<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CheckList;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

class CheckListRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private PaginatorInterface $paginator
    ) {
        parent::__construct($registry, CheckList::class);
    }

    public function findAllOrdered(int $page = 1, int $limit = 10): PaginationInterface
    {
        $query = $this->createQueryBuilder('cl')
            ->andWhere('cl.deleted = false')
            ->orderBy('cl.dateCreated', 'DESC')
            ->getQuery();

        return $this->paginator->paginate($query, $page, $limit);
    }

    /**
     * Checklists that can be filled in outside the admin: public ones, plus the logged-in ones for a logged-in user.
     *
     * @return CheckList[]
     */
    public function findOpenForSubmissions(bool $loggedIn): array
    {
        return $this->createQueryBuilder('cl')
            ->andWhere('cl.deleted = false')
            ->andWhere('cl.access IN (:access)')
            ->setParameter('access', $loggedIn ? [CheckList::ACCESS_PUBLIC, CheckList::ACCESS_USER] : [CheckList::ACCESS_PUBLIC])
            ->orderBy('cl.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findAllArchived(int $page = 1, int $limit = 10): PaginationInterface
    {
        $query = $this->createQueryBuilder('cl')
            ->andWhere('cl.deleted = true')
            ->orderBy('cl.dateCreated', 'DESC')
            ->getQuery();

        return $this->paginator->paginate($query, $page, $limit);
    }

    public function findById(int $id): ?CheckList
    {
        return $this->createQueryBuilder('cl')
            ->andWhere('cl.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByIdWithRelations(int $id): ?CheckList
    {
        return $this->createQueryBuilder('cl')
            ->leftJoin('cl.checkListFields', 'clf')
            ->addSelect('clf')
            ->leftJoin('clf.fieldType', 'ft')
            ->addSelect('ft')
            ->leftJoin('cl.checkListItems', 'cli')
            ->addSelect('cli')
            ->andWhere('cl.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}