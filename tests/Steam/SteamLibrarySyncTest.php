<?php

declare(strict_types=1);

namespace App\Tests\Steam;

use App\Entity\Steam\SteamAccount;
use App\Entity\Steam\SteamGame;
use App\Repository\Steam\SteamAccountRepository;
use App\Repository\Steam\SteamGameRepository;
use App\Service\Steam\SteamApiClient;
use App\Service\Steam\SteamApiException;
use App\Service\Steam\SteamLibrarySync;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Steam library sync against a simulated Steam API: what is stored, and which requests are (not) made.
 */
class SteamLibrarySyncTest extends KernelTestCase
{
    use SteamTestTrait;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->beginSteamTransaction();
    }

    protected function tearDown(): void
    {
        $this->rollBackSteamTransaction();
        parent::tearDown();
    }

    public function testFirstSyncStoresTheLibraryProfileDetailsAndAchievements(): void
    {
        $this->ownedGames = [
            $this->owned(10, 'Factorio', 31500, '2026-10-06 21:00', 640),
            $this->owned(20, 'Portal 2', 900, '2025-01-02 20:00'),
            $this->owned(30, 'Never Played', 0),
            $this->owned(40, 'Delisted Game', 120, '2020-05-05 12:00'),
        ];
        $this->storeDetails = [10 => $this->store('Factorio', ['Simulation', 'Strategy']), 20 => $this->store('Portal 2', ['Action', 'Adventure']), 30 => $this->store('Never Played'), 40 => null];
        $this->achievements = [10 => [12, 40], 20 => [51, 51], 40 => null];

        $result = $this->sync()->sync();

        $this->assertSame([4, 0, 0, 4, 3], [$result->added, $result->updated, $result->removed, $result->details, $result->achievements]);
        $games = $this->games();

        $factorio = $games['Factorio'];
        $this->assertSame([31500, 640, 525.0], [$factorio->getPlaytimeForever(), $factorio->getPlaytimeTwoWeeks(), $factorio->getHoursPlayed()]);
        $this->assertSame(['Simulation', 'Strategy'], $factorio->getGenres());
        $this->assertSame('About Factorio & more', $factorio->getShortDescription(), 'store HTML becomes plain text');
        $this->assertSame(['Studio Factorio', 88, '21 Oct, 2021'], [$factorio->getDevelopers()[0], $factorio->getMetacriticScore(), $factorio->getReleaseDate()]);
        $this->assertSame([12, 40, 30], [$factorio->getAchievementsUnlocked(), $factorio->getAchievementsTotal(), $factorio->getAchievementPercentage()]);
        $this->assertSame('https://media.steampowered.com/steamcommunity/public/images/apps/10/icon10.jpg', $factorio->getIconUrl());

        $this->assertSame(100, $games['Portal 2']->getAchievementPercentage());
        $this->assertNull($games['Never Played']->getAchievementsTotal(), 'unplayed: achievements not requested');
        $this->assertSame(0, $games['Delisted Game']->getAchievementsTotal(), 'no stats = no achievements');
        $this->assertNotNull($games['Delisted Game']->getDetailsFetchedAt(), 'no store page: not asked again for 30 days');
        $this->assertSame([], $games['Delisted Game']->getGenres());
        $this->assertStringContainsString('/40/header.jpg', $games['Delisted Game']->getHeaderImage(), 'falls back to Steam\'s standard header image');

        $account = $this->account();
        $this->assertSame(['Sander', 'Factorio', null], [$account->getPersonaName(), $account->getCurrentlyPlaying(), $account->getLastSyncError()]);
        $this->assertNotNull($account->getLastSyncedAt());
    }

    public function testNextSyncOnlyRequestsWhatChanged(): void
    {
        $this->ownedGames = [$this->owned(10, 'Factorio', 600, '2026-10-01'), $this->owned(20, 'Portal 2', 900, '2025-01-02'), $this->owned(30, 'Refunded', 5, '2024-01-01')];
        $this->storeDetails = [10 => $this->store('Factorio'), 20 => $this->store('Portal 2'), 30 => $this->store('Refunded')];
        $this->achievements = [10 => [1, 10], 20 => [5, 5], 30 => [0, 3]];
        $this->sync()->sync();

        // Factorio was played, Portal 2 wasn't, "Refunded" left the library
        $this->requests = [];
        $this->ownedGames = [$this->owned(10, 'Factorio', 720, '2026-10-07', 120), $this->owned(20, 'Portal 2', 900, '2025-01-02')];
        $this->achievements[10] = [4, 10];
        $result = $this->sync()->sync();

        $this->assertSame([0, 1, 1, 0, 1], [$result->added, $result->updated, $result->removed, $result->details, $result->achievements]);
        $this->assertSame(0, count(array_filter($this->requests, fn ($url) => str_contains($url, 'appdetails'))), 'store details are kept');
        $this->assertSame(['appid=10'], array_values(array_map(
            fn ($url) => (string) preg_replace('/.*(appid=\d+).*/', '$1', $url),
            array_filter($this->requests, fn ($url) => str_contains($url, 'GetPlayerAchievements'))
        )), 'only the game that was played');
        $this->assertSame(4, $this->games()['Factorio']->getAchievementsUnlocked());
        $this->assertArrayNotHasKey('Refunded', $this->games());
    }

    public function testDetailsAreSpreadOverRunsAndRateLimitedOnesAreRetried(): void
    {
        foreach (range(1, 5) as $appId) {
            $this->ownedGames[] = $this->owned($appId, 'Game ' . $appId, $appId * 100);
            $this->storeDetails[$appId] = $this->store('Game ' . $appId);
        }
        unset($this->storeDetails[5]); // the store answers 429

        $result = $this->sync()->sync(detailsLimit: 3, achievementsLimit: 0);

        // Most played first: 5 (rate limited), 4 and 3
        $this->assertSame([2, 3], [$result->details, $result->detailsPending]);
        $this->assertSame(5, $result->achievementsPending);
        $this->assertSame(['Game 4', 'Game 3'], $this->namesWithDetails());

        $result = $this->sync()->sync(detailsLimit: 3, achievementsLimit: 0);
        $this->assertSame([2, 1], [$result->details, $result->detailsPending], 'Game 2 and 1 now; Game 5 still rate limited');
        $this->assertSame(['Game 4', 'Game 3', 'Game 2', 'Game 1'], $this->namesWithDetails());
        $this->assertStringContainsString('Next run: 1 detail(s)', $result->summary());
    }

    public function testCustomProfileNameAndUrlAreResolved(): void
    {
        foreach (['sander', 'https://steamcommunity.com/id/sander/', 'https://steamcommunity.com/profiles/76561197960287930'] as $steamId) {
            $this->assertSame('76561197960287930', $this->steamClient($steamId)->getSteamId(), $steamId);
        }

        $this->expectException(SteamApiException::class);
        $this->expectExceptionMessage('Steam profile "nobody" was not found');
        $this->steamClient('nobody')->getSteamId();
    }

    public function testErrorsAreStoredWithoutTheApiKey(): void
    {
        $this->privateGameDetails = true;
        try {
            $this->sync()->sync();
            $this->fail('Private game details must stop the sync');
        } catch (SteamApiException $e) {
            $this->assertStringContainsString('"Game details" to Public', $e->getMessage());
        }
        $this->assertStringContainsString('Game details', (string) $this->account()->getLastSyncError());
        $this->assertNull($this->account()->getLastSyncedAt());

        $this->privateGameDetails = false;
        $this->ownedGamesStatus = 403;
        try {
            $this->sync()->sync();
            $this->fail('A refused key must stop the sync');
        } catch (SteamApiException $e) {
            $this->assertStringContainsString('refused the API key', $e->getMessage());
            $this->assertStringNotContainsString('secret-steam-key', $e->getMessage());
        }
    }

    public function testNotConfigured(): void
    {
        $client = new SteamApiClient(new \Symfony\Component\HttpClient\MockHttpClient(), '', '');
        $this->assertFalse($client->isConfigured());

        $this->expectException(SteamApiException::class);
        $this->expectExceptionMessage('set STEAM_API_KEY and STEAM_ID');
        $client->getSteamId();
    }

    private function sync(): SteamLibrarySync
    {
        return new SteamLibrarySync(
            $this->steamClient(),
            static::getContainer()->get(SteamAccountRepository::class),
            static::getContainer()->get(SteamGameRepository::class),
            $this->em
        );
    }

    /** @return array<string, SteamGame> by name, most played first */
    private function games(): array
    {
        $games = [];
        foreach ($this->em->getRepository(SteamGame::class)->findBy([], ['playtimeForever' => 'DESC']) as $game) {
            $games[$game->getName()] = $game;
        }

        return $games;
    }

    /** @return list<string> */
    private function namesWithDetails(): array
    {
        return array_values(array_map(fn (SteamGame $game) => $game->getName(), array_filter($this->games(), fn (SteamGame $game) => $game->getDetailsFetchedAt() !== null)));
    }

    private function account(): SteamAccount
    {
        $account = $this->em->getRepository(SteamAccount::class)->findOneBy(['steamId' => '76561197960287930']);
        $this->assertNotNull($account);

        return $account;
    }
}
