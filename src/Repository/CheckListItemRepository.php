<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CheckList;
use App\Entity\CheckListItem;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

class CheckListItemRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private PaginatorInterface $paginator
    ) {
        parent::__construct($registry, CheckListItem::class);
    }

    public function findByCheckList(CheckList $checkList): array
    {
        return $this->createQueryBuilder('cli')
            ->andWhere('cli.checkList = :checkList')
            ->setParameter('checkList', $checkList)
            ->orderBy('cli.dateCreated', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByCheckListPaginated(CheckList $checkList, int $page = 1, int $limit = 10): PaginationInterface
    {
        $query = $this->createQueryBuilder('cli')
            ->andWhere('cli.checkList = :checkList')
            ->setParameter('checkList', $checkList)
            ->orderBy('cli.dateCreated', 'ASC')
            ->getQuery();

        return $this->paginator->paginate($query, $page, $limit);
    }

    /**
     * All items of a checklist, in the same order as the paginated lists (used by the export).
     *
     * @return CheckListItem[]
     */
    public function findAllByCheckList(CheckList $checkList): array
    {
        return $this->createQueryBuilder('cli')
            ->andWhere('cli.checkList = :checkList')
            ->setParameter('checkList', $checkList)
            ->orderBy('cli.dateCreated', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Items a user submitted for a checklist ("My submissions"), newest first.
     *
     * @return CheckListItem[]
     */
    public function findByCheckListAndCreator(CheckList $checkList, User $user): array
    {
        return $this->createQueryBuilder('cli')
            ->andWhere('cli.checkList = :checkList')
            ->andWhere('cli.createdBy = :user')
            ->setParameter('checkList', $checkList)
            ->setParameter('user', $user)
            ->orderBy('cli.dateCreated', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findById(int $id): ?CheckListItem
    {
        return $this->createQueryBuilder('cli')
            ->andWhere('cli.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByIdWithAnswers(int $id): ?CheckListItem
    {
        return $this->createQueryBuilder('cli')
            ->leftJoin('cli.answersGiven', 'ag')
            ->addSelect('ag')
            ->leftJoin('ag.checkListField', 'clf')
            ->addSelect('clf')
            ->leftJoin('clf.fieldType', 'ft')
            ->addSelect('ft')
            ->leftJoin('ag.answerValue', 'a')
            ->addSelect('a')
            ->andWhere('cli.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}