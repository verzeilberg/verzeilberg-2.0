<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Event;
use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    public function findAllActive(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.enabled = true')
            ->orderBy('e.eventStartDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findUpcoming(int $limit = 10): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.enabled = true')
            ->andWhere('e.eventStartDate >= :today')
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('e.eventStartDate', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findPast(int $limit = 10): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.enabled = true')
            ->andWhere('e.eventEndDate < :today')
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('e.eventEndDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByCategory(Category $category): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.category = :category')
            ->andWhere('e.enabled = true')
            ->orderBy('e.eventStartDate', 'ASC')
            ->setParameter('category', $category)
            ->getQuery()
            ->getResult();
    }

    public function search(string $search): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.enabled = true')
            ->andWhere('e.title LIKE :search OR e.text LIKE :search OR e.labelText LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('e.eventStartDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findLatest(int $limit = 10): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.enabled = true')
            ->orderBy('e.dateCreated', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}