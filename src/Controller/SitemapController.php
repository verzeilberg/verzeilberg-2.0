<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\BlogPost;
use App\Entity\Category;
use App\Repository\BlogPostRepository;
use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * For search engines: /sitemap.xml lists the public pages, /robots.txt points to it and keeps them out of the
 * pages that are personal or need a login.
 */
class SitemapController extends AbstractController
{
    /** Pages for logged-in users, or that have no use in search results */
    private const DISALLOW = ['/admin/', '/profile', '/checklists', '/friends', '/users', '/strava/', '/handbal/', '/agenda/feed/', '/2fa', '/reset-password/', '/login', '/register', '/forgot-password', '/search', '/blog/search'];

    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'], format: 'xml')]
    public function sitemap(BlogPostRepository $blogPostRepository, EventRepository $eventRepository): Response
    {
        $urls = [];
        foreach (['app_home', 'app_about', 'blog_index', 'app_events', 'app_running', 'app_agenda', 'app_games', 'app_contact'] as $route) {
            $urls[] = ['loc' => $this->generateUrl($route, [], UrlGeneratorInterface::ABSOLUTE_URL), 'lastmod' => null];
        }

        /** @var list<BlogPost> $posts */
        $posts = $blogPostRepository->createPublicListQuery()->leftJoin('bp.categories', 'c')->addSelect('c')->getQuery()->getResult();
        /** @var array<string, Category> $categories categories that have a post online */
        $categories = [];
        foreach ($posts as $post) {
            $urls[] = [
                'loc' => $this->generateUrl('blog_detail', ['slug' => $post->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'lastmod' => $post->getDateChanged() ?? $post->getDateOnline() ?? $post->getDateCreated(),
            ];
            foreach ($post->getCategories() as $category) {
                if ($category->isEnabled()) {
                    $categories[$category->getSlug()] = $category;
                }
            }
        }
        foreach ($categories as $slug => $category) {
            $urls[] = ['loc' => $this->generateUrl('blog_category', ['slug' => $slug], UrlGeneratorInterface::ABSOLUTE_URL), 'lastmod' => null];
        }

        foreach ($eventRepository->findAllActive() as $event) {
            $urls[] = [
                'loc' => $this->generateUrl('app_event_show', ['slug' => $event->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'lastmod' => $event->getDateChanged() ?? $event->getDateCreated(),
            ];
        }

        $response = $this->render('sitemap/sitemap.xml.twig', ['urls' => $urls]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }

    #[Route('/robots.txt', name: 'app_robots', methods: ['GET'], format: 'txt')]
    public function robots(): Response
    {
        $lines = ['User-agent: *'];
        foreach (self::DISALLOW as $path) {
            $lines[] = 'Disallow: ' . $path;
        }
        $lines[] = '';
        $lines[] = 'Sitemap: ' . $this->generateUrl('app_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $response = new Response(implode("\n", $lines) . "\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        $response->setPublic();
        $response->setMaxAge(86400);

        return $response;
    }
}
