<?php

declare(strict_types=1);

namespace App\Controller\Strava;

use App\Repository\Strava\ActivityRepository;
use App\Service\Strava\StravaActivityService;
use App\Service\Strava\StravaOAuthService;
use App\Service\Strava\StravaAccessTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/strava')]
class StravaController extends AbstractController
{
    public function __construct(
        private StravaActivityService $stravaService,
        private StravaOAuthService $oauthService,
        private StravaAccessTokenService $accessTokenService,
        private ActivityRepository $activityRepository
    ) {}

    #[Route('/', name: 'strava_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        $months = $this->stravaService->getMonths();
        $currentMonth = (int) (new \DateTimeImmutable())->format('m');
        $currentYear = (int) (new \DateTimeImmutable())->format('Y');
        $years = $this->activityRepository->getYearsByActivities();

        return $this->render('strava/index.html.twig', [
            'currentMonth' => $currentMonth,
            'months' => $months,
            'currentYear' => $currentYear,
            'years' => $years,
        ]);
    }

    #[Route('/chart-data', name: 'strava_chart_data', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
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

    #[Route('/activity/{id}', name: 'strava_activity_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function detail(int $id): Response
    {
        $activity = $this->activityRepository->find($id);
        
        if (!$activity) {
            $this->addFlash('error', 'Activiteit niet gevonden');
            return $this->redirectToRoute('strava_index');
        }

        $points = $this->stravaService->decodePolyline($activity->getSummaryPolyline() ?? '');
        $rounds = $activity->getRounds();
        $googleMapKey = $this->getParameter('strava.google_maps_key');

        return $this->render('strava/detail.html.twig', [
            'activity' => $activity,
            'points' => $points,
            'rounds' => $rounds,
            'googleMapKey' => $googleMapKey,
        ]);
    }

    #[Route('/auth', name: 'strava_auth', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function auth(): Response
    {
        $authUrl = $this->oauthService->getAuthorizationUrl();
        return $this->redirect($authUrl);
    }

    #[Route('/callback', name: 'strava_callback', methods: ['GET'])]
    public function callback(Request $request): Response
    {
        $code = $request->query->get('code');
        $error = $request->query->get('error');

        if ($error) {
            $this->addFlash('error', 'Strava autorisatie geweigerd: ' . $error);
            return $this->redirectToRoute('strava_index');
        }

        if (!$code) {
            $this->addFlash('error', 'Geen autorisatiecode ontvangen');
            return $this->redirectToRoute('strava_index');
        }

        try {
            $tokenData = $this->oauthService->exchangeToken($code);
            $this->accessTokenService->createAccessToken($tokenData);
            $this->addFlash('success', 'Strava succesvol geautoriseerd!');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Fout bij uitwisselen van token: ' . $e->getMessage());
        }

        return $this->redirectToRoute('strava_index');
    }

    #[Route('/stats', name: 'strava_stats', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
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

        return $this->render('strava/stats.html.twig', [
            'statsByYear' => $statsByYear,
            'totalStats' => $totalStats,
            'years' => $years,
        ]);
    }
}