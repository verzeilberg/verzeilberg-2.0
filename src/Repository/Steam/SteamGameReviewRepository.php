<?php

declare(strict_types=1);

namespace App\Repository\Steam;

use App\Entity\Steam\SteamGameReview;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SteamGameReview>
 */
class SteamGameReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SteamGameReview::class);
    }
}
