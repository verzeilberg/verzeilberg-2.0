<?php

declare(strict_types=1);

namespace App\Repository\Handbal;

use App\Entity\Handbal\HandbalRonde;
use App\Entity\Handbal\HandbalSeizoen;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HandbalRonde>
 */
class HandbalRondeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HandbalRonde::class);
    }

    public function findBySeasonOrdered(HandbalSeizoen $seizoen): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.seizoen = :seizoen')
            ->andWhere('r.deleted = false')
            ->orderBy('r.datum', 'ASC')
            ->setParameter('seizoen', $seizoen)
            ->getQuery()
            ->getResult();
    }

    public function findAllOrderedByDateDesc(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.deleted = false')
            ->orderBy('r.datum', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAllOrderedByDateAsc(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.deleted = false')
            ->orderBy('r.datum', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByDate(\DateTimeInterface $datum): ?HandbalRonde
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.datum = :datum')
            ->andWhere('r.deleted = false')
            ->setParameter('datum', $datum)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findBySeasonAndDate(HandbalSeizoen $seizoen, \DateTimeInterface $datum): ?HandbalRonde
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.seizoen = :seizoen')
            ->andWhere('r.datum = :datum')
            ->andWhere('r.deleted = false')
            ->setParameter('seizoen', $seizoen)
            ->setParameter('datum', $datum)
            ->getQuery()
            ->getOneOrNullResult();
    }
}