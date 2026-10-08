<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserDetails;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserDetailsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserDetails::class);
    }

    public function findByUser(User $user): ?UserDetails
    {
        return $this->createQueryBuilder('ud')
            ->andWhere('ud.user = :user')
            ->andWhere('ud.deleted = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByEmail(string $email): ?UserDetails
    {
        return $this->createQueryBuilder('ud')
            ->innerJoin('ud.user', 'u')
            ->andWhere('u.email = :email')
            ->andWhere('ud.deleted = false')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByLocation(string $location): array
    {
        return $this->createQueryBuilder('ud')
            ->andWhere('ud.location = :location')
            ->andWhere('ud.deleted = false')
            ->setParameter('location', $location)
            ->orderBy('ud.lastName', 'ASC')
            ->addOrderBy('ud.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findWithSocialLinks(): array
    {
        return $this->createQueryBuilder('ud')
            ->andWhere('ud.socialLinks IS NOT NULL')
            ->andWhere('ud.deleted = false')
            ->getQuery()
            ->getResult();
    }

    public function createOrUpdateForUser(User $user, array $data): UserDetails
    {
        $details = $this->findByUser($user);
        if ($details === null) {
            $details = new UserDetails();
            $details->setUser($user);
        }

        foreach ($data as $key => $value) {
            $method = 'set' . ucfirst($key);
            if (method_exists($details, $method)) {
                $details->$method($value);
            }
        }

        $this->getEntityManager()->persist($details);
        return $details;
    }
}