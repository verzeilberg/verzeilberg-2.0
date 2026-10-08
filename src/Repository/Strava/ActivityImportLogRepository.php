<?php

declare(strict_types=1);

namespace App\Repository\Strava;

use App\Entity\Strava\ActivityImportLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ActivityImportLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityImportLog::class);
    }

    public function findLatest(): ?ActivityImportLog
    {
        return $this->createQueryBuilder('l')
            ->orderBy('l.importDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAllOrderByDateDesc(): array
    {
        return $this->createQueryBuilder('l')
            ->orderBy('l.importDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function save(ActivityImportLog $log, bool $flush = true): void
    {
        $this->getEntityManager()->persist($log);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ActivityImportLog $log, bool $flush = true): void
    {
        $this->getEntityManager()->remove($log);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}