<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\Strava\ActivityRepository;
use App\Repository\Strava\ActivityImportLogRepository;
use App\Service\Strava\StravaActivityService;
use App\Service\Strava\StravaOAuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/strava')]
class StravaController extends AbstractController
{
    public function __construct(
        private StravaActivityService $stravaService,
        private StravaOAuthService $oauthService,
        private ActivityRepository $activityRepository,
        private ActivityImportLogRepository $importLogRepository
    ) {}

    #[Route('/', name: 'admin_strava_index', methods: ['GET'])]
    public function index(): Response
    {
        $months = $this->stravaService->getMonths();
        $currentMonth = (int) (new \DateTimeImmutable())->format('m');
        $currentYear = (int) (new \DateTimeImmutable())->format('Y');
        $years = $this->activityRepository->getYearsByActivities();

        return $this->render('admin/strava/index.html.twig', [
            'currentMonth' => $currentMonth,
            'months' => $months,
            'currentYear' => $currentYear,
            'years' => $years,
        ]);
    }

    #[Route('/chart-data', name: 'admin_strava_chart_data', methods: ['POST'])]
    public function chartData(Request $request): JsonResponse
    {
        $year = $request->request->getInt('year');
        $month = $request->request->getInt('month');

        $dates = $this->stravaService->getStartAndEndDateByMonthAndYear($year, $month);
        $activities = $this->stravaService->getActivitiesByDateRange($dates['startDate'], $dates['endDate']);

        $data = [];
        foreach ($activities as $activity) {
            $data[] = [
                'id' => $activity->getId(),
                'name' => $activity->getName(),
                'type' => $activity->getType(),
                'distance' => $activity->getDistanceInKm(),
                'moving_time' => $activity->getMovingTime(),
                'start_date' => $activity->getStartDateLocal()->format('Y-m-d H:i:s'),
                'total_elevation_gain' => $activity->getTotalElevationGain(),
                'average_speed' => $activity->getAverageSpeed(),
                'average_heartrate' => $activity->getAverageHeartrate(),
            ];
        }

        return $this->json([
            'success' => true,
            'activities' => $data,
        ]);
    }

    #[Route('/activity/{id}', name: 'admin_strava_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(int $id): Response
    {
        $activity = $this->activityRepository->find($id);
        
        if (!$activity) {
            $this->addFlash('error', 'Activiteit niet gevonden');
            return $this->redirectToRoute('admin_strava_index');
        }

        $points = $this->stravaService->decodePolyline($activity->getSummaryPolyline() ?? '');
        $rounds = $activity->getRounds();
        $googleMapKey = $this->getParameter('strava.google_maps_key');

        return $this->render('admin/strava/detail.html.twig', [
            'activity' => $activity,
            'points' => $points,
            'rounds' => $rounds,
            'googleMapKey' => $googleMapKey ?? '',
        ]);
    }

    #[Route('/import', name: 'admin_strava_import', methods: ['GET', 'POST'])]
    public function import(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            try {
                $newActivities = $this->stravaService->importNewActivities();
                
                // Return JSON for AJAX requests
                if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
                    return $this->json([
                        'success' => true,
                        'count' => count($newActivities),
                        'activities' => array_map(fn($a) => [
                            'name' => $a->getName(),
                            'distance' => $a->getDistanceInKm(),
                            'type' => $a->getType(),
                            'start_date' => $a->getStartDateLocal()->format('Y-m-d H:i:s'),
                        ], $newActivities),
                    ]);
                }
                
                $this->addFlash('success', sprintf('%d nieuwe activiteiten geïmporteerd', count($newActivities)));
            } catch (\Exception $e) {
                if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
                    return $this->json([
                        'success' => false,
                        'message' => 'Fout bij importeren: ' . $e->getMessage(),
                    ]);
                }
                $this->addFlash('error', 'Fout bij importeren: ' . $e->getMessage());
            }
            return $this->redirectToRoute('admin_strava_index');
        }

        $recentImports = $this->importLogRepository->findAllOrderByDateDesc();
        
        return $this->render('admin/strava/import.html.twig', [
            'recentImports' => $recentImports,
        ]);
    }

    #[Route('/auth', name: 'admin_strava_auth', methods: ['GET'])]
    public function auth(): Response
    {
        $authUrl = $this->oauthService->getAuthorizationUrl();
        return $this->redirect($authUrl);
    }

    #[Route('/stats', name: 'admin_strava_stats', methods: ['GET'])]
    public function stats(): Response
    {
        $years = $this->activityRepository->getYearsByActivities();
        $statsByYear = [];

        foreach ($years as $year) {
            $statsByYear[$year] = $this->activityRepository->getStatsByYear($year);
        }

        $totalStats = [
            'count' => 0,
            'totalDistance' => 0,
            'totalTime' => 0,
            'totalElevation' => 0,
        ];

        foreach ($statsByYear as $yearStats) {
            $totalStats['count'] += $yearStats['count'];
            $totalStats['totalDistance'] += $yearStats['totalDistance'];
            $totalStats['totalTime'] += $yearStats['totalTime'];
            $totalStats['totalElevation'] += $yearStats['totalElevation'];
        }

        return $this->render('admin/strava/stats.html.twig', [
            'statsByYear' => $statsByYear,
            'totalStats' => $totalStats,
            'years' => $years,
        ]);
    }
}