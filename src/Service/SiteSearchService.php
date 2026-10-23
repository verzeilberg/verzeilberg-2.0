<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BlogPost;
use App\Entity\Event;
use App\Repository\BlogPostRepository;
use App\Repository\EventRepository;
use App\Repository\SearchPhraseRepository;
use App\Repository\Steam\SteamGameRepository;
use Doctrine\ORM\EntityManagerInterface;

class SiteSearchService
{
    public function __construct(
        private BlogPostRepository $blogPostRepository,
        private EventRepository $eventRepository,
        private SearchPhraseRepository $searchPhraseRepository,
        private SteamGameRepository $steamGameRepository,
        private EntityManagerInterface $em
    ) {
    }

    public function search(string $term): array
    {
        $term = trim($term);
        if (empty($term)) {
            return [
                'blogPosts' => [],
                'events' => [],
                'games' => [],
                'totalCount' => 0,
            ];
        }

        // Track search phrase
        $existing = $this->searchPhraseRepository->findByPhrase(strtolower($term));
        if ($existing) {
            $existing->incrementCount();
            $existing->updateDateSearched();
        } else {
            $phrase = new \App\Entity\SearchPhrase();
            $phrase->setSearchPhrase(strtolower($term));
            $phrase->setType('site');
            $phrase->setCount(1);
            $this->em->persist($phrase);
        }
        $this->em->flush();

        // Only posts that are online (search() also finds scheduled/expired ones and stops at 10)
        $blogPosts = $this->blogPostRepository->createPublicListQuery(null, $term)->setMaxResults(100)->getQuery()->getResult();
        $events = $this->eventRepository->search($term);
        $games = $this->steamGameRepository->searchPublic($term);

        return [
            'blogPosts' => $blogPosts,
            'events' => $events,
            'games' => $games,
            'totalCount' => count($blogPosts) + count($events) + count($games),
        ];
    }

    /**
     * Returns paginated results as an array with pagination metadata
     */
    public function searchPaginated(string $term, int $page = 1, int $limit = 10): array
    {
        $results = $this->search($term);
        
        // Combine results into a single array with type info
        $combined = [];
        foreach ($results['blogPosts'] as $post) {
            $combined[] = ['type' => 'blog', 'entity' => $post, 'date' => $post->getDateCreated()];
        }
        foreach ($results['events'] as $event) {
            $combined[] = ['type' => 'event', 'entity' => $event, 'date' => $event->getDateCreated()];
        }
        foreach ($results['games'] as $game) {
            // A reviewed game counts from its review, otherwise from when it came into the library
            $combined[] = ['type' => 'game', 'entity' => $game, 'date' => $game->getPublishedReview()?->getCreatedAt() ?? $game->getAddedAt()];
        }
        
        // Sort by date descending
        usort($combined, fn($a, $b) => $b['date'] <=> $a['date']);
        
        // Manual pagination
        $totalItems = count($combined);
        $totalPages = (int) ceil($totalItems / $limit);
        $offset = ($page - 1) * $limit;
        $items = array_slice($combined, $offset, $limit);
        
        return [
            'items' => $items,
            'totalItems' => $totalItems,
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'itemsPerPage' => $limit,
            'hasNextPage' => $page < $totalPages,
            'hasPreviousPage' => $page > 1,
            'nextPage' => $page < $totalPages ? $page + 1 : null,
            'previousPage' => $page > 1 ? $page - 1 : null,
        ];
    }
}