<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EmailAccount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EmailAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailAccount::class);
    }

    public function findAllEnabled(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.enabled = true')
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findWithMessages(): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.messages', 'm')
            ->addSelect('m')
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}