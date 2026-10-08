<?php

declare(strict_types=1);

namespace App\Repository\Steam;

use App\Entity\Steam\SteamAccount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SteamAccount>
 */
class SteamAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SteamAccount::class);
    }

    public function findBySteamId(string $steamId): ?SteamAccount
    {
        return $this->findOneBy(['steamId' => $steamId]);
    }
}
