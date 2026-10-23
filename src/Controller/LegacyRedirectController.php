<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\BlogPostRepository;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * URLs of the old (Laminas) site, permanently redirected to the new pages, so links elsewhere and search results
 * keep working. (The old blog list's "/blog?page=2" is handled in BlogController::index().)
 */
class LegacyRedirectController extends AbstractController
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/config/legacy_blog_slugs.php')] private string $blogSlugsFile,
    ) {
    }

    #[Route('/blog/detail/{slug}', name: 'legacy_blog_detail', methods: ['GET'])]
    public function blogPost(string $slug, BlogPostRepository $blogPostRepository): RedirectResponse
    {
        /** @var array<string, int> $oldSlugs */
        $oldSlugs = require $this->blogSlugsFile;
        $post = isset($oldSlugs[$slug]) ? $blogPostRepository->findOneBy(['id' => $oldSlugs[$slug], 'deleted' => false]) : $blogPostRepository->findBySlug($slug);
        if (!$post) {
            throw $this->createNotFoundException('Bericht niet gevonden');
        }

        return $this->redirectToRoute('blog_detail', ['slug' => $post->getSlug()], 301);
    }

    /** The old site used category ids; the categories kept them. Priority 1: before /blog/category/{slug} */
    #[Route('/blog/category/{id<\d+>}', name: 'legacy_blog_category', methods: ['GET'], priority: 1)]
    public function blogCategory(int $id, CategoryRepository $categoryRepository): RedirectResponse
    {
        $category = $categoryRepository->find($id) ?? throw $this->createNotFoundException('Categorie niet gevonden');

        return $this->redirectToRoute('blog_category', ['slug' => $category->getSlug()], 301);
    }

    #[Route('/running-stats', name: 'legacy_running_stats', methods: ['GET'])]
    public function runningStats(): RedirectResponse
    {
        return $this->redirectToRoute('app_running', [], 301);
    }

    #[Route('/beheer{rest}', name: 'legacy_admin', requirements: ['rest' => '(/.*)?'], methods: ['GET'])]
    public function admin(): RedirectResponse
    {
        return $this->redirectToRoute('admin_dashboard', [], 301);
    }
}
