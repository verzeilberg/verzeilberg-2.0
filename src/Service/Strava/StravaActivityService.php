<?php

declare(strict_types=1);

namespace App\Service\Strava;

use App\Entity\Strava\Activity;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Strava\ActivityImportLog;
use App\Entity\Strava\Round;
use App\Repository\Strava\ActivityRepository;
use App\Repository\Strava\ActivityImportLogRepository;
use App\Repository\Strava\RoundRepository;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

class StravaActivityService
{
    private int $athleteId;
    private int $activitiesPerPage;
    private ActivityRepository $activityRepository;
    private RoundRepository $roundRepository;
    private ActivityImportLogRepository $importLogRepository;
    private HttpClientInterface $httpClient;
    private StravaAccessTokenService $accessTokenService;

    public function __construct(
        ActivityRepository $activityRepository,
        RoundRepository $roundRepository,
        ActivityImportLogRepository $importLogRepository,
        HttpClientInterface $httpClient,
        StravaAccessTokenService $accessTokenService,
        private EntityManagerInterface $em,
        int $athleteId,
        int $activitiesPerPage = 30
    ) {
        $this->activityRepository = $activityRepository;
        $this->roundRepository = $roundRepository;
        $this->importLogRepository = $importLogRepository;
        $this->httpClient = $httpClient;
        $this->accessTokenService = $accessTokenService;
        $this->athleteId = $athleteId;
        $this->activitiesPerPage = $activitiesPerPage;
    }

    private function getAccessToken(): ?string
    {
        return $this->accessTokenService->getAccessToken();
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function getActivity(int $activityId, bool $includeAllEfforts = true): ?array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return null;
        }

        $response = $this->httpClient->request('GET', "https://www.strava.com/api/v3/activities/{$activityId}", [
            'query' => ['include_all_efforts' => $includeAllEfforts ? 'true' : 'false'],
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ]);

        return $response->toArray();
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function getAthleteActivities(?int $before = null, ?int $after = null, int $page = 1, ?int $perPage = null): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return [];
        }

        $perPage = $perPage ?? $this->activitiesPerPage;

        $query = [
            'page' => $page,
            'per_page' => $perPage,
        ];

        if ($before) {
            $query['before'] = $before;
        }
        if ($after) {
            $query['after'] = $after;
        }

        $response = $this->httpClient->request('GET', 'https://www.strava.com/api/v3/athlete/activities', [
            'query' => $query,
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ]);

        return $response->toArray();
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function getAthleteStats(): ?array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return null;
        }

        $response = $this->httpClient->request('GET', "https://www.strava.com/api/v3/athletes/{$this->athleteId}/stats", [
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ]);

        return $response->toArray();
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function getActivityLaps(int $activityId): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return [];
        }

        $response = $this->httpClient->request('GET', "https://www.strava.com/api/v3/activities/{$activityId}/laps", [
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ]);

        return $response->toArray();
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function getActivityZones(int $activityId): ?array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return null;
        }

        $response = $this->httpClient->request('GET', "https://www.strava.com/api/v3/activities/{$activityId}/zones", [
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ]);

        return $response->toArray();
    }

    /**
     * Import all new activities from Strava
     */
    public function importNewActivities(): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            throw new \RuntimeException('No valid access token available');
        }

        $totalPages = 10; // Fallback: fetch up to 10 pages (300 activities)
        $newActivities = [];

        $importLog = new ActivityImportLog();
        $this->importLogRepository->save($importLog);

        for ($page = 1; $page <= $totalPages; $page++) {
            $activities = $this->getAthleteActivities(page: $page, perPage: $this->activitiesPerPage);

            if (empty($activities)) {
                break; // No more activities
            }

            foreach ($activities as $activityData) {
                $existing = $this->activityRepository->findByActivityId((string) $activityData['id']);
                if (!$existing) {
                    $activity = $this->createActivityFromApiData($activityData, $importLog);
                    $this->em->persist($activity);
                    $newActivities[] = $activity;
                }
            }
        }

        if (!empty($newActivities)) {
            $this->em->flush();
        }

        return $newActivities;
    }

    public function createActivityFromApiData(array $activityData, ActivityImportLog $importLog): Activity
    {
        $activity = new Activity();
        $this->setActivityData($activityData, $activity);
        $activity->setActivityImportLog($importLog);
        $this->setRoundsFromApiData($activityData, $activity);

        return $activity;
    }

    public function updateActivityFromApiData(Activity $activity, array $activityData): Activity
    {
        $this->setActivityData($activityData, $activity);
        $activity->setDateChanged(new \DateTimeImmutable());

        // Remove existing rounds
        foreach ($activity->getRounds() as $round) {
            $this->roundRepository->remove($round, false);
        }
        $activity->getRounds()->clear();

        $this->setRoundsFromApiData($activityData, $activity);

        return $activity;
    }

    private function setActivityData(array $activityData, Activity $activity): void
    {
        $activity->setActivityId((string) $activityData['id']);
        $activity->setAthleteId((int) ($activityData['athlete']['id'] ?? $this->athleteId));
        $activity->setName($activityData['name'] ?? '');
        $activity->setDistance((float) ($activityData['distance'] ?? 0));
        $activity->setMovingTime((int) ($activityData['moving_time'] ?? 0));
        $activity->setElapsedTime((int) ($activityData['elapsed_time'] ?? 0));
        $activity->setTotalElevationGain((float) ($activityData['total_elevation_gain'] ?? 0));
        $activity->setType($activityData['type'] ?? '');
        $activity->setWorkoutType($activityData['workout_type'] ?? null);

        if (isset($activityData['start_date'])) {
            $activity->setStartDate(new \DateTimeImmutable($activityData['start_date']));
        }
        if (isset($activityData['start_date_local'])) {
            $activity->setStartDateLocal(new \DateTimeImmutable($activityData['start_date_local']));
        }
        $activity->setTimezone($activityData['timezone'] ?? 'UTC');

        if (isset($activityData['start_latlng']) && is_array($activityData['start_latlng']) && count($activityData['start_latlng']) >= 2) {
            $activity->setStartLat((float) $activityData['start_latlng'][0]);
            $activity->setStartLng((float) $activityData['start_latlng'][1]);
        }

        if (isset($activityData['end_latlng']) && is_array($activityData['end_latlng']) && count($activityData['end_latlng']) >= 2) {
            $activity->setEndLat((float) $activityData['end_latlng'][0]);
            $activity->setEndLng((float) $activityData['end_latlng'][1]);
        }

        $activity->setSummaryPolyline($activityData['map']['polyline'] ?? null);
        $activity->setAverageSpeed((float) ($activityData['average_speed'] ?? 0));
        
        if (isset($activityData['average_speed']) && $activityData['average_speed'] > 0) {
            $paceSeconds = 1000 / $activityData['average_speed'];
            $paceTime = gmdate('H:i:s', (int) $paceSeconds);
            $activity->setAverageSpeedTime(new \DateTimeImmutable($paceTime));
        }

        $activity->setMaxSpeed((float) ($activityData['max_speed'] ?? 0));
        $activity->setAverageHeartrate($activityData['average_heartrate'] ?? null);
        $activity->setMaxHeartrate($activityData['max_heartrate'] ?? null);
        $activity->setElevHigh($activityData['elev_high'] ?? null);
        $activity->setElevLow($activityData['elev_low'] ?? null);
        $activity->setDescription($activityData['description'] ?? null);
    }

    private function setRoundsFromApiData(array $activityData, Activity $activity): void
    {
        if (!isset($activityData['splits_metric']) || !is_array($activityData['splits_metric'])) {
            return;
        }

        foreach ($activityData['splits_metric'] as $index => $split) {
            $round = new Round();
            $round->setDistance((float) ($split['distance'] ?? 0));
            $round->setElapsedTime((int) ($split['elapsed_time'] ?? 0));
            $round->setElevationDifference($split['elevation_difference'] ?? null);
            $round->setMovingTime((int) ($split['moving_time'] ?? 0));
            $round->setSplit((int) ($split['split'] ?? $index + 1));
            $round->setAverageSpeed($split['average_speed'] ?? null);
            $round->setAverageHeartrate($split['average_heartrate'] ?? null);
            $round->setPaceZone($split['pace_zone'] ?? null);
            $round->setActivity($activity);
            
            $this->roundRepository->save($round, false);
        }
    }

    public function decodePolyline(string $polyline): array
    {
        $points = $this->decodePolylinePoints($polyline);
        return array_map(fn($p) => ['lat' => $p[0], 'lng' => $p[1]], $points);
    }

    private function decodePolylinePoints(string $encoded): array
    {
        $len = strlen($encoded);
        $index = 0;
        $lat = 0;
        $lng = 0;
        $array = [];

        while ($index < $len) {
            $b = 0;
            $shift = 0;
            $result = 0;

            do {
                $b = ord($encoded[$index++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($b >= 0x20);

            $dlat = ($result & 1) ? ~($result >> 1) : ($result >> 1);
            $lat += $dlat;

            $shift = 0;
            $result = 0;

            do {
                $b = ord($encoded[$index++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($b >= 0x20);

            $dlng = ($result & 1) ? ~($result >> 1) : ($result >> 1);
            $lng += $dlng;

            $array[] = [$lat * 1e-5, $lng * 1e-5];
        }

        return $array;
    }

    public function getMonths(): array
    {
        return [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maart',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Augustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'December',
        ];
    }

    public function getStartAndEndDateByMonthAndYear(?int $year = null, ?int $month = null): array
    {
        if ($year === null) {
            $year = (int) (new \DateTimeImmutable())->format('Y');
            $startDate = new \DateTimeImmutable($year . '-' . $month . '-01');
            $endDate = clone $startDate;
        } elseif ($month === null) {
            $startDate = new \DateTimeImmutable($year . '-01-01');
            $endDate = new \DateTimeImmutable($year . '-12-31');
        } else {
            $startDate = new \DateTimeImmutable($year . '-' . $month . '-01');
            $endDate = clone $startDate;
        }

        $startDate = $startDate->modify('first day of this month');
        $endDate = $endDate->modify('last day of this month');

        return [
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];
    }

    public function getActivitiesByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->activityRepository->findBetweenDates($startDate, $endDate);
    }

    public function getYearsWithActivities(): array
    {
        return $this->activityRepository->getYearsByActivities();
    }

    public function getActivityById(int $id): ?Activity
    {
        return $this->activityRepository->find($id);
    }

    public function getActivitiesForPagination(int $page = 1, int $limit = 10): array
    {
        return $this->activityRepository->getActivitiesForPagination($page, $limit);
    }

    public function countAllActivities(): int
    {
        return $this->activityRepository->countAll();
    }
}