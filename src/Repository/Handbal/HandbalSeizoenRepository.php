<?php

declare(strict_types=1);

namespace App\Repository\Handbal;

use App\Entity\Handbal\HandbalSeizoen;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HandbalSeizoen>
 */
class HandbalSeizoenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HandbalSeizoen::class);
    }

    public function findActiveOrderedByStartDateDesc(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.actief = true')
            ->andWhere('s.deleted = false')
            ->orderBy('s.startDatum', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAllOrderedByStartDateDesc(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.deleted = false')
            ->orderBy('s.startDatum', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findLatest(): ?HandbalSeizoen
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.deleted = false')
            ->orderBy('s.startDatum', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByCurrentDate(?\DateTimeInterface $date = null): ?HandbalSeizoen
    {
        if ($date === null) {
            $date = new \DateTime();
        }
        $dateString = $date->format('Y-m-d');

        $seizoen = $this->createQueryBuilder('s')
            ->andWhere('s.deleted = false')
            ->andWhere('s.startDatum <= :date')
            ->andWhere('s.eindDatum >= :date')
            ->setParameter('date', $dateString)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($seizoen) {
            return $seizoen;
        }

        return $this->findLatest();
    }

    public function findOverlapping(\DateTimeInterface $startDatum, \DateTimeInterface $eindDatum, ?int $excludeId = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.deleted = false')
            ->andWhere('s.startDatum <= :eindDatum')
            ->andWhere('s.eindDatum >= :startDatum')
            ->setParameter('startDatum', $startDatum)
            ->setParameter('eindDatum', $eindDatum);

        if ($excludeId !== null) {
            $qb->andWhere('s.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getResult();
    }
}