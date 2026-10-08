<?php

declare(strict_types=1);

namespace App\Repository\Handbal;

use App\Entity\Handbal\HandbalScore;
use App\Entity\Handbal\HandbalSpeler;
use App\Entity\Handbal\HandbalRonde;
use App\Entity\Handbal\HandbalKeeper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HandbalScore>
 */
class HandbalScoreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HandbalScore::class);
    }

    public function findBySpelerAndRonde(HandbalSpeler $speler, HandbalRonde $ronde): ?HandbalScore
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.speler = :speler')
            ->andWhere('s.ronde = :ronde')
            ->andWhere('s.deleted = false')
            ->setParameter('speler', $speler)
            ->setParameter('ronde', $ronde)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByKeeperAndRonde(HandbalKeeper $keeper, HandbalRonde $ronde): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.keeper = :keeper')
            ->andWhere('s.ronde = :ronde')
            ->andWhere('s.deleted = false')
            ->setParameter('keeper', $keeper)
            ->setParameter('ronde', $ronde)
            ->getQuery()
            ->getResult();
    }

    public function findByRonde(HandbalRonde $ronde): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.ronde = :ronde')
            ->andWhere('s.deleted = false')
            ->setParameter('ronde', $ronde)
            ->getQuery()
            ->getResult();
    }

    public function findBySeasonAndSpeler(int $seizoenId, HandbalSpeler $speler): array
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.ronde', 'r')
            ->andWhere('r.seizoen = :seizoenId')
            ->andWhere('s.speler = :speler')
            ->andWhere('s.deleted = false')
            ->setParameter('seizoenId', $seizoenId)
            ->setParameter('speler', $speler)
            ->getQuery()
            ->getResult();
    }

    public function getScoresForStandings(array $spelers, array $rondes): array
    {
        if (empty($spelers) || empty($rondes)) {
            return [];
        }

        $spelerIds = array_map(fn($s) => $s->getId(), $spelers);
        $rondeIds = array_map(fn($r) => $r->getId(), $rondes);

        return $this->createQueryBuilder('s')
            ->andWhere('s.speler IN (:spelerIds)')
            ->andWhere('s.ronde IN (:rondeIds)')
            ->andWhere('s.deleted = false')
            ->setParameter('spelerIds', $spelerIds)
            ->setParameter('rondeIds', $rondeIds)
            ->getQuery()
            ->getResult();
    }

    public function getKeeperScoresForStandings(array $keepers, array $rondes): array
    {
        if (empty($keepers) || empty($rondes)) {
            return [];
        }

        $keeperIds = array_map(fn($k) => $k->getId(), $keepers);
        $rondeIds = array_map(fn($r) => $r->getId(), $rondes);

        return $this->createQueryBuilder('s')
            ->andWhere('s.keeper IN (:keeperIds)')
            ->andWhere('s.ronde IN (:rondeIds)')
            ->andWhere('s.deleted = false')
            ->setParameter('keeperIds', $keeperIds)
            ->setParameter('rondeIds', $rondeIds)
            ->getQuery()
            ->getResult();
    }
}