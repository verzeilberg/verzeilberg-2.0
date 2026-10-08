<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Permission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Permission::class);
    }

    public function findByName(string $name): ?Permission
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.name = :name')
            ->andWhere('p.deleted = false')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAllActive(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.deleted = false')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByNames(array $names): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.name IN (:names)')
            ->andWhere('p.deleted = false')
            ->setParameter('names', $names)
            ->getQuery()
            ->getResult();
    }
}