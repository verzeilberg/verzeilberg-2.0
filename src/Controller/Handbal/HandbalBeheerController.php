<?php

declare(strict_types=1);

namespace App\Controller\Handbal;

use App\Entity\Handbal\HandbalScore;
use App\Entity\Handbal\HandbalSpeler;
use App\Entity\Handbal\HandbalKeeper;
use App\Entity\Handbal\HandbalRonde;
use App\Entity\Handbal\HandbalSeizoen;
use App\Form\Handbal\HandbalScoreType;
use App\Form\Handbal\HandbalSpelerType;
use App\Form\Handbal\HandbalKeeperType;
use App\Form\Handbal\HandbalRondeType;
use App\Form\Handbal\HandbalSeizoenType;
use App\Repository\Handbal\HandbalScoreRepository;
use App\Repository\Handbal\HandbalSpelerRepository;
use App\Repository\Handbal\HandbalKeeperRepository;
use App\Repository\Handbal\HandbalRondeRepository;
use App\Repository\Handbal\HandbalSeizoenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/beheer/handbal')]
#[IsGranted('ROLE_ADMIN')]
class HandbalBeheerController extends AbstractController
{
    public function __construct(
        private HandbalSpelerRepository $spelerRepository,
        private HandbalKeeperRepository $keeperRepository,
        private HandbalRondeRepository $rondeRepository,
        private HandbalScoreRepository $scoreRepository,
        private HandbalSeizoenRepository $seizoenRepository,
        private EntityManagerInterface $em,
    ) {}

    #[Route('/', name: 'handbal_beheer_index', methods: ['GET'])]
    public function index(): Response
    {
        $spelers = $this->spelerRepository->findActiveOrderedByName();
        $keepers = $this->keeperRepository->findActiveOrderedByName();
        $rondes = $this->rondeRepository->findAllOrderedByDateDesc();
        $seizoenen = $this->seizoenRepository->findAllOrderedByStartDateDesc();

        return $this->render('handbal/beheer/index.html.twig', [
            'spelers' => $spelers,
            'keepers' => $keepers,
            'rondes' => $rondes,
            'seizoenen' => $seizoenen,
        ]);
    }

    // Speler management
    #[Route('/speler/new', name: 'handbal_beheer_speler_new', methods: ['GET', 'POST'])]
    public function newSpeler(Request $request): Response
    {
        $speler = new HandbalSpeler();
        $form = $this->createForm(HandbalSpelerType::class, $speler);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($speler);
            $this->em->flush();
            $this->addFlash('success', 'Speler toegevoegd');
            return $this->redirectToRoute('handbal_beheer_index');
        }

        return $this->render('handbal/beheer/speler_form.html.twig', [
            'form' => $form,
            'speler' => $speler,
            'action' => 'new',
        ]);
    }

    #[Route('/speler/{id}/edit', name: 'handbal_beheer_speler_edit', methods: ['GET', 'POST'])]
    public function editSpeler(Request $request, HandbalSpeler $speler): Response
    {
        $form = $this->createForm(HandbalSpelerType::class, $speler);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $speler->touch($this->getUser() instanceof \App\Entity\User ? $this->getUser() : null);
            $this->em->flush();
            $this->addFlash('success', 'Speler bijgewerkt');
            return $this->redirectToRoute('handbal_beheer_index');
        }

        return $this->render('handbal/beheer/speler_form.html.twig', [
            'form' => $form,
            'speler' => $speler,
            'action' => 'edit',
        ]);
    }

    // Keeper management
    #[Route('/keeper/new', name: 'handbal_beheer_keeper_new', methods: ['GET', 'POST'])]
    public function newKeeper(Request $request): Response
    {
        $keeper = new HandbalKeeper();
        $form = $this->createForm(HandbalKeeperType::class, $keeper);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($keeper);
            $this->em->flush();
            $this->addFlash('success', 'Keeper toegevoegd');
            return $this->redirectToRoute('handbal_beheer_index');
        }

        return $this->render('handbal/beheer/keeper_form.html.twig', [
            'form' => $form,
            'keeper' => $keeper,
            'action' => 'new',
        ]);
    }

    #[Route('/keeper/{id}/edit', name: 'handbal_beheer_keeper_edit', methods: ['GET', 'POST'])]
    public function editKeeper(Request $request, HandbalKeeper $keeper): Response
    {
        $form = $this->createForm(HandbalKeeperType::class, $keeper);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $keeper->touch($this->getUser() instanceof \App\Entity\User ? $this->getUser() : null);
            $this->em->flush();
            $this->addFlash('success', 'Keeper bijgewerkt');
            return $this->redirectToRoute('handbal_beheer_index');
        }

        return $this->render('handbal/beheer/keeper_form.html.twig', [
            'form' => $form,
            'keeper' => $keeper,
            'action' => 'edit',
        ]);
    }

    // Seizoen management
    #[Route('/seizoenen', name: 'handbal_beheer_seizoenen', methods: ['GET'])]
    public function seizoenen(): Response
    {
        $seizoenen = $this->seizoenRepository->findAllOrderedByStartDateDesc();
        return $this->render('handbal/beheer/seizoenen.html.twig', [
            'seizoenen' => $seizoenen,
        ]);
    }

    #[Route('/seizoen/new', name: 'handbal_beheer_seizoen_new', methods: ['GET', 'POST'])]
    public function newSeizoen(Request $request): Response
    {
        $seizoen = new HandbalSeizoen();
        $form = $this->createForm(HandbalSeizoenType::class, $seizoen);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($seizoen);
            $this->em->flush();
            $this->addFlash('success', 'Seizoen toegevoegd');
            return $this->redirectToRoute('handbal_beheer_seizoenen');
        }

        return $this->render('handbal/beheer/seizoen_form.html.twig', [
            'form' => $form,
            'seizoen' => $seizoen,
            'action' => 'new',
        ]);
    }

    #[Route('/seizoen/{id}/edit', name: 'handbal_beheer_seizoen_edit', methods: ['GET', 'POST'])]
    public function editSeizoen(Request $request, HandbalSeizoen $seizoen): Response
    {
        $form = $this->createForm(HandbalSeizoenType::class, $seizoen);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $seizoen->touch($this->getUser() instanceof \App\Entity\User ? $this->getUser() : null);
            $this->em->flush();
            $this->addFlash('success', 'Seizoen bijgewerkt');
            return $this->redirectToRoute('handbal_beheer_seizoenen');
        }

        return $this->render('handbal/beheer/seizoen_form.html.twig', [
            'form' => $form,
            'seizoen' => $seizoen,
            'action' => 'edit',
        ]);
    }

    #[Route('/seizoen/{id}/delete', name: 'handbal_beheer_seizoen_delete', methods: ['POST'])]
    public function deleteSeizoen(Request $request, HandbalSeizoen $seizoen): JsonResponse
    {
        $rondes = $this->rondeRepository->findBySeasonOrdered($seizoen);
        if (!empty($rondes)) {
            return new JsonResponse(['success' => false, 'message' => 'Kan niet verwijderen: er zijn nog rondes gekoppeld aan dit seizoen']);
        }

        $this->em->remove($seizoen);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Seizoen verwijderd']);
    }

    #[Route('/ronde/{id}/delete', name: 'handbal_beheer_ronde_delete', methods: ['POST'])]
    public function deleteRonde(Request $request, HandbalRonde $ronde): JsonResponse
    {
        $this->em->remove($ronde);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Ronde verwijderd']);
    }

    #[Route('/seizoen/{id}/dates', name: 'handbal_beheer_seizoen_dates', methods: ['GET'])]
    public function getSeizoenDates(HandbalSeizoen $seizoen): JsonResponse
    {
        return new JsonResponse([
            'startDatum' => $seizoen->getStartDatum()->format('Y-m-d'),
            'eindDatum' => $seizoen->getEindDatum()->format('Y-m-d'),
        ]);
    }

    // Ronde management
    #[Route('/ronde/new', name: 'handbal_beheer_ronde_new', methods: ['GET', 'POST'])]
    public function newRonde(Request $request): Response
    {
        $ronde = new HandbalRonde();
        $seizoenen = $this->seizoenRepository->findActiveOrderedByStartDateDesc();
        
        if ($seizoenId = $request->query->getInt('seizoen_id')) {
            $seizoen = $this->seizoenRepository->find($seizoenId);
            if ($seizoen) {
                $ronde->setSeizoen($seizoen);
                // Set default date based on season dates vs current date
                $now = new \DateTime();
                if ($now < $seizoen->getStartDatum()) {
                    $ronde->setDatum($seizoen->getStartDatum());
                } elseif ($now > $seizoen->getEindDatum()) {
                    $ronde->setDatum($seizoen->getEindDatum());
                } else {
                    $ronde->setDatum($now);
                }
            }
        } elseif (!empty($seizoenen)) {
            $ronde->setSeizoen($seizoenen[0]);
        }

        $spelers = $this->spelerRepository->findActiveOrderedByName();
        $keepers = $this->keeperRepository->findActiveOrderedByName();

        // Auto-select keeper if only one exists
        if (count($keepers) === 1) {
            $ronde->setKeeper($keepers[0]);
        }

        $form = $this->createForm(HandbalRondeType::class, $ronde);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check for duplicate date
            $existingRonde = $this->rondeRepository->findByDate($ronde->getDatum());
            if ($existingRonde && !$ronde->getId()) {
                $this->addFlash('error', 'Er bestaat al een ronde op deze datum: ' . $ronde->getDatum()->format('d-m-Y'));
                return $this->render('handbal/beheer/ronde_form.html.twig', [
                    'form' => $form,
                    'ronde' => $ronde,
                    'seizoenen' => $seizoenen,
                    'spelers' => $spelers,
                    'keepers' => $keepers,
                    'action' => 'new',
                ]);
            }

            // Set date created/changed
            if (!$ronde->getId()) {
                $ronde->setDateCreated(new \DateTimeImmutable());
            }
            $ronde->setDateChanged(new \DateTimeImmutable());

            $this->em->persist($ronde);
            $this->em->flush();

            // Save scores - use keeper from form
            $scoresData = $request->request->all('scores');
            $keeper = $form->get('keeper')->getData();
            
            if (!empty($scoresData) && $keeper) {
                foreach ($spelers as $speler) {
                    $spelerId = $speler->getId();
                    $gescored = isset($scoresData[$spelerId]) && $scoresData[$spelerId] == '1';

                    $score = $this->scoreRepository->findBySpelerAndRonde($speler, $ronde);
                    if (!$score) {
                        $score = new HandbalScore();
                        $score->setSpeler($speler);
                        $score->setRonde($ronde);
                    }
                    $score->setKeeper($keeper);
                    $score->setGescored($gescored);

                    $this->em->persist($score);
                }
                $this->em->flush();
            }

            $this->addFlash('success', 'Ronde en scores opgeslagen');
            return $this->redirectToRoute('handbal_beheer_scores_ronde', ['id' => $ronde->getId()]);
        }

        return $this->render('handbal/beheer/ronde_form.html.twig', [
            'form' => $form,
            'ronde' => $ronde,
            'seizoenen' => $seizoenen,
            'spelers' => $spelers,
            'keepers' => $keepers,
            'action' => 'new',
        ]);
    }

    // Scores management
    #[Route('/scores', name: 'handbal_beheer_scores', methods: ['GET'])]
    public function scores(Request $request): Response
    {
        $seizoenId = $request->query->getInt('seizoen_id', 0);
        
        if ($seizoenId) {
            $seizoen = $this->seizoenRepository->find($seizoenId);
        } else {
            // Default to current season based on today's date
            $seizoen = $this->seizoenRepository->findByCurrentDate();
        }

        if ($seizoen) {
            $rondes = $this->rondeRepository->findBySeasonOrdered($seizoen);
        } else {
            $rondes = $this->rondeRepository->findAllOrderedByDateAsc();
        }

        $spelers = $this->spelerRepository->findActiveOrderedByName();
        $keepers = $this->keeperRepository->findActiveOrderedByName();
        $seizoenen = $this->seizoenRepository->findActiveOrderedByStartDateDesc();

        return $this->render('handbal/beheer/scores.html.twig', [
            'rondes' => $rondes,
            'spelers' => $spelers,
            'keepers' => $keepers,
            'seizoenen' => $seizoenen,
            'currentSeizoen' => $seizoen,
        ]);
    }

    #[Route('/scores/ronde/{id}', name: 'handbal_beheer_scores_ronde', methods: ['GET', 'POST'])]
    public function scoresForRonde(int $id, Request $request): Response
    {
        $ronde = $this->rondeRepository->find($id);
        if (!$ronde) {
            $this->addFlash('error', 'Ronde niet gevonden');
            return $this->redirectToRoute('handbal_beheer_scores');
        }

        $spelers = $this->spelerRepository->findActiveOrderedByName();
        $keepers = $this->keeperRepository->findActiveOrderedByName();
        $seizoenen = $this->seizoenRepository->findActiveOrderedByStartDateDesc();

        // Get existing scores for this ronde
        $existingScores = [];
        foreach ($spelers as $speler) {
            $score = $this->scoreRepository->findBySpelerAndRonde($speler, $ronde);
            if ($score) {
                $existingScores[$speler->getId()] = $score;
            }
        }

        // Handle form submission
        if ($request->isMethod('POST')) {
            $keeperId = $request->request->getInt('keeper_id');
            $scoresData = $request->request->all('scores');

            $keeper = $keeperId ? $this->keeperRepository->find($keeperId) : null;

            if (!$keeper) {
                $this->addFlash('error', 'Kies een keeper');
            } else {
                foreach ($spelers as $speler) {
                    $spelerId = $speler->getId();
                    $gescored = isset($scoresData[$spelerId]) && $scoresData[$spelerId] == '1';

                    $score = $this->scoreRepository->findBySpelerAndRonde($speler, $ronde);
                    if (!$score) {
                        $score = new HandbalScore();
                        $score->setSpeler($speler);
                        $score->setRonde($ronde);
                    }
                    $score->setKeeper($keeper);
                    $score->setGescored($gescored);

                    $this->em->persist($score);
                }

                $this->em->flush();
                $this->addFlash('success', 'Scores opgeslagen voor ronde ' . $ronde->getDatum()->format('d-m-Y'));
                return $this->redirectToRoute('handbal_beheer_scores_ronde', ['id' => $ronde->getId()]);
            }
        }

        return $this->render('handbal/beheer/scores_ronde.html.twig', [
            'ronde' => $ronde,
            'spelers' => $spelers,
            'keepers' => $keepers,
            'seizoenen' => $seizoenen,
            'existingScores' => $existingScores,
        ]);
    }

    #[Route('/scores/save', name: 'handbal_beheer_scores_save', methods: ['POST'])]
    public function saveScores(Request $request): Response
    {
        $rondeId = $request->request->getInt('ronde_id');
        $keeperId = $request->request->getInt('keeper_id');
        $scoresData = $request->request->all('scores');

        $ronde = $this->rondeRepository->find($rondeId);
        $keeper = $keeperId ? $this->keeperRepository->find($keeperId) : null;

        if (!$ronde || !$keeper) {
            $this->addFlash('error', 'Ongeldige ronde of keeper');
            return $this->redirectToRoute('handbal_beheer_scores');
        }

        $spelers = $this->spelerRepository->findActiveOrderedByName();

        foreach ($spelers as $speler) {
            $spelerId = $speler->getId();
            $gescored = isset($scoresData[$spelerId]) && $scoresData[$spelerId] == '1';

            $score = $this->scoreRepository->findBySpelerAndRonde($speler, $ronde);
            if (!$score) {
                $score = new HandbalScore();
                $score->setSpeler($speler);
                $score->setRonde($ronde);
            }
            $score->setKeeper($keeper);
            $score->setGescored($gescored);

            $this->em->persist($score);
        }

        $this->em->flush();
        $this->addFlash('success', 'Scores opgeslagen voor ronde ' . $ronde->getDatum()->format('d-m-Y'));

        return $this->redirectToRoute('handbal_beheer_scores', ['seizoen_id' => $ronde->getSeizoen()->getId()]);
    }

    // Standings
    #[Route('/standings', name: 'handbal_beheer_standings', methods: ['GET'])]
    public function standings(Request $request): Response
    {
        $seizoenId = $request->query->getInt('seizoen_id', 0);
        
        if ($seizoenId) {
            $seizoen = $this->seizoenRepository->find($seizoenId);
        } else {
            // Default to current season based on today's date
            $seizoen = $this->seizoenRepository->findByCurrentDate();
        }

        if ($seizoen) {
            $rondes = $this->rondeRepository->findBySeasonOrdered($seizoen);
        } else {
            $rondes = $this->rondeRepository->findAllOrderedByDateAsc();
        }

        $spelers = $this->spelerRepository->findActiveOrderedByName();
        $keepers = $this->keeperRepository->findActiveOrderedByName();
        $seizoenen = $this->seizoenRepository->findActiveOrderedByStartDateDesc();

        $standings = $this->calculateStandings($spelers, $keepers, $rondes);

        // Pre-compute scores for detail table
        $scoresBySpelerAndRonde = [];
        foreach ($this->spelerRepository->findAllOrderedByName() as $speler) {
            foreach ($rondes as $ronde) {
                $score = $this->scoreRepository->findBySpelerAndRonde($speler, $ronde);
                if ($score) {
                    $scoresBySpelerAndRonde[$speler->getId()][$ronde->getId()] = $score;
                }
            }
        }

        $keeperScoresByRonde = [];
        foreach ($this->keeperRepository->findAllOrderedByName() as $keeper) {
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

        return $this->render('handbal/beheer/standings.html.twig', [
            'standings' => $standings,
            'rondes' => $rondes,
            'spelers' => $this->spelerRepository->findAllOrderedByName(),
            'keepers' => $this->keeperRepository->findAllOrderedByName(),
            'scoresBySpelerAndRonde' => $scoresBySpelerAndRonde,
            'keeperScoresByRonde' => $keeperScoresByRonde,
            'seizoenen' => $seizoenen,
            'currentSeizoen' => $seizoen,
        ]);
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

        // Add keepers to player standings as well
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