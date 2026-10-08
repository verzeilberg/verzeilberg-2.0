<?php

declare(strict_types=1);

namespace App\Repository\Handbal;

use App\Entity\Handbal\HandbalSpeler;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<HandbalSpeler>
 */
class HandbalSpelerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HandbalSpeler::class);
    }

    public function findActiveOrderedByName(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.actief = true')
            ->andWhere('s.deleted = false')
            ->orderBy('s.naam', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.deleted = false')
            ->orderBy('s.naam', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBySeasonActive(int $seizoenId): array
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.scores', 'sc')
            ->innerJoin('sc.ronde', 'r')
            ->andWhere('r.seizoen = :seizoenId')
            ->andWhere('s.actief = true')
            ->andWhere('s.deleted = false')
            ->orderBy('s.naam', 'ASC')
            ->setParameter('seizoenId', $seizoenId)
            ->getQuery()
            ->getResult();
    }
}