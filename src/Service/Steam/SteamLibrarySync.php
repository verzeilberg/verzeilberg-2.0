<?php

declare(strict_types=1);

namespace App\Service\Steam;

use App\Entity\Steam\SteamAccount;
use App\Entity\Steam\SteamGame;
use App\Repository\Steam\SteamAccountRepository;
use App\Repository\Steam\SteamGameRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Brings the local copy of the Steam library up to date, with as few requests as possible:
 *  - one request for the profile and one for all owned games with their playtime;
 *  - store details only for new games, and again after DETAILS_MAX_AGE (the store limits requests);
 *  - achievements only for games played since they were last read.
 * Details and achievements are limited per run; whatever is left is done by the next run.
 */
class SteamLibrarySync
{
    public const DETAILS_MAX_AGE = '-30 days';
    private const DETAILS_BATCH = 10;
    private const ACHIEVEMENTS_BATCH = 20;

    public function __construct(
        private SteamApiClient $client,
        private SteamAccountRepository $accountRepository,
        private SteamGameRepository $gameRepository,
        private EntityManagerInterface $em,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /** The account of STEAM_ID, if it was synced before */
    public function getAccount(): ?SteamAccount
    {
        try {
            return $this->client->isConfigured() ? $this->accountRepository->findBySteamId($this->client->getSteamId()) : null;
        } catch (SteamApiException) {
            return $this->accountRepository->findOneBy([], ['id' => 'DESC']);
        }
    }

    /**
     * @throws SteamApiException (also stored on the account, to show in the admin)
     */
    public function sync(int $detailsLimit = 40, int $achievementsLimit = 100): SteamSyncResult
    {
        $account = null;
        try {
            $steamId = $this->client->getSteamId();
            $account = $this->accountRepository->findBySteamId($steamId) ?? new SteamAccount($steamId);
            $this->em->persist($account);

            $profile = $this->client->getPlayerSummary();
            $account->updateProfile($profile['personaName'], $profile['avatarUrl'], $profile['profileUrl'], $profile['currentlyPlaying']);

            [$added, $updated, $removed] = $this->syncOwnedGames($this->client->getOwnedGames());
            $this->em->flush();

            [$details, $detailsPending] = $this->syncDetails($detailsLimit);
            [$achievements, $achievementsPending] = $this->syncAchievements($achievementsLimit);
        } catch (SteamApiException $e) {
            if ($account !== null) {
                $account->markSynced($e->getMessage());
                $this->em->flush();
            }
            throw $e;
        }

        $account->markSynced();
        $this->em->flush();

        return new SteamSyncResult($added, $updated, $removed, $details, $achievements, $detailsPending, $achievementsPending);
    }

    /**
     * @param list<array<string, mixed>> $ownedGames
     *
     * @return array{int, int, int} added, updated, removed
     */
    private function syncOwnedGames(array $ownedGames): array
    {
        $existing = $this->gameRepository->findAllIndexedByAppId();
        $added = $updated = 0;

        foreach ($ownedGames as $owned) {
            $appId = (int) $owned['appid'];
            $game = $existing[$appId] ?? null;
            if ($game === null) {
                $game = new SteamGame($appId);
                $this->em->persist($game);
                ++$added;
            }
            unset($existing[$appId]);

            $lastPlayed = (int) ($owned['rtime_last_played'] ?? 0);
            $before = [$game->getName(), $game->getPlaytimeForever(), $game->getPlaytimeTwoWeeks(), $game->getLastPlayedAt()?->getTimestamp()];
            $game->setName(mb_substr((string) ($owned['name'] ?? $game->getName() ?: 'App ' . $appId), 0, 255))
                ->setPlaytimeForever((int) ($owned['playtime_forever'] ?? 0))
                ->setPlaytimeTwoWeeks((int) ($owned['playtime_2weeks'] ?? 0))
                ->setLastPlayedAt($lastPlayed > 0 ? (new \DateTimeImmutable())->setTimestamp($lastPlayed) : null)
                ->setIconHash(($owned['img_icon_url'] ?? '') !== '' ? (string) $owned['img_icon_url'] : $game->getIconHash());
            $after = [$game->getName(), $game->getPlaytimeForever(), $game->getPlaytimeTwoWeeks(), $game->getLastPlayedAt()?->getTimestamp()];
            if ($game->getId() !== null && $before !== $after) {
                ++$updated;
            }
        }

        // No longer in the library (refunded, family sharing ended, ...); a game I reviewed stays, with its review
        $removed = 0;
        foreach ($existing as $game) {
            if ($game->getReview() === null) {
                $this->em->remove($game);
                ++$removed;
            }
        }

        return [$added, $updated, $removed];
    }

    /** @return array{int, int} read, still to do */
    private function syncDetails(int $limit): array
    {
        $due = $this->gameRepository->createQueryBuilder('g')
            ->where('g.detailsFetchedAt IS NULL OR g.detailsFetchedAt < :maxAge')
            ->setParameter('maxAge', new \DateTimeImmutable(self::DETAILS_MAX_AGE))
            // New games first, then the most played
            ->addSelect('CASE WHEN g.detailsFetchedAt IS NULL THEN 0 ELSE 1 END AS HIDDEN known')
            ->orderBy('known', 'ASC')->addOrderBy('g.playtimeForever', 'DESC')
            ->getQuery()->getResult();

        $read = 0;
        foreach (array_chunk(array_slice($due, 0, max(0, $limit)), self::DETAILS_BATCH) as $batch) {
            $byAppId = [];
            foreach ($batch as $game) {
                $byAppId[$game->getAppId()] = $game;
            }
            foreach ($this->client->getAppDetails(array_keys($byAppId)) as $appId => $data) {
                $this->applyDetails($byAppId[$appId], $data);
                ++$read;
            }
            $this->em->flush();
        }

        return [$read, count($due) - $read];
    }

    /** @param array<string, mixed>|null $data null: the store has no page for the game */
    private function applyDetails(SteamGame $game, ?array $data): void
    {
        $game->setDetailsFetchedAt(new \DateTimeImmutable());
        if ($data === null) {
            return;
        }

        $descriptions = fn (mixed $list) => is_array($list)
            ? array_values(array_unique(array_filter(array_map(fn ($item) => trim(html_entity_decode(strip_tags((string) (is_array($item) ? ($item['description'] ?? '') : $item)), ENT_QUOTES | ENT_HTML5)), $list))))
            : [];

        $game->setHeaderImage(is_string($data['header_image'] ?? null) ? $data['header_image'] : null)
            ->setShortDescription(($text = trim(html_entity_decode(strip_tags((string) ($data['short_description'] ?? '')), ENT_QUOTES | ENT_HTML5))) !== '' ? $text : null)
            ->setGenres($descriptions($data['genres'] ?? null))
            ->setDevelopers($descriptions($data['developers'] ?? null))
            ->setPublishers($descriptions($data['publishers'] ?? null))
            ->setReleaseDate(($date = trim((string) ($data['release_date']['date'] ?? ''))) !== '' ? mb_substr($date, 0, 50) : null)
            ->setWebsite(is_string($data['website'] ?? null) && str_starts_with($data['website'], 'http') ? mb_substr($data['website'], 0, 500) : null)
            ->setMetacriticScore(isset($data['metacritic']['score']) ? (int) $data['metacritic']['score'] : null);
    }

    /** @return array{int, int} read, still to do */
    private function syncAchievements(int $limit): array
    {
        $due = array_values(array_filter(
            $this->gameRepository->findBy([], ['lastPlayedAt' => 'DESC']),
            fn (SteamGame $game) => $game->needsAchievementRefresh()
        ));
        // Private games (asked again every sync) after the others, so they never take the place of a game that changed
        usort($due, fn (SteamGame $a, SteamGame $b) => $a->isAchievementsPrivate() <=> $b->isAchievementsPrivate());

        $read = 0;
        foreach (array_chunk(array_slice($due, 0, max(0, $limit)), self::ACHIEVEMENTS_BATCH) as $batch) {
            $byAppId = [];
            foreach ($batch as $game) {
                $byAppId[$game->getAppId()] = $game;
            }
            foreach ($this->client->getAchievements(array_keys($byAppId)) as $appId => $achievements) {
                if ($achievements['unlocked'] === null) {
                    $byAppId[$appId]->setAchievementsPrivate($achievements['total']);
                } else {
                    $byAppId[$appId]->setAchievements($achievements['unlocked'], (int) $achievements['total']);
                }
                ++$read;
            }
            $this->em->flush();
        }

        return [$read, count($due) - $read];
    }
}
