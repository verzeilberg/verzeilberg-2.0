<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\Steam\SteamGameRepository;
use App\Service\Steam\SteamLibrarySync;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public Steam game library. Only reads the local copy; SteamLibrarySync (cronjob) keeps it up to date.
 */
class GamesController extends AbstractController
{
    #[Route('/games', name: 'app_games', methods: ['GET'])]
    public function index(Request $request, SteamGameRepository $gameRepository, SteamLibrarySync $sync, PaginatorInterface $paginator): Response
    {
        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'genre' => (string) $request->query->get('genre', ''),
            // ("order", not "sort": the paginator uses "sort" for its own column sorting)
            'sort' => (string) $request->query->get('order', 'recent'),
            // Played games only, unless "all" is chosen
            'played' => $request->query->get('show') !== 'all',
        ];

        return $this->render('games/index.html.twig', [
            'account' => $sync->getAccount(),
            'stats' => $gameRepository->getStats(),
            'recent' => $gameRepository->findRecentlyPlayed(4),
            'genres' => $gameRepository->findGenres(),
            'filters' => $filters,
            'sorts' => array_keys(SteamGameRepository::SORTS),
            'games' => $paginator->paginate($gameRepository->createLibraryQuery($filters), $request->query->getInt('page', 1), 24),
        ]);
    }
}
