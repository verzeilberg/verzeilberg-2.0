<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CheckListFieldType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CheckListFieldTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CheckListFieldType::class);
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('ft')
            ->orderBy('ft.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByName(string $name): ?CheckListFieldType
    {
        return $this->createQueryBuilder('ft')
            ->andWhere('ft.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByFormType(string $formType): ?CheckListFieldType
    {
        return $this->createQueryBuilder('ft')
            ->andWhere('ft.formType = :formType')
            ->setParameter('formType', $formType)
            ->getQuery()
            ->getOneOrNullResult();
    }
}