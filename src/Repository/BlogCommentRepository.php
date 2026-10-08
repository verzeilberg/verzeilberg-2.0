<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BlogComment;
use App\Entity\BlogPost;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BlogCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlogComment::class);
    }

    public function find($id, $lockMode = null, $lockVersion = null): ?BlogComment
    {
        return parent::find($id, $lockMode, $lockVersion);
    }

    public function findApprovedByPost(BlogPost $post): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.blogPost = :post')
            ->andWhere('c.isApproved = true')
            ->andWhere('c.deleted = false')
            ->andWhere('c.parent IS NULL')
            ->setParameter('post', $post)
            ->orderBy('c.dateCreated', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findAllByPost(BlogPost $post): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.blogPost = :post')
            ->andWhere('c.deleted = false')
            ->setParameter('post', $post)
            ->orderBy('c.dateCreated', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingApproval(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isApproved = false')
            ->andWhere('c.deleted = false')
            ->orderBy('c.dateCreated', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countPendingApproval(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.isApproved = false')
            ->andWhere('c.deleted = false')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.author = :user')
            ->andWhere('c.deleted = false')
            ->setParameter('user', $user)
            ->orderBy('c.dateCreated', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findLatest(int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.deleted = false')
            ->leftJoin('c.blogPost', 'bp')
            ->addSelect('bp')
            ->leftJoin('c.author', 'u')
            ->addSelect('u')
            ->orderBy('c.dateCreated', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findPendingApprovalLatest(int $limit = 5): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isApproved = false')
            ->andWhere('c.deleted = false')
            ->leftJoin('c.blogPost', 'bp')
            ->addSelect('bp')
            ->leftJoin('c.author', 'u')
            ->addSelect('u')
            ->orderBy('c.dateCreated', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}