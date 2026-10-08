<?php

declare(strict_types=1);

namespace App\Repository\Strava;

use App\Entity\Strava\Round;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RoundRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Round::class);
    }

    public function findByActivity(\App\Entity\Strava\Activity $activity): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.activity = :activity')
            ->setParameter('activity', $activity)
            ->orderBy('r.split', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(Round $round, bool $flush = true): void
    {
        $this->getEntityManager()->persist($round);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Round $round, bool $flush = true): void
    {
        $this->getEntityManager()->remove($round);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}