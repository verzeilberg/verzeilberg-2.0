<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\Steam\SteamGameRepository;
use App\Service\Steam\SteamLibrarySync;
use App\Service\Steam\SteamPrivacy;
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
    public function index(Request $request, SteamGameRepository $gameRepository, SteamLibrarySync $sync, SteamPrivacy $privacy, PaginatorInterface $paginator): Response
    {
        // Without activity, sorting by "last played" is left out too: the order would show it
        $hideActivity = $privacy->isActivityHidden();
        $sorts = $hideActivity ? array_values(array_diff(SteamGameRepository::PUBLIC_SORTS, ['recent'])) : SteamGameRepository::PUBLIC_SORTS;
        $defaultSort = $sorts[0];

        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'genre' => (string) $request->query->get('genre', ''),
            // ("order", not "sort": the paginator uses "sort" for its own column sorting)
            'sort' => in_array($order = (string) $request->query->get('order', $defaultSort), $sorts, true) ? $order : $defaultSort,
            // Played games only, unless "all" (or "reviewed": every game with a review) is chosen
            'played' => !in_array($request->query->get('show'), ['all', 'reviewed'], true),
            'reviewed' => $request->query->get('show') === 'reviewed',
        ];

        return $this->render('games/index.html.twig', [
            'account' => $sync->getAccount(),
            'stats' => $gameRepository->getStats(),
            'recent' => $hideActivity ? [] : $gameRepository->findRecentlyPlayed(4),
            'hideActivity' => $hideActivity,
            'genres' => $gameRepository->findGenres(),
            'filters' => $filters,
            'sorts' => $sorts,
            'games' => $paginator->paginate($gameRepository->createLibraryQuery($filters), $request->query->getInt('page', 1), 24),
        ]);
    }

    /** One game with my review (if published); hidden games don't exist for visitors */
    #[Route('/games/{appId}', name: 'app_game', requirements: ['appId' => '\d+'], methods: ['GET'])]
    public function show(int $appId, SteamGameRepository $gameRepository, SteamLibrarySync $sync, SteamPrivacy $privacy): Response
    {
        $game = $gameRepository->findOneBy(['appId' => $appId]);
        if ($game === null || $game->isHidden()) {
            throw $this->createNotFoundException('Game niet gevonden.');
        }

        return $this->render('games/show.html.twig', [
            'game' => $game,
            'review' => $game->getPublishedReview(),
            'account' => $sync->getAccount(),
            'hideActivity' => $privacy->isActivityHidden(),
        ]);
    }
}
