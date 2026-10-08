<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\MenuRepository;
use App\Repository\BlogPostRepository;
use App\Repository\BlogCommentRepository;
use App\Repository\CategoryRepository;
use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private MenuRepository $menuRepository,
        private BlogPostRepository $blogPostRepository,
        private BlogCommentRepository $blogCommentRepository,
        private CategoryRepository $categoryRepository,
        private EventRepository $eventRepository
    ) {}

    #[Route('/', name: 'admin_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'menus' => $this->menuRepository->findAllActive(),
            'blogPosts' => $this->blogPostRepository->findLatest(5),
            'blogCategories' => $this->categoryRepository->findAllActive(),
            'events' => $this->eventRepository->findLatest(5),
            'recentComments' => $this->blogCommentRepository->findLatest(5),
        ]);
    }
}