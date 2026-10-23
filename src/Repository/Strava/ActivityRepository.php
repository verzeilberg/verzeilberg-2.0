<?php

declare(strict_types=1);

namespace App\Repository\Strava;

use App\Entity\Event;
use App\Entity\Strava\Activity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activity::class);
    }

    public function findByActivityId(string $activityId): ?Activity
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.activityId = :activityId')
            ->setParameter('activityId', $activityId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByAthleteId(int $athleteId): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.athleteId = :athleteId')
            ->setParameter('athleteId', $athleteId)
            ->orderBy('a.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findBetweenDates(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.startDate >= :startDate')
            ->andWhere('a.startDate <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('a.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getYearsByActivities(): array
    {
        $results = $this->createQueryBuilder('a')
            ->select('DISTINCT YEAR(a.startDate) as year')
            ->orderBy('year', 'DESC')
            ->getQuery()
            ->getResult();

        return array_column($results, 'year');
    }

    public function getActivitiesForPagination(int $page = 1, int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.startDate', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getStatsByYear(int $year): array
    {
        $activities = $this->createQueryBuilder('a')
            ->where('YEAR(a.startDate) = :year')
            ->setParameter('year', $year)
            ->getQuery()
            ->getResult();

        $totalDistance = 0;
        $totalTime = 0;
        $totalElevation = 0;
        $count = count($activities);
        $byType = [];

        foreach ($activities as $activity) {
            $totalDistance += $activity->getDistance();
            $totalTime += $activity->getMovingTime();
            $totalElevation += $activity->getTotalElevationGain();

            $type = $activity->getType();
            if (!isset($byType[$type])) {
                $byType[$type] = ['count' => 0, 'distance' => 0, 'time' => 0];
            }
            $byType[$type]['count']++;
            $byType[$type]['distance'] += $activity->getDistance();
            $byType[$type]['time'] += $activity->getMovingTime();
        }

        return [
            'count' => $count,
            'totalDistance' => round($totalDistance / 1000, 2),
            'totalTime' => $totalTime,
            'totalElevation' => round($totalElevation, 2),
            'byType' => $byType,
        ];
    }

    /**
     * The race run or ridden on the event's days: an activity marked "Race" in Strava (workout type 1 for a run,
     * 11 for a ride), the longest when there are several. Only races: a training run may start at home.
     */
    public function findRaceForEvent(Event $event): ?Activity
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.startDateLocal >= :from AND a.startDateLocal < :until')
            ->andWhere("(a.type = 'Run' AND a.workoutType = 1) OR (a.type = 'Ride' AND a.workoutType = 11)")
            ->setParameter('from', $event->getEventStartDate()->setTime(0, 0))
            ->setParameter('until', $event->getEventEndDate()->setTime(0, 0)->modify('+1 day'))
            ->orderBy('a.distance', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
