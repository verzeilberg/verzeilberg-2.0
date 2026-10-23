<?php

declare(strict_types=1);

namespace App\Repository\Steam;

use App\Entity\Steam\SteamGame;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SteamGame>
 */
class SteamGameRepository extends ServiceEntityRepository
{
    /**
     * Sort options: key => [DQL expression, direction when first chosen, condition for "no value"].
     * Games without a value (never played, achievements unknown or private, ...) always go last; the expression
     * gives them all the same value (NULL or 0), so among themselves they're in name order.
     */
    public const SORTS = [
        'recent' => ['g.lastPlayedAt', 'DESC', 'g.lastPlayedAt IS NULL'],
        'playtime' => ['g.playtimeForever', 'DESC', null],
        'name' => ['g.name', 'ASC', null],
        'achievements' => [
            'CASE WHEN g.achievementsPrivate = false THEN COALESCE(g.achievementsUnlocked, 0) ELSE 0 END',
            'DESC',
            'g.achievementsPrivate = true OR g.achievementsUnlocked IS NULL',
        ],
        // Percentage unlocked; games without (known) achievements have no percentage
        'completion' => [
            'CASE WHEN g.achievementsPrivate = false AND g.achievementsTotal > 0 THEN (COALESCE(g.achievementsUnlocked, 0) * 100.0) / g.achievementsTotal ELSE 0 END',
            'DESC',
            'g.achievementsPrivate = true OR g.achievementsUnlocked IS NULL OR g.achievementsTotal IS NULL OR g.achievementsTotal = 0',
        ],
        'details' => ['g.detailsFetchedAt', 'DESC', 'g.detailsFetchedAt IS NULL'],
        // My review (r: joined in createLibraryQuery; on the public page only published reviews)
        'rating' => ['COALESCE(r.rating, 0)', 'DESC', 'r.id IS NULL'],
        'visibility' => ['g.hidden', 'ASC', null],
    ];

    /** The sort options of the public page (the admin sorts on every column) */
    public const PUBLIC_SORTS = ['recent', 'playtime', 'name', 'achievements', 'rating'];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SteamGame::class);
    }

    /** @return array<int, SteamGame> by app id */
    public function findAllIndexedByAppId(): array
    {
        return $this->createQueryBuilder('g', 'g.appId')->getQuery()->getResult();
    }

    /**
     * The library for the public page or the admin, filtered and sorted (for the paginator).
     *
     * @param array{q?: string, genre?: string, sort?: string, dir?: string, played?: bool, reviewed?: bool} $filters dir: "asc"/"desc", or empty for the sort's own direction
     */
    public function createLibraryQuery(array $filters, bool $includeHidden = false): QueryBuilder
    {
        // The review comes along in the same query; visitors (not $includeHidden) only get published ones
        $qb = $this->createQueryBuilder('g')->addSelect('r');
        if ($includeHidden) {
            $qb->leftJoin('g.review', 'r');
        } else {
            $qb->leftJoin('g.review', 'r', 'WITH', 'r.published = true');
        }
        if ($filters['reviewed'] ?? false) {
            $qb->andWhere('r.id IS NOT NULL');
        }
        if (!$includeHidden) {
            $qb->andWhere('g.hidden = false');
        }
        if (($filters['q'] ?? '') !== '') {
            $qb->andWhere('g.name LIKE :q')->setParameter('q', '%' . $this->escapeLike($filters['q']) . '%');
        }
        if (($filters['genre'] ?? '') !== '') {
            // Genres are a JSON list; the encoded name with its quotes only matches a whole genre
            // (string type: otherwise Doctrine takes the column's JSON type and encodes the pattern again)
            $qb->andWhere('g.genres LIKE :genre')->setParameter('genre', '%' . $this->escapeLike((string) json_encode($filters['genre'])) . '%', Types::STRING);
        }
        if ($filters['played'] ?? false) {
            $qb->andWhere('g.playtimeForever > 0');
        }

        [$expression, $direction, $empty] = self::SORTS[$filters['sort'] ?? 'recent'] ?? self::SORTS['recent'];
        if (in_array(strtolower($filters['dir'] ?? ''), ['asc', 'desc'], true)) {
            $direction = strtoupper((string) $filters['dir']);
        }
        // Games without a value go last, in both directions; then by name, so pages don't shuffle
        if ($empty !== null) {
            $qb->addSelect(sprintf('CASE WHEN %s THEN 1 ELSE 0 END AS HIDDEN emptyLast', $empty))->addOrderBy('emptyLast', 'ASC');
        }
        $qb->addSelect($expression . ' AS HIDDEN sortValue')
            ->addOrderBy('sortValue', $direction)
            ->addOrderBy('g.name', 'ASC')
            ->addOrderBy('g.id', 'ASC');

        return $qb;
    }

    /**
     * Site search: visible games whose name or published review contains the term.
     *
     * @return list<SteamGame>
     */
    public function searchPublic(string $term, int $limit = 100): array
    {
        return $this->createQueryBuilder('g')
            ->addSelect('r')
            ->leftJoin('g.review', 'r', 'WITH', 'r.published = true')
            ->andWhere('g.hidden = false')
            ->andWhere('g.name LIKE :term OR r.body LIKE :term')
            ->setParameter('term', '%' . $this->escapeLike(trim($term)) . '%')
            // Reviewed games first, then by name
            ->addSelect('CASE WHEN r.id IS NULL THEN 1 ELSE 0 END AS HIDDEN unreviewed')
            ->orderBy('unreviewed', 'ASC')
            ->addOrderBy('g.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return list<SteamGame> the most recently played games */
    public function findRecentlyPlayed(int $limit, bool $includeHidden = false): array
    {
        $qb = $this->createQueryBuilder('g')
            ->andWhere('g.lastPlayedAt IS NOT NULL')
            ->orderBy('g.lastPlayedAt', 'DESC')
            ->setMaxResults($limit);
        if (!$includeHidden) {
            $qb->andWhere('g.hidden = false');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array{games: int, played: int, minutes: int, achievements: int, perfect: int}
     */
    public function getStats(bool $includeHidden = false): array
    {
        $qb = $this->createQueryBuilder('g')
            ->select('COUNT(g.id) AS games')
            ->addSelect('COALESCE(SUM(CASE WHEN g.playtimeForever > 0 THEN 1 ELSE 0 END), 0) AS played')
            ->addSelect('COALESCE(SUM(g.playtimeForever), 0) AS minutes')
            ->addSelect('COALESCE(SUM(g.achievementsUnlocked), 0) AS achievements')
            ->addSelect('COALESCE(SUM(CASE WHEN g.achievementsTotal > 0 AND g.achievementsUnlocked = g.achievementsTotal THEN 1 ELSE 0 END), 0) AS perfect');
        if (!$includeHidden) {
            $qb->andWhere('g.hidden = false');
        }

        return array_map('intval', $qb->getQuery()->getSingleResult());
    }

    /** @return list<string> all genres in the library, sorted */
    public function findGenres(bool $includeHidden = false): array
    {
        $qb = $this->createQueryBuilder('g')->select('g.genres');
        if (!$includeHidden) {
            $qb->andWhere('g.hidden = false');
        }
        $genres = array_unique(array_merge([], ...array_column($qb->getQuery()->getArrayResult(), 'genres')));
        sort($genres, SORT_NATURAL | SORT_FLAG_CASE);

        return $genres;
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
