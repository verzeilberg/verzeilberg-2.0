<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BlogFile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BlogFileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlogFile::class);
    }

    public function findPublic(): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.isPublic = true')
            ->andWhere('f.deleted = false')
            ->orderBy('f.dateCreated', 'DESC')
            ->getQuery()
            ->getResult();
    }
}