<?php

declare(strict_types=1);

namespace App\Service\Strava;

use App\Entity\Event;
use App\Entity\Strava\Activity;
use App\Repository\EventRepository;
use App\Repository\Strava\ActivityRepository;

/**
 * The public running overview (/running), from the Strava runs: totals per year and month, personal records per
 * distance and the races. Only numbers and race names: no routes, and no names of training runs.
 */
class RunningStats
{
    /** Distances for personal records: label => [shortest, longest] in meters a run may measure (GPS is never exact) */
    public const RECORD_DISTANCES = [
        '5 km' => [4900, 5300],
        '10 km' => [9850, 10600],
        '15 km' => [14800, 15500],
        '10 Engelse mijl' => [15950, 16600],
        'Halve marathon' => [20950, 21700],
        'Marathon' => [41900, 43000],
    ];

    /** Strava's workout type for a race run */
    private const RACE = 1;

    /** @var list<Activity>|null all runs, oldest first, loaded once */
    private ?array $runs = null;

    /** @var array<string, Event>|null events by day, loaded once */
    private ?array $eventsByDay = null;

    public function __construct(
        private ActivityRepository $activityRepository,
        private EventRepository $eventRepository,
    ) {
    }

    /** @return list<int> years with runs, newest first */
    public function years(): array
    {
        $years = array_values(array_unique(array_map(fn (Activity $run) => (int) $run->getStartDateLocal()->format('Y'), $this->runs())));
        rsort($years);

        return $years;
    }

    /** @return array{runs: int, km: float, seconds: int, elevation: float, races: int, kmPerMonth: list<float>} */
    public function year(int $year): array
    {
        $totals = ['runs' => 0, 'km' => 0.0, 'seconds' => 0, 'elevation' => 0.0, 'races' => 0, 'kmPerMonth' => array_fill(0, 12, 0.0)];
        foreach ($this->runsIn($year) as $run) {
            ++$totals['runs'];
            $totals['km'] += $run->getDistance() / 1000;
            $totals['seconds'] += $run->getMovingTime();
            $totals['elevation'] += $run->getTotalElevationGain();
            $totals['races'] += $run->getWorkoutType() === self::RACE ? 1 : 0;
            $totals['kmPerMonth'][(int) $run->getStartDateLocal()->format('n') - 1] += $run->getDistance() / 1000;
        }
        $totals['km'] = round($totals['km'], 1);
        $totals['kmPerMonth'] = array_map(fn (float $km) => round($km, 1), $totals['kmPerMonth']);

        return $totals;
    }

    /** @return array<int, float> year => km, oldest first */
    public function kmPerYear(): array
    {
        $km = [];
        foreach ($this->runs() as $run) {
            $year = (int) $run->getStartDateLocal()->format('Y');
            $km[$year] = ($km[$year] ?? 0) + $run->getDistance() / 1000;
        }

        return array_map(fn (float $total) => round($total, 1), $km);
    }

    /**
     * The fastest run per distance, over all years.
     *
     * @return list<array{distance: string, seconds: int, km: float, date: \DateTimeImmutable, race: bool, event: ?Event}>
     */
    public function personalRecords(): array
    {
        $best = [];
        foreach ($this->runs() as $run) {
            foreach (self::RECORD_DISTANCES as $label => [$min, $max]) {
                if ($run->getDistance() >= $min && $run->getDistance() <= $max
                    && (!isset($best[$label]) || $this->pace($run) < $this->pace($best[$label]))) {
                    $best[$label] = $run;
                }
            }
        }

        $records = [];
        foreach (array_keys(self::RECORD_DISTANCES) as $label) {
            if (isset($best[$label])) {
                $run = $best[$label];
                $records[] = [
                    'distance' => $label,
                    'seconds' => $run->getMovingTime(),
                    'km' => round($run->getDistance() / 1000, 2),
                    'date' => $run->getStartDateLocal(),
                    'race' => $run->getWorkoutType() === self::RACE,
                    'event' => $run->getWorkoutType() === self::RACE ? $this->eventOn($run->getStartDateLocal()) : null,
                ];
            }
        }

        return $records;
    }

    /** @return list<array{name: string, date: \DateTimeImmutable, km: float, seconds: int, event: ?Event}> the year's races, oldest first */
    public function races(int $year): array
    {
        $races = [];
        foreach ($this->runsIn($year) as $run) {
            if ($run->getWorkoutType() === self::RACE) {
                $event = $this->eventOn($run->getStartDateLocal());
                $races[] = [
                    'name' => $event?->getTitle() ?? $run->getName(),
                    'date' => $run->getStartDateLocal(),
                    'km' => round($run->getDistance() / 1000, 2),
                    'seconds' => $run->getMovingTime(),
                    'event' => $event,
                ];
            }
        }

        return $races;
    }

    /** @return list<Activity> */
    private function runsIn(int $year): array
    {
        return array_values(array_filter($this->runs(), fn (Activity $run) => (int) $run->getStartDateLocal()->format('Y') === $year));
    }

    /** @return list<Activity> */
    private function runs(): array
    {
        return $this->runs ??= $this->activityRepository->findBy(['type' => 'Run'], ['startDateLocal' => 'ASC']);
    }

    /** Seconds per km: compares runs of slightly different length fairly */
    private function pace(Activity $run): float
    {
        return $run->getMovingTime() / ($run->getDistance() / 1000);
    }

    private function eventOn(\DateTimeImmutable $day): ?Event
    {
        if ($this->eventsByDay === null) {
            $this->eventsByDay = [];
            foreach ($this->eventRepository->findAllActive() as $event) {
                for ($date = $event->getEventStartDate(); $date <= $event->getEventEndDate(); $date = $date->modify('+1 day')) {
                    $this->eventsByDay[$date->format('Y-m-d')] ??= $event;
                }
            }
        }

        return $this->eventsByDay[$day->format('Y-m-d')] ?? null;
    }
}
