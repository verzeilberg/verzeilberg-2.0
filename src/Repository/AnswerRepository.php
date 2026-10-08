<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Answer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AnswerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Answer::class);
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.label', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findById(int $id): ?Answer
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByLabelAndValue(string $label, string $value): ?Answer
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.label = :label')
            ->andWhere('a.value = :value')
            ->setParameter('label', $label)
            ->setParameter('value', $value)
            ->getQuery()
            ->getOneOrNullResult();
    }
}