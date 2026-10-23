<?php

declare(strict_types=1);

namespace App\Service\Steam;

use App\Entity\Steam\SteamGame;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpException;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Collects a game's statistics live from Steam: the achievement list (names, descriptions, icons), the player's
 * progress, how many players have each achievement, and the game's own stats.
 *
 * The four requests run at the same time. The achievement list and global percentages hardly change and are cached
 * for a day; the player's progress and stats for 10 minutes ($refresh skips the cache). The stored achievement count
 * of the game is updated with what Steam says, so the list in the admin stays right.
 */
class SteamGameStatsProvider
{
    private const LONG = 86400;
    private const SHORT = 600;

    public function __construct(
        private SteamApiClient $client,
        private EntityManagerInterface $em,
        #[Autowire(service: 'cache.steam')] private CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * @throws SteamApiException when Steam isn't configured or the profile can't be found
     */
    public function forGame(SteamGame $game, bool $refresh = false): SteamGameStats
    {
        $steamId = $this->client->getSteamId();
        $appId = $game->getAppId();

        $parts = [
            'schema' => ['ISteamUserStats/GetSchemaForGame/v2', ['appid' => $appId, 'l' => 'english'], self::LONG],
            'global' => ['ISteamUserStats/GetGlobalAchievementPercentagesForApp/v2', ['gameid' => $appId], self::LONG],
            'player' => ['ISteamUserStats/GetPlayerAchievements/v1', ['appid' => $appId, 'steamid' => $steamId, 'l' => 'english'], self::SHORT],
            'stats' => ['ISteamUserStats/GetUserStatsForGame/v2', ['appid' => $appId, 'steamid' => $steamId], self::SHORT],
        ];

        // Cached answers, and requests (started together) for the rest
        $items = $data = $responses = [];
        foreach ($parts as $part => [$method, $query]) {
            $items[$part] = $this->cache->getItem(sprintf('steam_%s_%d_%s', $part, $appId, $part === 'player' || $part === 'stats' ? $steamId : 'all'));
            if ($items[$part]->isHit() && !($refresh && in_array($part, ['player', 'stats'], true))) {
                $data[$part] = $items[$part]->get();
            } else {
                $responses[$part] = $this->client->request($method, $query);
            }
        }

        $errors = [];
        foreach ($responses as $part => $response) {
            $answer = $this->read($response);
            if ($answer === null) {
                $errors[] = sprintf('Steam gaf geen antwoord op de aanvraag voor %s; probeer het later opnieuw.', ['schema' => 'de prestatielijst', 'global' => 'de wereldwijde percentages', 'player' => 'de prestaties', 'stats' => 'de statistieken'][$part]);
                $data[$part] = null;
                continue;
            }
            $data[$part] = $answer;
            $this->save($items[$part], $answer, $parts[$part][2]);
        }

        $stats = $this->build($data, $errors);
        $this->updateGame($game, $stats);

        return $stats;
    }

    /**
     * Status code and decoded body, or null when the request failed (network, 5xx, rate limit).
     *
     * @return array{status: int, body: array<mixed>}|null
     */
    private function read(ResponseInterface $response): ?array
    {
        try {
            $status = $response->getStatusCode();
            if ($status === 429 || $status >= 500) {
                return null;
            }

            return ['status' => $status, 'body' => $response->toArray(false)];
        } catch (HttpException) {
            return null;
        }
    }

    /** @param array{status: int, body: array<mixed>} $answer */
    private function save(CacheItemInterface $item, array $answer, int $lifetime): void
    {
        $item->set($answer)->expiresAfter($lifetime);
        $this->cache->save($item);
    }

    /**
     * @param array<string, array{status: int, body: array<mixed>}|null> $data
     * @param list<string>                                                $errors
     */
    private function build(array $data, array $errors): SteamGameStats
    {
        $schema = $data['schema']['body']['game']['availableGameStats'] ?? [];
        $player = $data['player'] ?? null;
        $playerError = strtolower((string) ($player['body']['playerstats']['error'] ?? ''));

        $percentages = [];
        foreach ($data['global']['body']['achievementpercentages']['achievements'] ?? [] as $row) {
            if (isset($row['name'], $row['percent'])) {
                $percentages[(string) $row['name']] = round((float) $row['percent'], 1);
            }
        }

        $progress = [];
        foreach (($player['body']['playerstats']['success'] ?? false) ? ($player['body']['playerstats']['achievements'] ?? []) : [] as $row) {
            if (isset($row['apiname'])) {
                $progress[(string) $row['apiname']] = $row;
            }
        }

        $achievements = [];
        foreach (is_array($schema['achievements'] ?? null) ? $schema['achievements'] : [] as $definition) {
            $apiName = (string) ($definition['name'] ?? '');
            if ($apiName === '') {
                continue;
            }
            $own = $progress[$apiName] ?? null;
            $achieved = (int) ($own['achieved'] ?? 0) === 1;
            $unlockTime = (int) ($own['unlocktime'] ?? 0);
            $hidden = (int) ($definition['hidden'] ?? 0) === 1;
            // A hidden achievement's description is only shown once it's unlocked
            $description = trim((string) ($definition['description'] ?? $own['description'] ?? ''));
            $achievements[] = [
                'apiName' => $apiName,
                'name' => (string) ($definition['displayName'] ?? $own['name'] ?? $apiName),
                'description' => $description !== '' && (!$hidden || $achieved) ? $description : null,
                'icon' => ($achieved ? ($definition['icon'] ?? null) : ($definition['icongray'] ?? $definition['icon'] ?? null)) ?: null,
                'hidden' => $hidden,
                'achieved' => $achieved,
                'unlockedAt' => $achieved && $unlockTime > 0 ? (new \DateTimeImmutable())->setTimestamp($unlockTime) : null,
                'globalPercent' => $percentages[$apiName] ?? null,
            ];
        }

        // Unlocked first, most recent first; then locked, most common (easiest) first
        usort($achievements, fn (array $a, array $b) => [$b['achieved'], $b['unlockedAt']?->getTimestamp() ?? 0, $b['globalPercent'] ?? -1, $a['name']]
            <=> [$a['achieved'], $a['unlockedAt']?->getTimestamp() ?? 0, $a['globalPercent'] ?? -1, $b['name']]);

        $statNames = [];
        foreach (is_array($schema['stats'] ?? null) ? $schema['stats'] : [] as $definition) {
            if (isset($definition['name'])) {
                $statNames[(string) $definition['name']] = trim((string) ($definition['displayName'] ?? '')) ?: (string) $definition['name'];
            }
        }
        $stats = [];
        foreach ($data['stats']['body']['playerstats']['stats'] ?? [] as $row) {
            if (isset($row['name'], $row['value'])) {
                $stats[] = ['name' => $statNames[(string) $row['name']] ?? (string) $row['name'], 'value' => $row['value'] + 0];
            }
        }
        usort($stats, fn (array $a, array $b) => strnatcasecmp($a['name'], $b['name']));

        $status = match (true) {
            $player !== null && $player['status'] === 403 && str_contains($playerError, 'not public') => SteamGameStats::STATUS_PRIVATE,
            $achievements === [] && ($data['schema'] !== null || str_contains($playerError, 'no stats')) => SteamGameStats::STATUS_NONE,
            $player === null || $data['schema'] === null => SteamGameStats::STATUS_ERROR,
            default => SteamGameStats::STATUS_OK,
        };

        return new SteamGameStats($status, $achievements, $stats, $errors, new \DateTimeImmutable());
    }

    /** Keeps the stored counts (admin list, public page) in line with what Steam just said */
    private function updateGame(SteamGame $game, SteamGameStats $stats): void
    {
        match ($stats->status) {
            SteamGameStats::STATUS_OK => $game->setAchievements($stats->getUnlocked(), $stats->getTotal()),
            SteamGameStats::STATUS_NONE => $game->setAchievements(0, 0),
            SteamGameStats::STATUS_PRIVATE => $game->setAchievementsPrivate($stats->getTotal() ?: null),
            default => null,
        };
        $this->em->flush();
    }
}
