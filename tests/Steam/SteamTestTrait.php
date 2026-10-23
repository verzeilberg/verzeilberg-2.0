<?php

declare(strict_types=1);

namespace App\Tests\Steam;

use App\Service\Steam\SteamApiClient;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * A simulated Steam: owned games, store details and achievements come from the properties below,
 * and every request is recorded. Each test runs in a transaction that is rolled back.
 */
trait SteamTestTrait
{
    protected EntityManagerInterface $em;
    protected Connection $connection;

    /** @var list<string> request URLs, in order */
    protected array $requests = [];

    /** @var list<array<string, mixed>> */
    protected array $ownedGames = [];

    /** @var array<int, array<string, mixed>|null> store data per app id; null = not in the store; missing = HTTP 429 */
    protected array $storeDetails = [];

    /** @var array<int, array{int, int}|string|null> [unlocked, total] per app id; null = the game has no stats; 'private' = marked private */
    protected array $achievements = [];

    /** @var array<int, int> number of achievements in a game's public list (GetSchemaForGame); the last one is hidden */
    protected array $schemaTotals = [];

    /** @var array<int, array<string, int|float>> the game's own statistics per app id (GetUserStatsForGame) */
    protected array $userStats = [];

    /** Answer GetPlayerAchievements with a server error */
    protected bool $playerAchievementsDown = false;

    protected int $ownedGamesStatus = 200;
    protected bool $privateGameDetails = false;

    protected function beginSteamTransaction(): void
    {
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->connection = $this->em->getConnection();
        $this->connection->beginTransaction();
        // Start from an empty library (the test database may hold games of an earlier run)
        $this->connection->executeStatement('DELETE FROM steam_game');
        $this->connection->executeStatement('DELETE FROM steam_account');
        // No cached Steam answers from an earlier test
        static::getContainer()->get('cache.steam')->clear();
    }

    protected function rollBackSteamTransaction(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
    }

    protected function steamClient(string $steamId = '76561197960287930'): SteamApiClient
    {
        $http = new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->requests[] = $url;
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            if (str_contains($url, 'ResolveVanityURL')) {
                return $this->json(['response' => $query['vanityurl'] === 'sander' ? ['success' => 1, 'steamid' => '76561197960287930'] : ['success' => 42, 'message' => 'No match']]);
            }
            if (str_contains($url, 'GetPlayerSummaries')) {
                return $this->json(['response' => ['players' => [[
                    'steamid' => $query['steamids'], 'personaname' => 'Sander', 'profileurl' => 'https://steamcommunity.com/id/sander/',
                    'avatarfull' => 'https://avatars.example/full.jpg', 'communityvisibilitystate' => 3, 'gameextrainfo' => 'Factorio',
                ]]]]);
            }
            if (str_contains($url, 'GetOwnedGames')) {
                if ($this->ownedGamesStatus !== 200) {
                    return $this->json(['error' => 'Forbidden'], $this->ownedGamesStatus);
                }

                return $this->json(['response' => $this->privateGameDetails ? [] : ['game_count' => count($this->ownedGames), 'games' => $this->ownedGames]]);
            }
            if (str_contains($url, 'appdetails')) {
                $appId = (int) $query['appids'];
                if (!array_key_exists($appId, $this->storeDetails)) {
                    return new MockResponse('', ['http_code' => 429]);
                }
                $data = $this->storeDetails[$appId];

                return $this->json([(string) $appId => $data === null ? ['success' => false] : ['success' => true, 'data' => $data]]);
            }
            if (str_contains($url, 'GetPlayerAchievements')) {
                if ($this->playerAchievementsDown) {
                    return new MockResponse('', ['http_code' => 500]);
                }
                $result = $this->achievements[(int) $query['appid']] ?? null;
                if ($result === 'private') {
                    return $this->json(['playerstats' => ['error' => 'Profile is not public', 'success' => false]], 403);
                }
                if ($result === null) {
                    return $this->json(['playerstats' => ['error' => 'Requested app has no stats', 'success' => false]], 400);
                }
                [$unlocked, $total] = $result;
                $list = [];
                for ($i = 0; $i < $total; ++$i) {
                    // Achievement i is unlocked on day i (so the last unlocked one is the most recent)
                    $list[] = ['apiname' => 'A' . $i, 'achieved' => $i < $unlocked ? 1 : 0, 'unlocktime' => $i < $unlocked ? 1700000000 + $i * 86400 : 0];
                }

                return $this->json(['playerstats' => ['steamID' => $query['steamid'], 'success' => true, 'achievements' => $list]]);
            }

            if (str_contains($url, 'GetSchemaForGame')) {
                $total = $this->schemaTotals[(int) $query['appid']] ?? 0;
                $achievements = [];
                for ($i = 0; $i < $total; ++$i) {
                    $hidden = $i === $total - 1;
                    $achievements[] = array_filter([
                        'name' => 'A' . $i, 'displayName' => 'Achievement ' . $i, 'hidden' => $hidden ? 1 : 0,
                        // Steam leaves the description of a hidden achievement out of the list
                        'description' => $hidden ? null : 'Do thing ' . $i,
                        'icon' => 'https://icons.example/' . $i . '.jpg', 'icongray' => 'https://icons.example/' . $i . '_gray.jpg',
                    ], fn ($value) => $value !== null);
                }
                $stats = array_map(fn ($name) => ['name' => $name, 'displayName' => $name === 'zombies_killed' ? 'Zombies killed' : ''], array_keys($this->userStats[(int) $query['appid']] ?? []));

                return $this->json(['game' => ['gameName' => 'x', 'availableGameStats' => $achievements ? ['achievements' => $achievements, 'stats' => $stats] : []]]);
            }
            if (str_contains($url, 'GetGlobalAchievementPercentagesForApp')) {
                $total = $this->schemaTotals[(int) $query['gameid']] ?? 0;
                // A0 is the most common, the last one the rarest
                $rows = [];
                for ($i = 0; $i < $total; ++$i) {
                    $rows[] = ['name' => 'A' . $i, 'percent' => (string) round(90 / ($i + 1), 3)];
                }

                return $this->json(['achievementpercentages' => ['achievements' => $rows]]);
            }
            if (str_contains($url, 'GetUserStatsForGame')) {
                $stats = $this->userStats[(int) $query['appid']] ?? null;
                if ($stats === null) {
                    return $this->json([], 400);
                }

                return $this->json(['playerstats' => ['steamID' => $query['steamid'], 'gameName' => 'x', 'stats' => array_map(fn ($name, $value) => ['name' => $name, 'value' => $value], array_keys($stats), $stats)]]);
            }

            return new MockResponse('{}', ['http_code' => 404]);
        });

        return new SteamApiClient($http, 'secret-steam-key', $steamId);
    }

    /** @param array<array-key, mixed> $data keys can be app ids */
    private function json(array $data, int $status = 200): MockResponse
    {
        return new MockResponse((string) json_encode($data), ['http_code' => $status, 'response_headers' => ['content-type' => 'application/json']]);
    }

    /** @return array<string, mixed> an owned game as GetOwnedGames gives it */
    protected function owned(int $appId, string $name, int $minutes, ?string $lastPlayed = null, int $twoWeeks = 0): array
    {
        return array_filter([
            'appid' => $appId,
            'name' => $name,
            'playtime_forever' => $minutes,
            'playtime_2weeks' => $twoWeeks ?: null,
            'img_icon_url' => 'icon' . $appId,
            'rtime_last_played' => $lastPlayed ? (new \DateTimeImmutable($lastPlayed))->getTimestamp() : 0,
        ], fn ($value) => $value !== null);
    }

    /**
     * @param list<string> $genres
     *
     * @return array<string, mixed> store data as appdetails gives it
     */
    protected function store(string $name, array $genres = ['Action']): array
    {
        return [
            'type' => 'game', 'name' => $name,
            'short_description' => 'About <b>' . $name . '</b> &amp; more',
            'header_image' => 'https://cdn.example/' . rawurlencode($name) . '/header.jpg',
            'website' => 'https://' . strtolower((string) preg_replace('/\W/', '', $name)) . '.example',
            'genres' => array_map(fn ($genre, $id) => ['id' => (string) $id, 'description' => $genre], $genres, array_keys($genres)),
            'developers' => ['Studio ' . $name],
            'publishers' => ['Publisher'],
            'release_date' => ['coming_soon' => false, 'date' => '21 Oct, 2021'],
            'metacritic' => ['score' => 88, 'url' => 'https://metacritic.example'],
        ];
    }
}
