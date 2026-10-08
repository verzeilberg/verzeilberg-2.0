<?php

declare(strict_types=1);

namespace App\Repository\Strava;

use App\Entity\Strava\AccessToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AccessTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccessToken::class);
    }

    public function findLatest(): ?AccessToken
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findValidToken(): ?AccessToken
    {
        return $this->createQueryBuilder('t')
            ->where('t.expiresAt > :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('t.expiresAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save(AccessToken $token, bool $flush = true): void
    {
        $this->getEntityManager()->persist($token);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AccessToken $token, bool $flush = true): void
    {
        $this->getEntityManager()->remove($token);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}