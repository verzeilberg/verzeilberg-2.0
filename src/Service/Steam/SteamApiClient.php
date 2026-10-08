<?php

declare(strict_types=1);

namespace App\Service\Steam;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Read-only access to the Steam Web API and store for one profile (STEAM_API_KEY, STEAM_ID).
 *
 * Store details and achievements are one request per game, so they are requested in batches that run
 * concurrently instead of one after the other. Request URLs contain the API key, so errors are rethrown
 * with a message of their own.
 */
class SteamApiClient
{
    private const API_URL = 'https://api.steampowered.com';
    private const STORE_URL = 'https://store.steampowered.com/api/appdetails';

    private ?string $resolvedSteamId = null;

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(default::STEAM_API_KEY)%')] private ?string $apiKey,
        #[Autowire('%env(default::STEAM_ID)%')] private ?string $steamId,
    ) {
    }

    public function isConfigured(): bool
    {
        return trim((string) $this->apiKey) !== '' && trim((string) $this->steamId) !== '';
    }

    /**
     * The 64-bit Steam ID of STEAM_ID, which may also be a custom profile name or a profile URL
     * ("https://steamcommunity.com/id/name/" or ".../profiles/7656…").
     *
     * @throws SteamApiException
     */
    public function getSteamId(): string
    {
        if ($this->resolvedSteamId !== null) {
            return $this->resolvedSteamId;
        }
        if (!$this->isConfigured()) {
            throw new SteamApiException('Steam is not configured: set STEAM_API_KEY and STEAM_ID.');
        }

        $value = trim((string) $this->steamId);
        if (preg_match('#steamcommunity\.com/(id|profiles)/([^/?\#]+)#', $value, $match)) {
            $value = $match[2];
        }
        if (preg_match('/^\d{17}$/', $value)) {
            return $this->resolvedSteamId = $value;
        }

        $data = $this->api('ISteamUser/ResolveVanityURL/v1', ['vanityurl' => $value]);
        if ((int) ($data['response']['success'] ?? 0) !== 1 || !isset($data['response']['steamid'])) {
            throw new SteamApiException(sprintf('Steam profile "%s" was not found. Use the 17-digit Steam ID or the name from your profile URL.', $value));
        }

        return $this->resolvedSteamId = (string) $data['response']['steamid'];
    }

    /**
     * @return array{personaName: ?string, avatarUrl: ?string, profileUrl: ?string, currentlyPlaying: ?string, public: bool}
     *
     * @throws SteamApiException
     */
    public function getPlayerSummary(): array
    {
        $data = $this->api('ISteamUser/GetPlayerSummaries/v2', ['steamids' => $this->getSteamId()]);
        $player = $data['response']['players'][0] ?? null;
        if (!is_array($player)) {
            throw new SteamApiException('Steam did not return the profile.');
        }

        return [
            'personaName' => $player['personaname'] ?? null,
            'avatarUrl' => $player['avatarfull'] ?? null,
            'profileUrl' => $player['profileurl'] ?? null,
            'currentlyPlaying' => $player['gameextrainfo'] ?? null,
            // 3 = public; otherwise games and playtime can't be read
            'public' => (int) ($player['communityvisibilitystate'] ?? 0) === 3,
        ];
    }

    /**
     * All owned games (also free games that were played), in one request.
     *
     * @return list<array{appid: int, name?: string, playtime_forever?: int, playtime_2weeks?: int, rtime_last_played?: int, img_icon_url?: string}>
     *
     * @throws SteamApiException
     */
    public function getOwnedGames(): array
    {
        $data = $this->api('IPlayerService/GetOwnedGames/v1', [
            'steamid' => $this->getSteamId(),
            'include_appinfo' => 1,
            'include_played_free_games' => 1,
        ]);
        // A private "game details" setting gives an empty response instead of an error
        if (!isset($data['response']['game_count'])) {
            throw new SteamApiException('Steam returned no games: set "Game details" to Public in the Steam privacy settings.');
        }

        return array_values(array_filter($data['response']['games'] ?? [], fn ($game) => is_array($game) && isset($game['appid'])));
    }

    /**
     * Store details per game, requested concurrently. A game the store doesn't know (removed, tool, …) gets null;
     * a game whose request failed (e.g. rate limited) is left out, so it's tried again next time.
     *
     * @param list<int> $appIds
     *
     * @return array<int, array<string, mixed>|null>
     */
    public function getAppDetails(array $appIds): array
    {
        $responses = [];
        foreach ($appIds as $appId) {
            $responses[$appId] = $this->httpClient->request('GET', self::STORE_URL, [
                'query' => ['appids' => $appId, 'l' => 'english', 'filters' => 'basic,genres,developers,publishers,release_date,metacritic'],
                'timeout' => 20,
            ]);
        }

        $details = [];
        foreach ($responses as $appId => $response) {
            try {
                if ($response->getStatusCode() !== 200) {
                    continue;
                }
                $data = $response->toArray(false)[(string) $appId] ?? null;
                $details[$appId] = is_array($data) && ($data['success'] ?? false) && is_array($data['data'] ?? null) ? $data['data'] : null;
            } catch (HttpException) {
                continue;
            }
        }

        return $details;
    }

    /**
     * Unlocked and total achievements per game, requested concurrently. A game without achievements gives 0/0;
     * a failed request is left out.
     *
     * @param list<int> $appIds
     *
     * @return array<int, array{unlocked: int, total: int}>
     *
     * @throws SteamApiException when the Steam ID can't be determined
     */
    public function getAchievements(array $appIds): array
    {
        $steamId = $this->getSteamId();
        $responses = [];
        foreach ($appIds as $appId) {
            $responses[$appId] = $this->request('ISteamUserStats/GetPlayerAchievements/v1', ['steamid' => $steamId, 'appid' => $appId]);
        }

        $achievements = [];
        foreach ($responses as $appId => $response) {
            try {
                $status = $response->getStatusCode();
                $stats = $response->toArray(false)['playerstats'] ?? [];
            } catch (HttpException) {
                continue;
            }
            if ($status === 200 && ($stats['success'] ?? false)) {
                $list = is_array($stats['achievements'] ?? null) ? $stats['achievements'] : [];
                $achievements[$appId] = [
                    'unlocked' => count(array_filter($list, fn ($achievement) => (int) ($achievement['achieved'] ?? 0) === 1)),
                    'total' => count($list),
                ];
            } elseif ($status === 400 && str_contains(strtolower((string) ($stats['error'] ?? '')), 'no stats')) {
                // "Requested app has no stats": the game has no achievements
                $achievements[$appId] = ['unlocked' => 0, 'total' => 0];
            }
        }

        return $achievements;
    }

    /**
     * @param array<string, scalar> $query
     *
     * @return array<string, mixed>
     *
     * @throws SteamApiException
     */
    private function api(string $method, array $query): array
    {
        $response = $this->request($method, $query);
        try {
            $status = $response->getStatusCode();
            if ($status === 401 || $status === 403) {
                throw new SteamApiException('Steam refused the API key (check STEAM_API_KEY).');
            }
            if ($status === 429) {
                throw new SteamApiException('Steam is limiting the number of requests; try again later.');
            }
            if ($status >= 400) {
                throw new SteamApiException(sprintf('Steam answered with HTTP %d for %s.', $status, $method));
            }

            return $response->toArray();
        } catch (HttpException) {
            throw new SteamApiException(sprintf('Steam could not be reached or gave an invalid answer (%s).', $method));
        }
    }

    /** @param array<string, scalar> $query */
    private function request(string $method, array $query): ResponseInterface
    {
        return $this->httpClient->request('GET', self::API_URL . '/' . $method . '/', [
            'query' => ['key' => (string) $this->apiKey, 'format' => 'json'] + $query,
            'timeout' => 20,
        ]);
    }
}
