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
    /** Sort options of the library: key => [field, direction] */
    public const SORTS = [
        'recent' => ['g.lastPlayedAt', 'DESC'],
        'playtime' => ['g.playtimeForever', 'DESC'],
        'name' => ['g.name', 'ASC'],
        'achievements' => ['g.achievementsUnlocked', 'DESC'],
    ];

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
     * @param array{q?: string, genre?: string, sort?: string, played?: bool} $filters
     */
    public function createLibraryQuery(array $filters, bool $includeHidden = false): QueryBuilder
    {
        $qb = $this->createQueryBuilder('g');
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

        [$field, $direction] = self::SORTS[$filters['sort'] ?? 'recent'] ?? self::SORTS['recent'];
        // Games without a value (never played, achievements unknown) go last
        $qb->addSelect(sprintf('CASE WHEN %s IS NULL THEN 1 ELSE 0 END AS HIDDEN emptyLast', $field))
            ->orderBy('emptyLast', 'ASC')
            ->addOrderBy($field, $direction)
            ->addOrderBy('g.name', 'ASC');

        return $qb;
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
