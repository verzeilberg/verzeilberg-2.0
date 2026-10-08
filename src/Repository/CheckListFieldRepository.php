<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CheckList;
use App\Entity\CheckListField;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CheckListFieldRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CheckListField::class);
    }

    public function findByCheckList(CheckList $checkList): array
    {
        return $this->createQueryBuilder('clf')
            ->andWhere('clf.checkList = :checkList')
            ->andWhere('clf.parent IS NULL')
            ->setParameter('checkList', $checkList)
            ->orderBy('clf.order', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByCheckListWithChildren(CheckList $checkList): array
    {
        return $this->createQueryBuilder('clf')
            ->andWhere('clf.checkList = :checkList')
            ->setParameter('checkList', $checkList)
            ->orderBy('clf.order', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findById(int $id): ?CheckListField
    {
        return $this->createQueryBuilder('clf')
            ->andWhere('clf.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByIdWithRelations(int $id): ?CheckListField
    {
        return $this->createQueryBuilder('clf')
            ->leftJoin('clf.fieldType', 'ft')
            ->addSelect('ft')
            ->leftJoin('clf.answers', 'a')
            ->addSelect('a')
            ->leftJoin('clf.children', 'children')
            ->addSelect('children')
            ->leftJoin('children.fieldType', 'cft')
            ->addSelect('cft')
            ->leftJoin('children.answers', 'ca')
            ->addSelect('ca')
            ->andWhere('clf.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findRootFieldsByCheckList(CheckList $checkList): array
    {
        return $this->createQueryBuilder('clf')
            ->andWhere('clf.checkList = :checkList')
            ->andWhere('clf.parent IS NULL')
            ->setParameter('checkList', $checkList)
            ->orderBy('clf.order', 'ASC')
            ->getQuery()
            ->getResult();
    }
}