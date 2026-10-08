<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.email = :email')
            ->andWhere('u.deleted = false')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.status = :status')
            ->andWhere('u.deleted = false')
            ->setParameter('status', User::STATUS_ACTIVE)
            ->orderBy('u.fullName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByRole(string $roleName): array
    {
        return $this->createQueryBuilder('u')
            ->innerJoin('u.roles', 'r')
            ->andWhere('r.name = :roleName')
            ->andWhere('u.deleted = false')
            ->setParameter('roleName', $roleName)
            ->orderBy('u.fullName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findWithDetails(): array
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.details', 'd')
            ->addSelect('d')
            ->andWhere('u.deleted = false')
            ->orderBy('u.fullName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByPasswordResetToken(string $token): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.passwordResetToken = :token')
            ->andWhere('u.deleted = false')
            ->setParameter('token', $token)
            ->getQuery()
            ->getOneOrNullResult();
    }
}