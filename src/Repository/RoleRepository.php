<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Role;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Role::class);
    }

    public function findByName(string $name): ?Role
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.name = :name')
            ->andWhere('r.deleted = false')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAllActive(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.deleted = false')
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findRootRoles(): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.parentRoles', 'pr')
            ->andWhere('pr IS NULL')
            ->andWhere('r.deleted = false')
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findWithPermissions(): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.permissions', 'p')
            ->addSelect('p')
            ->andWhere('r.deleted = false')
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}