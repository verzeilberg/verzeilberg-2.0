<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Steam\SteamGame;
use App\Entity\Steam\SteamGameReview;
use App\Form\SteamGameReviewType;
use App\Repository\Steam\SteamGameRepository;
use App\Service\Steam\SteamApiException;
use App\Service\Steam\SteamGameStatsProvider;
use App\Service\Steam\SteamLibrarySync;
use App\Service\Steam\SteamPrivacy;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Steam library in the admin: sync status, "Sync now", and hiding games from the public pages.
 */
#[Route('/admin/steam')]
class SteamController extends AbstractController
{
    public function __construct(
        private SteamLibrarySync $sync,
        private SteamGameRepository $gameRepository,
    ) {
    }

    #[Route('/', name: 'admin_steam_index', methods: ['GET'])]
    public function index(Request $request, PaginatorInterface $paginator, SteamPrivacy $privacy): Response
    {
        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'genre' => (string) $request->query->get('genre', ''),
            // ("order", not "sort": the paginator uses "sort" for its own column sorting)
            'sort' => isset(SteamGameRepository::SORTS[$order = (string) $request->query->get('order', 'recent')]) ? $order : 'recent',
            // Empty: the column's own first direction (name A-Z, the rest highest/newest first)
            'dir' => in_array($dir = strtolower((string) $request->query->get('dir', '')), ['asc', 'desc'], true) ? $dir : '',
        ];

        return $this->render('admin/steam/index.html.twig', [
            'configured' => $this->sync->isConfigured(),
            'hideActivity' => $privacy->isActivityHidden(),
            'account' => $this->sync->getAccount(),
            'stats' => $this->gameRepository->getStats(true),
            'genres' => $this->gameRepository->findGenres(true),
            'filters' => $filters,
            'sorts' => SteamGameRepository::SORTS,
            'games' => $paginator->paginate($this->gameRepository->createLibraryQuery($filters, true), $request->query->getInt('page', 1), 50),
        ]);
    }

    /** Statistics of one game (achievements with global rarity, the game's own stats, live from Steam) and my review */
    #[Route('/game/{id}', name: 'admin_steam_game', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function game(Request $request, SteamGame $game, SteamGameStatsProvider $statsProvider, EntityManagerInterface $em): Response
    {
        // First the statistics (this also saves the achievement count), so a review form with errors is never saved with it
        $stats = null;
        $error = null;
        try {
            $stats = $statsProvider->forGame($game, $request->query->getBoolean('refresh'));
        } catch (SteamApiException $e) {
            $error = $e->getMessage();
        }

        $review = $game->getReview() ?? new SteamGameReview($game);
        $reviewForm = $this->createForm(SteamGameReviewType::class, $review);
        $reviewForm->handleRequest($request);
        if ($reviewForm->isSubmitted() && $reviewForm->isValid()) {
            if ($review->getId() !== null) {
                $review->markUpdated();
            }
            $em->persist($review);
            $em->flush();
            $this->addFlash('success', $review->isPublished() ? 'Review opgeslagen en gepubliceerd.' : 'Review opgeslagen als concept.');

            return $this->redirectToRoute('admin_steam_game', ['id' => $game->getId(), '_fragment' => 'review']);
        }

        $show = in_array($show = (string) $request->query->get('show', 'all'), ['all', 'unlocked', 'locked'], true) ? $show : 'all';

        return $this->render('admin/steam/game.html.twig', [
            'game' => $game,
            'review' => $review,
            'account' => $this->sync->getAccount(),
            'stats' => $stats,
            'error' => $error,
            'show' => $show,
            'reviewForm' => $reviewForm,
        ], new Response(status: $reviewForm->isSubmitted() && !$reviewForm->isValid() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /** Sync with smaller limits than the cronjob, so the page answers quickly */
    #[Route('/sync', name: 'admin_steam_sync', methods: ['POST'])]
    public function sync(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('steam_sync', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Ongeldig beveiligingstoken, probeer het opnieuw.');

            return $this->redirectToRoute('admin_steam_index');
        }

        try {
            $this->addFlash('success', 'Steam gesynchroniseerd: ' . $this->sync->sync(20, 40)->summary());
        } catch (SteamApiException $e) {
            $this->addFlash('error', 'Steam-synchronisatie mislukt: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_steam_index');
    }

    /** Called by the shared delete modal */
    #[Route('/game/{id}/review/delete', name: 'admin_steam_review_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteReview(Request $request, SteamGame $game, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->isCsrfTokenValid('steam_review_delete' . $game->getId(), (string) $request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Ongeldig beveiligingstoken, herlaad de pagina.'], Response::HTTP_FORBIDDEN);
        }
        if ($review = $game->getReview()) {
            $game->setReview(null);
            $em->remove($review);
            $em->flush();
            $this->addFlash('success', 'Review verwijderd.');
        }

        return new JsonResponse(['success' => true]);
    }

    /** Switch: show or hide when games were played on the public pages */
    #[Route('/privacy', name: 'admin_steam_privacy', methods: ['POST'])]
    public function privacy(Request $request, SteamPrivacy $privacy): Response
    {
        if ($this->isCsrfTokenValid('steam_privacy', (string) $request->request->get('_token'))) {
            $hide = $request->request->getBoolean('hide');
            $privacy->setActivityHidden($hide);
            $this->addFlash('success', $hide
                ? 'De openbare pagina\'s tonen niet meer wanneer je speelde (laatst gespeeld, recent gespeeld, nu aan het spelen).'
                : 'De openbare pagina\'s tonen weer wanneer je speelde.');
        }

        return $this->redirectToRoute('admin_steam_index');
    }

    #[Route('/game/{id}/visibility', name: 'admin_steam_game_visibility', methods: ['POST'])]
    public function visibility(Request $request, SteamGame $game, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('steam_visibility' . $game->getId(), (string) $request->request->get('_token'))) {
            $game->setHidden(!$game->isHidden());
            $em->flush();
            $this->addFlash('success', sprintf('"%s" is nu %s op de openbare pagina\'s.', $game->getName(), $game->isHidden() ? 'verborgen' : 'zichtbaar'));
        }

        // Back to the same page of the list
        $back = (string) $request->request->get('back', '');

        return str_starts_with($back, '/admin/steam') ? $this->redirect($back) : $this->redirectToRoute('admin_steam_index');
    }
}
