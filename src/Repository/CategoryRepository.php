<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    public function findBySlug(string $slug): ?Category
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAllActive(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.enabled = true')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByModule(?string $module = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.enabled = true')
            ->orderBy('c.name', 'ASC');

        if ($module) {
            $qb->andWhere('c.module = :module')
                ->setParameter('module', $module);
        }

        return $qb->getQuery()->getResult();
    }
}