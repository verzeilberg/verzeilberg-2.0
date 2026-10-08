<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Category;
use App\Entity\User;
use App\Entity\BlogPost;
use App\Entity\BlogComment;
use App\Entity\Setting;
use App\Repository\CategoryRepository;
use App\Repository\BlogPostRepository;
use App\Repository\SettingRepository;
use App\Service\SearchPhraseService;

use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BlogController extends AbstractController
{
    public function __construct(
        private BlogPostRepository $blogPostRepository,
        private CategoryRepository $categoryRepository,
        private SettingRepository $settingRepository,
        private EntityManagerInterface $em,
        private PaginatorInterface $paginator,
        private SearchPhraseService $searchPhraseService
    ) {}

    #[Route('/blog', name: 'blog_index', methods: ['GET'])]
    #[Route('/blog/page/{page<\d+>}', name: 'blog_index_paginated', methods: ['GET'])]
    public function index(Request $request, int $page = 1): Response
    {
        $posts = $this->blogPostRepository->findOnlinePosts();
        $categories = $this->categoryRepository->findAllActive();

        $pagination = $this->paginator->paginate(
            $posts,
            $page,
            5
        );

        return $this->render('blog/index.html.twig', [
            'pagination' => $pagination,
            'categories' => $categories,
            'currentCategory' => null,
        ]);
    }

    #[Route('/blog/category/{slug}', name: 'blog_category', methods: ['GET'])]
    #[Route('/blog/category/{slug}/page/{page<\d+>}', name: 'blog_category_paginated', methods: ['GET'])]
    public function category(string $slug, Request $request, int $page = 1): Response
    {
        $category = $this->categoryRepository->findBySlug($slug);

        if (!$category) {
            throw $this->createNotFoundException('Category not found');
        }

        $posts = $this->blogPostRepository->findByCategory($category);
        $categories = $this->categoryRepository->findAllActive();

        $pagination = $this->paginator->paginate(
            $posts,
            $page,
            5
        );

        return $this->render('blog/index.html.twig', [
            'pagination' => $pagination,
            'categories' => $categories,
            'currentCategory' => $category,
        ]);
    }

    #[Route('/blog/search', name: 'blog_search', methods: ['GET', 'POST'])]
    #[Route('/blog/search/page/{page<\d+>}', name: 'blog_search_paginated', methods: ['GET'])]
    public function search(Request $request, int $page = 1): Response
    {
        $searchTerm = $request->query->get('q', $request->request->get('q', ''));

        if (empty($searchTerm)) {
            return $this->redirectToRoute('blog_index');
        }

        // Track search phrase
        $this->searchPhraseService->trackSearch($searchTerm, 'blog');

        $posts = $this->blogPostRepository->search($searchTerm);
        $categories = $this->categoryRepository->findAllActive();

        $pagination = $this->paginator->paginate(
            $posts,
            $page,
            5
        );

        return $this->render('blog/search.html.twig', [
            'pagination' => $pagination,
            'categories' => $categories,
            'searchTerm' => $searchTerm,
        ]);
    }

    #[Route('/blog/{slug}', name: 'blog_detail', methods: ['GET', 'POST'])]
    public function detail(string $slug, Request $request): Response
    {
        $post = $this->blogPostRepository->findBySlug($slug);

        if (!$post) {
            throw $this->createNotFoundException('Blog post not found');
        }

        // Check if post is accessible
        if (!$post->isOnline() && !$this->isGranted('ROLE_AUTHOR')) {
            throw $this->createNotFoundException('Blog post not found');
        }

        // Handle comment submission
        if ($request->isMethod('POST') && $this->isGranted('IS_AUTHENTICATED_FULLY')) {
            $content = trim($request->request->get('comment', ''));
            $csrfToken = $request->request->get('_token');
            
            if (!empty($content) && $this->isCsrfTokenValid('comment_' . $post->getId(), $csrfToken)) {
                $comment = new BlogComment();
                $comment->setContent($content);
                $comment->setBlogPost($post);
                $author = $this->getUser();
                $comment->setAuthor($author instanceof User ? $author : null);
                
                // Check comment moderation setting
                $moderationEnabled = $this->settingRepository->getBooleanValue(Setting::COMMENT_MODERATION, true);
                $comment->setApproved(!$moderationEnabled); // Auto-approve if moderation is disabled
                
                $this->em->persist($comment);
                $this->em->flush();

                if ($moderationEnabled) {
                    $this->addFlash('success', 'Comment submitted for moderation. It will appear once approved.');
                } else {
                    $this->addFlash('success', 'Comment added successfully.');
                }
                
                return $this->redirectToRoute('blog_detail', ['slug' => $slug]);
            }
        }

        $categories = $this->categoryRepository->findAllActive();
        
        // Get only approved comments for display
        $comments = $post->getRootComments();
        $comments = array_filter($comments, fn(BlogComment $c) => $c->isApproved());

        // Set OG meta tags
        $firstImage = $post->getImages()->first();
        $ogImage = $firstImage ? $firstImage->getPublicPath() : null;

        return $this->render('blog/detail.html.twig', [
            'post' => $post,
            'categories' => $categories,
            'comments' => $comments,
            'ogImage' => $ogImage,
        ]);
    }
}