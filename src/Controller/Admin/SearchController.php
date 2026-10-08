<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\SearchPhraseService;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/search')]
#[IsGranted('ROLE_ADMIN')]
class SearchController extends AbstractController
{
    public function __construct(
        private SearchPhraseService $searchPhraseService
    ) {}

    #[Route('/', name: 'admin_search_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = 20;

        /** @var PaginationInterface $pagination */
        $pagination = $this->searchPhraseService->getAllPhrases($page, $limit);

        return $this->render('admin/search/index.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    #[Route('/{id<\d+>}', name: 'admin_search_delete', methods: ['POST'])]
    public function delete(Request $request, int $id): JsonResponse
    {
        if (!$this->isCsrfTokenValid('delete' . $id, $request->headers->get('X-CSRF-Token') ?? $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid CSRF token']);
        }

        $success = $this->searchPhraseService->deletePhrase($id);

        if ($success) {
            return new JsonResponse(['success' => true]);
        }

        return new JsonResponse(['success' => false, 'message' => 'Search phrase not found']);
    }
}