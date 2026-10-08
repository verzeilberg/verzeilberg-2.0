<?php

declare(strict_types=1);

namespace App\Controller\Handbal;

use App\Entity\Handbal\HandbalScore;
use App\Entity\Handbal\HandbalSpeler;
use App\Entity\Handbal\HandbalKeeper;
use App\Entity\Handbal\HandbalRonde;
use App\Entity\Handbal\HandbalSeizoen;
use App\Repository\Handbal\HandbalScoreRepository;
use App\Repository\Handbal\HandbalSpelerRepository;
use App\Repository\Handbal\HandbalKeeperRepository;
use App\Repository\Handbal\HandbalSeizoenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/handbal')]
class HandbalPublicController extends AbstractController
{
    public function __construct(
        private HandbalSpelerRepository $spelerRepository,
        private HandbalKeeperRepository $keeperRepository,
        private HandbalScoreRepository $scoreRepository,
        private HandbalSeizoenRepository $seizoenRepository,
    ) {}

    #[Route('/', name: 'handbal_public', methods: ['GET', 'POST'])]
    public function public(Request $request): Response
    {
        $session = $request->getSession();
        $password = $request->request->get('password');
        // HANDBAL_PUBLIC_PASSWORD; while it's empty nobody can open the page
        $storedPassword = (string) $this->getParameter('handbal_public_password');

        if ($request->isMethod('POST') && $storedPassword !== '' && is_string($password) && hash_equals($storedPassword, $password)) {
            $session->set('handbal_authenticated', true);
        }

        if (!$session->get('handbal_authenticated')) {
            return $this->render('handbal/public/password.html.twig');
        }

        $seizoen = $this->seizoenRepository->findByCurrentDate();
        
        if ($seizoen) {
            $rondes = $seizoen->getRondes()->toArray();
            usort($rondes, fn($a, $b) => $a->getDatum() <=> $b->getDatum());
        } else {
            $rondes = [];
        }
        
        // Use ALL players/keepers for standings (not filtered by actief) - matching old project
        $spelers = $this->spelerRepository->findAllOrderedByName();
        $keepers = $this->keeperRepository->findAllOrderedByName();

        $standings = $this->calculateStandings($spelers, $keepers, $rondes);

        // Pre-compute scores for detail table - use ACTIVE players/keepers only (matching old project)
        $scoresBySpelerAndRonde = [];
        foreach ($this->spelerRepository->findActiveOrderedByName() as $speler) {
            foreach ($rondes as $ronde) {
                $score = $this->scoreRepository->findBySpelerAndRonde($speler, $ronde);
                if ($score) {
                    $scoresBySpelerAndRonde[$speler->getId()][$ronde->getId()] = $score;
                }
            }
        }

        $keeperScoresByRonde = [];
        foreach ($this->keeperRepository->findActiveOrderedByName() as $keeper) {
            foreach ($rondes as $ronde) {
                $scores = $this->scoreRepository->findByKeeperAndRonde($keeper, $ronde);
                $reddingen = 0;
                foreach ($scores as $score) {
                    if (!$score->isGescored()) {
                        $reddingen++;
                    }
                }
                $keeperScoresByRonde[$keeper->getId()][$ronde->getId()] = $reddingen;
            }
        }

        return $this->render('handbal/public/index.html.twig', [
            'standings' => $standings,
            'rondes' => $rondes,
            'seizoen' => $seizoen,
            'spelers' => $spelers,
            'keepers' => $keepers,
            'scoresBySpelerAndRonde' => $scoresBySpelerAndRonde,
            'keeperScoresByRonde' => $keeperScoresByRonde,
        ]);
    }

    #[Route('/logout', name: 'handbal_public_logout', methods: ['GET'])]
    public function logout(Request $request): Response
    {
        $session = $request->getSession();
        $session->remove('handbal_authenticated');
        return $this->redirectToRoute('handbal_public');
    }

    private function calculateStandings(array $spelers, array $keepers, array $rondes): array
    {
        $aantalMaandagen = count($rondes);
        $aantalSpelers = count($spelers);

        if ($aantalMaandagen === 0) {
            return [
                'spelers' => [],
                'keepers' => [],
                'aantalMaandagen' => 0,
                'aantalSpelers' => $aantalSpelers,
            ];
        }

        // Get scores in bulk for efficiency
        $allScores = $this->scoreRepository->getScoresForStandings($spelers, $rondes);
        $keeperScores = $this->scoreRepository->getKeeperScoresForStandings($keepers, $rondes);

        // Group scores by speler_id -> ronde_id
        $scoresBySpeler = [];
        foreach ($allScores as $score) {
            $spelerId = $score->getSpeler()?->getId();
            $rondeId = $score->getRonde()?->getId();
            if ($spelerId && $rondeId) {
                $scoresBySpeler[$spelerId][$rondeId] = $score;
            }
        }

        // Group keeper scores by keeper_id -> ronde_id
        $scoresByKeeper = [];
        foreach ($keeperScores as $score) {
            $keeperId = $score->getKeeper()?->getId();
            $rondeId = $score->getRonde()?->getId();
            if ($keeperId && $rondeId) {
                $scoresByKeeper[$keeperId][$rondeId][] = $score;
            }
        }

        $spelerStandings = [];
        foreach ($spelers as $speler) {
            $doelpunten = 0;
            foreach ($rondes as $ronde) {
                $score = $scoresBySpeler[$speler->getId()][$ronde->getId()] ?? null;
                if ($score && $score->isGescored()) {
                    $doelpunten++;
                }
            }
            /** @phpstan-ignore greater.alwaysTrue */
            $gemiddelde = $aantalMaandagen > 0 ? round($doelpunten / $aantalMaandagen, 2) : 0;

            $spelerStandings[] = [
                'speler' => $speler,
                'doelpunten' => $doelpunten,
                'gemiddelde' => $gemiddelde,
                'is_keeper' => false,
            ];
        }

        // Add keepers to player standings
        foreach ($keepers as $keeper) {
            $reddingen = 0;
            foreach ($rondes as $ronde) {
                $scores = $scoresByKeeper[$keeper->getId()][$ronde->getId()] ?? [];
                foreach ($scores as $score) {
                    if (!$score->isGescored()) {
                        $reddingen++;
                    }
                }
            }
            $maxMogelijkeReddingen = $aantalMaandagen * $aantalSpelers;
            $gemiddelde = $maxMogelijkeReddingen > 0 ? round($reddingen / $maxMogelijkeReddingen, 2) : 0;

            $spelerStandings[] = [
                'speler' => $keeper,
                'doelpunten' => $reddingen,
                'gemiddelde' => $gemiddelde,
                'is_keeper' => true,
                'maxMogelijk' => $maxMogelijkeReddingen,
            ];
        }

        usort($spelerStandings, function($a, $b) {
            return $b['gemiddelde'] <=> $a['gemiddelde'];
        });

        $keeperStandings = [];
        foreach ($keepers as $keeper) {
            $reddingen = 0;
            foreach ($rondes as $ronde) {
                $scores = $scoresByKeeper[$keeper->getId()][$ronde->getId()] ?? [];
                foreach ($scores as $score) {
                    if (!$score->isGescored()) {
                        $reddingen++;
                    }
                }
            }
            $maxMogelijkeReddingen = $aantalMaandagen * $aantalSpelers;
            $gemiddelde = $maxMogelijkeReddingen > 0 ? round($reddingen / $maxMogelijkeReddingen, 2) : 0;

            $keeperStandings[] = [
                'keeper' => $keeper,
                'reddingen' => $reddingen,
                'maxMogelijk' => $maxMogelijkeReddingen,
                'gemiddelde' => $gemiddelde,
            ];
        }

        usort($keeperStandings, function($a, $b) {
            return $b['reddingen'] <=> $a['reddingen'];
        });

        return [
            'spelers' => $spelerStandings,
            'keepers' => $keeperStandings,
            'aantalMaandagen' => $aantalMaandagen,
            'aantalSpelers' => $aantalSpelers,
        ];
    }
}