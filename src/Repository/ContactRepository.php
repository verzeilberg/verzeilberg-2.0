<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contact;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

class ContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private PaginatorInterface $paginator)
    {
        parent::__construct($registry, Contact::class);
    }

    public function findAllOrdered(int $page = 1, int $limit = 10): PaginationInterface
    {
        $query = $this->createQueryBuilder('c')
            ->orderBy('c.dateCreated', 'DESC')
            ->getQuery();

        return $this->paginator->paginate($query, $page, $limit);
    }

    public function countUnread(): int
    {
        return $this->count(['readAt' => null]);
    }
}