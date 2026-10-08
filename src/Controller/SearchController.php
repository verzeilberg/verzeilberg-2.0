<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\SiteSearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SearchController extends AbstractController
{
    public function __construct(
        private SiteSearchService $siteSearchService
    ) {}

    #[Route('/search', name: 'app_search', methods: ['GET', 'POST'])]
    #[Route('/search/page/{page<\d+>}', name: 'app_search_paginated', methods: ['GET'])]
    public function index(Request $request, int $page = 1): Response
    {
        $searchTerm = $request->query->get('q', $request->request->get('q', ''));

        if (empty($searchTerm)) {
            return $this->render('search/index.html.twig', [
                'searchTerm' => '',
                'results' => null,
            ]);
        }

        $results = $this->siteSearchService->searchPaginated($searchTerm, $page, 10);

        return $this->render('search/index.html.twig', [
            'searchTerm' => $searchTerm,
            'results' => $results,
        ]);
    }
}