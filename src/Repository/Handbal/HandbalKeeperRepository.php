<?php

declare(strict_types=1);

namespace App\Repository\Handbal;

use App\Entity\Handbal\HandbalKeeper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HandbalKeeper>
 */
class HandbalKeeperRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HandbalKeeper::class);
    }

    public function findActiveOrderedByName(): array
    {
        return $this->createQueryBuilder('k')
            ->andWhere('k.actief = true')
            ->andWhere('k.deleted = false')
            ->orderBy('k.naam', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('k')
            ->andWhere('k.deleted = false')
            ->orderBy('k.naam', 'ASC')
            ->getQuery()
            ->getResult();
    }
}