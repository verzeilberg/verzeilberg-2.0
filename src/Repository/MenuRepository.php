<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Menu;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MenuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Menu::class);
    }

    public function findByName(string $name): ?Menu
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.name = :name')
            ->andWhere('m.deleted = false')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAllActive(): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.deleted = false')
            ->orderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}