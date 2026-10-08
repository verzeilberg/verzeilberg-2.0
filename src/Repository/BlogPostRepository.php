<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BlogPost;
use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BlogPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlogPost::class);
    }

    public function findBySlug(string $slug): ?BlogPost
    {
        return $this->createQueryBuilder('bp')
            ->andWhere('bp.slug = :slug')
            ->andWhere('bp.deleted = false')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function find($id, $lockMode = null, $lockVersion = null): ?BlogPost
    {
        return parent::find($id, $lockMode, $lockVersion);
    }

    public function findForAdmin(int $id): ?BlogPost
    {
        return $this->createQueryBuilder('bp')
            ->andWhere('bp.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOnlinePosts(int $limit = 10, int $offset = 0): array
    {
        return $this->createQueryBuilder('bp')
            ->andWhere('bp.status = :status')
            ->andWhere('bp.deleted = false')
            ->andWhere('(bp.dateOnline IS NULL OR bp.dateOnline <= :now)')
            ->andWhere('(bp.dateOffline IS NULL OR bp.dateOffline >= :now)')
            ->setParameter('status', BlogPost::STATUS_ONLINE)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('bp.dateOnline', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function findByCategory(Category $category, int $limit = 10, int $offset = 0): array
    {
        return $this->createQueryBuilder('bp')
            ->innerJoin('bp.categories', 'c')
            ->andWhere('c = :category')
            ->andWhere('bp.status = :status')
            ->andWhere('bp.deleted = false')
            ->andWhere('(bp.dateOnline IS NULL OR bp.dateOnline <= :now)')
            ->andWhere('(bp.dateOffline IS NULL OR bp.dateOffline >= :now)')
            ->setParameter('category', $category)
            ->setParameter('status', BlogPost::STATUS_ONLINE)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('bp.dateOnline', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function search(string $term, int $limit = 10, int $offset = 0): array
    {
        return $this->createQueryBuilder('bp')
            ->andWhere('bp.status = :status')
            ->andWhere('bp.deleted = false')
            ->andWhere('(bp.title LIKE :term OR bp.content LIKE :term OR bp.introText LIKE :term)')
            ->setParameter('status', BlogPost::STATUS_ONLINE)
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('bp.dateOnline', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countOnline(): int
    {
        return (int) $this->createQueryBuilder('bp')
            ->select('COUNT(bp.id)')
            ->andWhere('bp.status = :status')
            ->andWhere('bp.deleted = false')
            ->andWhere('(bp.dateOnline IS NULL OR bp.dateOnline <= :now)')
            ->andWhere('(bp.dateOffline IS NULL OR bp.dateOffline >= :now)')
            ->setParameter('status', BlogPost::STATUS_ONLINE)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByCategory(Category $category): int
    {
        return (int) $this->createQueryBuilder('bp')
            ->select('COUNT(bp.id)')
            ->innerJoin('bp.categories', 'c')
            ->andWhere('c = :category')
            ->andWhere('bp.status = :status')
            ->andWhere('bp.deleted = false')
            ->andWhere('(bp.dateOnline IS NULL OR bp.dateOnline <= :now)')
            ->andWhere('(bp.dateOffline IS NULL OR bp.dateOffline >= :now)')
            ->setParameter('category', $category)
            ->setParameter('status', BlogPost::STATUS_ONLINE)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countSearch(string $term): int
    {
        return (int) $this->createQueryBuilder('bp')
            ->select('COUNT(bp.id)')
            ->andWhere('bp.status = :status')
            ->andWhere('bp.deleted = false')
            ->andWhere('(bp.title LIKE :term OR bp.content LIKE :term OR bp.introText LIKE :term)')
            ->setParameter('status', BlogPost::STATUS_ONLINE)
            ->setParameter('term', '%' . $term . '%')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findDrafts(): array
    {
        return $this->createQueryBuilder('bp')
            ->andWhere('bp.status = :status')
            ->andWhere('bp.deleted = false')
            ->setParameter('status', BlogPost::STATUS_DRAFT)
            ->orderBy('bp.dateCreated', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findScheduled(): array
    {
        return $this->createQueryBuilder('bp')
            ->andWhere('bp.status = :status')
            ->andWhere('bp.deleted = false')
            ->andWhere('bp.dateOnline > :now')
            ->setParameter('status', BlogPost::STATUS_ONLINE)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('bp.dateOnline', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findArchived(): array
    {
        return $this->createQueryBuilder('bp')
            ->andWhere('bp.status = :status')
            ->andWhere('bp.deleted = false')
            ->setParameter('status', BlogPost::STATUS_ARCHIVED)
            ->orderBy('bp.dateOnline', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findLatest(int $limit = 10): array
    {
        return $this->createQueryBuilder('bp')
            ->andWhere('bp.deleted = false')
            ->orderBy('bp.dateCreated', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}