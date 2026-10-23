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

    /**
     * The newest comments of a user that everyone can see, on posts that are online (for their page).
     *
     * @return list<BlogComment>
     */
    public function findRecentPublishedBy(User $author, int $limit = 5): array
    {
        $now = new \DateTimeImmutable();
        $comments = $this->createQueryBuilder('c')
            ->innerJoin('c.blogPost', 'bp')
            ->addSelect('bp')
            ->andWhere('c.author = :author')
            ->andWhere('c.isApproved = true')
            ->andWhere('c.deleted = false')
            ->setParameter('author', $author)
            ->orderBy('c.dateCreated', 'DESC')
            // A few extra: a reply under a comment that isn't published drops out below
            ->setMaxResults($limit * 3)
            ->getQuery()
            ->getResult();

        $visible = array_filter($comments, fn (BlogComment $comment) => $comment->isPublished() && $comment->getBlogPost()->isVisibleOn($now));

        return array_slice(array_values($visible), 0, $limit);
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