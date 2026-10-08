<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BlogImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BlogImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlogImage::class);
    }

    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.imageType = :type')
            ->andWhere('i.deleted = false')
            ->setParameter('type', $type)
            ->orderBy('i.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findAllSorted(): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.deleted = false')
            ->orderBy('i.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}