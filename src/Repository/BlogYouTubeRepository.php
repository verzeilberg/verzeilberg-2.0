<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BlogYouTube;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BlogYouTubeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlogYouTube::class);
    }

    public function findAllSorted(): array
    {
        return $this->createQueryBuilder('y')
            ->andWhere('y.deleted = false')
            ->orderBy('y.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}