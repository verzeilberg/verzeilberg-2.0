<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Steam\SteamGame;
use App\Repository\Steam\SteamGameRepository;
use App\Service\Steam\SteamApiException;
use App\Service\Steam\SteamLibrarySync;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'genre' => (string) $request->query->get('genre', ''),
            // ("order", not "sort": the paginator uses "sort" for its own column sorting)
            'sort' => (string) $request->query->get('order', 'recent'),
        ];

        return $this->render('admin/steam/index.html.twig', [
            'configured' => $this->sync->isConfigured(),
            'account' => $this->sync->getAccount(),
            'stats' => $this->gameRepository->getStats(true),
            'genres' => $this->gameRepository->findGenres(true),
            'filters' => $filters,
            'sorts' => array_keys(SteamGameRepository::SORTS),
            'games' => $paginator->paginate($this->gameRepository->createLibraryQuery($filters, true), $request->query->getInt('page', 1), 50),
        ]);
    }

    /** Sync with smaller limits than the cronjob, so the page answers quickly */
    #[Route('/sync', name: 'admin_steam_sync', methods: ['POST'])]
    public function sync(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('steam_sync', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token, please try again.');

            return $this->redirectToRoute('admin_steam_index');
        }

        try {
            $this->addFlash('success', 'Steam synced: ' . $this->sync->sync(20, 40)->summary());
        } catch (SteamApiException $e) {
            $this->addFlash('error', 'Steam sync failed: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_steam_index');
    }

    #[Route('/game/{id}/visibility', name: 'admin_steam_game_visibility', methods: ['POST'])]
    public function visibility(Request $request, SteamGame $game, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('steam_visibility' . $game->getId(), (string) $request->request->get('_token'))) {
            $game->setHidden(!$game->isHidden());
            $em->flush();
            $this->addFlash('success', sprintf('"%s" is now %s on the public pages.', $game->getName(), $game->isHidden() ? 'hidden' : 'visible'));
        }

        // Back to the same page of the list
        $back = (string) $request->request->get('back', '');

        return str_starts_with($back, '/admin/steam') ? $this->redirect($back) : $this->redirectToRoute('admin_steam_index');
    }
}
