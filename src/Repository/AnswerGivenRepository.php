<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AnswerGiven;
use App\Entity\CheckList;
use App\Entity\CheckListItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Pagination\PaginationInterface;

class AnswerGivenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnswerGiven::class);
    }

    public function findByCheckList(CheckList $checkList): array
    {
        return $this->createQueryBuilder('ag')
            ->andWhere('ag.checkList = :checkList')
            ->setParameter('checkList', $checkList)
            ->orderBy('ag.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByCheckListItem(CheckListItem $checkListItem): array
    {
        return $this->createQueryBuilder('ag')
            ->andWhere('ag.checkListItem = :checkListItem')
            ->setParameter('checkListItem', $checkListItem)
            ->orderBy('ag.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByCheckListAndItem(CheckList $checkList, CheckListItem $checkListItem): array
    {
        return $this->createQueryBuilder('ag')
            ->andWhere('ag.checkList = :checkList')
            ->andWhere('ag.checkListItem = :checkListItem')
            ->setParameter('checkList', $checkList)
            ->setParameter('checkListItem', $checkListItem)
            ->orderBy('ag.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findById(int $id): ?AnswerGiven
    {
        return $this->createQueryBuilder('ag')
            ->andWhere('ag.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}