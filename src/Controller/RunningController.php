<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Strava\RunningStats;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** The public running overview per year, from the Strava runs (see RunningStats) */
class RunningController extends AbstractController
{
    #[Route('/running', name: 'app_running', methods: ['GET'])]
    public function index(Request $request, RunningStats $stats): Response
    {
        $years = $stats->years();
        $year = $request->query->getInt('year', $years[0] ?? (int) date('Y'));
        if ($years !== [] && !in_array($year, $years, true)) {
            return $this->redirectToRoute('app_running');
        }

        return $this->render('running/index.html.twig', [
            'years' => $years,
            'year' => $year,
            'totals' => $stats->year($year),
            'kmPerYear' => $stats->kmPerYear(),
            'records' => $stats->personalRecords(),
            'races' => $stats->races($year),
        ]);
    }
}
