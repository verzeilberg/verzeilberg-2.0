<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
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

    /** Accounts registered through the site, with a confirmed email address, that an admin hasn't approved yet */
    public function countPendingApproval(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.status = :status')
            ->andWhere('u.emailVerifiedAt IS NOT NULL')
            ->andWhere('u.deleted = false')
            ->setParameter('status', User::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return array<int, int> role id => number of users with that role */
    public function countPerRole(): array
    {
        $rows = $this->createQueryBuilder('u')
            ->select('r.id AS roleId, COUNT(u.id) AS users')
            ->join('u.roles', 'r')
            ->andWhere('u.deleted = false')
            ->groupBy('r.id')
            ->getQuery()
            ->getArrayResult();

        return array_column(array_map(fn(array $row) => [(int) $row['roleId'], (int) $row['users']], $rows), 1, 0);
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

    /** The user directory: active users who made their page public, optionally filtered on name, location or catch line */
    public function createPublicDirectoryQuery(?string $search = null): Query
    {
        $qb = $this->createQueryBuilder('u')
            ->leftJoin('u.details', 'd')
            ->addSelect('d')
            ->andWhere('u.profilePublic = true')
            ->andWhere('u.status = :status')
            ->andWhere('u.deleted = false')
            ->setParameter('status', User::STATUS_ACTIVE)
            ->orderBy('u.fullName', 'ASC');

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('u.fullName LIKE :search OR d.location LIKE :search OR d.catchLine LIKE :search')
                ->setParameter('search', '%' . addcslashes(trim($search), '%_\\') . '%');
        }

        return $qb->getQuery();
    }

    public function findByEmailVerificationToken(string $token): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.emailVerificationToken = :token')
            ->andWhere('u.deleted = false')
            ->setParameter('token', User::hashPasswordResetToken($token))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<User> registrations nobody approved (or whose address was never confirmed) since $before */
    public function findPendingCreatedBefore(\DateTimeImmutable $before): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.status = :status')
            ->andWhere('u.dateCreated < :before')
            ->setParameter('status', User::STATUS_PENDING)
            ->setParameter('before', $before)
            ->getQuery()
            ->getResult();
    }

    public function findByPasswordResetToken(string $token): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.passwordResetToken = :token')
            ->andWhere('u.deleted = false')
            ->setParameter('token', User::hashPasswordResetToken($token))
            ->getQuery()
            ->getOneOrNullResult();
    }
}