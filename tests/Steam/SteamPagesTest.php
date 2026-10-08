<?php

declare(strict_types=1);

namespace App\Tests\Steam;

use App\Entity\Role;
use App\Entity\Steam\SteamGame;
use App\Entity\User;
use App\Service\Steam\SteamApiClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The public game library, the home page block and the admin page.
 */
class SteamPagesTest extends WebTestCase
{
    use SteamTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->beginSteamTransaction();
        static::getContainer()->set(SteamApiClient::class, $this->steamClient());
    }

    protected function tearDown(): void
    {
        $this->rollBackSteamTransaction();
        parent::tearDown();
    }

    public function testLibraryShowsPlayedVisibleGamesWithFiltersAndSorting(): void
    {
        $this->game(10, 'Factorio', 31500, '-2 days', ['Simulation', 'Strategy'], [12, 40]);
        $this->game(20, 'Portal 2', 900, '-1 year', ['Action', 'Puzzle'], [51, 51]);
        $this->game(30, 'Never Played', 0, null, ['Action']);
        $this->game(40, 'Secret Game', 60, '-1 day', ['Action'])->setHidden(true);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/games');
        $this->assertResponseIsSuccessful();
        $this->assertSame(['Factorio', 'Portal 2'], $this->libraryNames($crawler), 'played games, last played first; hidden left out');
        $this->assertSame(['Factorio', 'Portal 2'], $crawler->filter('h2:contains("Recently played") + .row .card-title')->each(fn ($node) => $node->text()));
        $this->assertSame(['3', '2', '540', '63'], $crawler->filter('.fs-3')->each(fn ($node) => $node->text()), 'games, played, hours, achievements (hidden left out)');
        $this->assertStringContainsString('12/40', $crawler->filter('.steam-game')->first()->text());
        $this->assertSame(['', 'Action', 'Puzzle', 'Simulation', 'Strategy'], $crawler->filter('#gameGenre option')->each(fn ($node) => $node->attr('value')));

        $this->assertSame(['Never Played', 'Portal 2'], $this->libraryNames($this->client->request('GET', '/games?show=all&genre=Action&order=name')));
        $this->assertSame(['Portal 2'], $this->libraryNames($this->client->request('GET', '/games?q=port')));
        $this->assertSame(['Portal 2', 'Factorio'], $this->libraryNames($this->client->request('GET', '/games?order=achievements')));
        $this->assertSame([], $this->libraryNames($this->client->request('GET', '/games?q=100%25')), 'LIKE wildcards are literal');
    }

    public function testEmptyLibraryAndHomeBlock(): void
    {
        $this->client->request('GET', '/games');
        $this->assertSelectorTextContains('.alert-info', 'hasn\'t been loaded yet');
        $this->client->request('GET', '/');
        $this->assertSelectorNotExists('.steam-game', 'no block without games');

        $this->game(10, 'Factorio', 600, '-1 day', ['Strategy']);
        $this->em->flush();
        $this->client->request('GET', '/');
        $this->assertSelectorTextContains('.steam-game .card-title', 'Factorio');
    }

    public function testAdminSyncAndHideAGame(): void
    {
        $this->loginAsAdmin();
        $this->ownedGames = [$this->owned(10, 'Factorio', 600, '-1 day'), $this->owned(20, 'Portal 2', 90, '-1 week')];
        $this->storeDetails = [10 => $this->store('Factorio', ['Strategy']), 20 => $this->store('Portal 2')];
        $this->achievements = [10 => [3, 10], 20 => null];

        $crawler = $this->client->request('GET', '/admin/steam/');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.card-body', 'Not synced yet');
        $this->client->submit($crawler->filter('form[action$="/admin/steam/sync"]')->form());
        $this->assertResponseRedirects('/admin/steam/');

        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'Steam synced: 2 added');
        $this->assertSame(['Factorio', 'Portal 2'], $crawler->filter('tbody tr td:first-child a')->each(fn ($node) => $node->text()));
        $this->assertStringContainsString('None', $crawler->filter('tbody tr')->eq(1)->text(), 'Portal 2 has no achievements');

        // Hide Factorio: gone from the public page, still in the admin
        $this->client->submit($crawler->filter('tbody tr')->first()->filter('form')->form());
        $this->assertResponseRedirects('/admin/steam/');
        $this->assertTrue($this->em->getRepository(SteamGame::class)->findOneBy(['appId' => 10])?->isHidden());
        $this->assertSame(['Portal 2'], $this->libraryNames($this->client->request('GET', '/games')));
    }

    public function testAdminShowsTheSyncError(): void
    {
        $this->loginAsAdmin();
        $this->privateGameDetails = true;

        $crawler = $this->client->request('GET', '/admin/steam/');
        $this->client->submit($crawler->filter('form[action$="/admin/steam/sync"]')->form());
        $this->client->followRedirect();

        $this->assertSelectorTextContains('.alert-dismissible.alert-danger', 'Steam sync failed');
        $this->assertSelectorTextContains('.card .alert-danger', 'Game details');
    }

    /** @return list<string> names in the library grid (not the "Recently played" row) */
    private function libraryNames(\Symfony\Component\DomCrawler\Crawler $crawler): array
    {
        return $crawler->filter('#library ~ .row .steam-game .card-title')->each(fn ($node) => $node->text());
    }

    /**
     * @param list<string>     $genres
     * @param array{int, int}|null $achievements [unlocked, total]
     */
    private function game(int $appId, string $name, int $minutes, ?string $lastPlayed, array $genres, ?array $achievements = null): SteamGame
    {
        $game = (new SteamGame($appId))->setName($name)->setPlaytimeForever($minutes)
            ->setLastPlayedAt($lastPlayed ? new \DateTimeImmutable($lastPlayed) : null)
            ->setGenres($genres)->setDetailsFetchedAt(new \DateTimeImmutable());
        if ($achievements) {
            $game->setAchievements(...$achievements);
        }
        $this->em->persist($game);
        $this->em->flush();

        return $game;
    }

    private function loginAsAdmin(): void
    {
        $role = $this->em->getRepository(Role::class)->findOneBy(['name' => 'ROLE_ADMIN']) ?? (new Role())->setName('ROLE_ADMIN')->setDescription('ROLE_ADMIN');
        $this->em->persist($role);
        $user = (new User())->setEmail(uniqid('steam-') . '@example.com')->setFullName('Steam test')->setPassword('not-used');
        $user->addRole($role);
        $this->em->persist($user);
        $this->em->flush();

        $this->client->loginUser($user, 'primary_auth');
    }
}
