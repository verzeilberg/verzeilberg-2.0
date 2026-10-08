<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BlogPost;
use App\Entity\Event;
use App\Repository\BlogPostRepository;
use App\Repository\EventRepository;
use App\Repository\SearchPhraseRepository;
use Doctrine\ORM\EntityManagerInterface;

class SiteSearchService
{
    public function __construct(
        private BlogPostRepository $blogPostRepository,
        private EventRepository $eventRepository,
        private SearchPhraseRepository $searchPhraseRepository,
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

        $blogPosts = $this->blogPostRepository->search($term);
        $events = $this->eventRepository->search($term);

        return [
            'blogPosts' => $blogPosts,
            'events' => $events,
            'totalCount' => count($blogPosts) + count($events),
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