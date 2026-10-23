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
use App\Service\ActivityNotifier;
use App\Service\CommentNotifier;
use App\Service\SearchPhraseService;
use App\Service\ShareImage;

use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Attribute\Route;

class BlogController extends AbstractController
{
    /** Posts per page in the lists: a grid of three columns, like the events */
    private const PER_PAGE = 12;

    private const COMMENT_MAX_LENGTH = 2000;

    /** How long a preview link of a post that isn't online works (see preview()) */
    public const PREVIEW_DAYS = 14;

    /** Replies are indented up to this depth (0 = top level); deeper replies stay at this level */
    private const COMMENT_MAX_DEPTH = 2;

    public function __construct(
        private BlogPostRepository $blogPostRepository,
        private CategoryRepository $categoryRepository,
        private SettingRepository $settingRepository,
        private EntityManagerInterface $em,
        private PaginatorInterface $paginator,
        private SearchPhraseService $searchPhraseService,
        private ShareImage $shareImage,
        private CommentNotifier $commentNotifier,
        private ActivityNotifier $activityNotifier,
    ) {}

    /** RSS feed of the newest posts; priority 1, so "feed.xml" isn't taken for a post's slug */
    #[Route('/blog/feed.xml', name: 'blog_feed', methods: ['GET'], format: 'xml', priority: 1)]
    public function feed(): Response
    {
        $posts = $this->blogPostRepository->createPublicListQuery()->setMaxResults(20)->getQuery()->getResult();

        $response = $this->render('blog/feed.xml.twig', ['posts' => $posts]);
        $response->headers->set('Content-Type', 'application/rss+xml; charset=UTF-8');
        $response->setPublic();
        $response->setMaxAge(900);

        return $response;
    }

    #[Route('/blog', name: 'blog_index', methods: ['GET'])]
    #[Route('/blog/page/{page<\d+>}', name: 'blog_index_paginated', methods: ['GET'])]
    public function index(Request $request, int $page = 1): Response
    {
        // The old site paged with ?page=2
        if ($request->attributes->get('_route') === 'blog_index' && $request->query->getInt('page') > 1) {
            return $this->redirectToRoute('blog_index_paginated', ['page' => $request->query->getInt('page')], 301);
        }

        $categories = $this->categoriesWithPosts();

        // (The whole list, not findOnlinePosts(): that one stops at 10 posts)
        $pagination = $this->paginator->paginate($this->blogPostRepository->createPublicListQuery(), $page, self::PER_PAGE);

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
            throw $this->createNotFoundException('Categorie niet gevonden');
        }

        $categories = $this->categoriesWithPosts();

        $pagination = $this->paginator->paginate($this->blogPostRepository->createPublicListQuery($category), $page, self::PER_PAGE);

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

        $categories = $this->categoriesWithPosts();

        $pagination = $this->paginator->paginate($this->blogPostRepository->createPublicListQuery(null, (string) $searchTerm), $page, self::PER_PAGE);

        return $this->render('blog/search.html.twig', [
            'pagination' => $pagination,
            'categories' => $categories,
            'searchTerm' => $searchTerm,
        ]);
    }

    /**
     * A post that isn't online yet, for whoever has the signed link from the admin edit page. The link stops
     * working after PREVIEW_DAYS days; the page has no comments and stays out of search engines.
     */
    #[Route('/blog/preview/{id<\d+>}', name: 'blog_preview', methods: ['GET'], priority: 1)]
    public function preview(int $id, Request $request, UriSigner $uriSigner): Response
    {
        $post = $uriSigner->checkRequest($request) ? $this->blogPostRepository->findOneBy(['id' => $id, 'deleted' => false]) : null;
        if (!$post) {
            throw $this->createNotFoundException('Deze voorbeeldlink is ongeldig of verlopen.');
        }
        if ($post->isOnline()) {
            return $this->redirectToRoute('blog_detail', ['slug' => $post->getSlug()]);
        }

        $response = $this->render('blog/detail.html.twig', [
            'post' => $post,
            'preview' => true,
            'recentPosts' => array_slice($this->blogPostRepository->findOnlinePosts(5), 0, 5),
            'categories' => $this->categoriesWithPosts(),
            'comments' => [],
            'ogImage' => null,
            'ogDescription' => '',
            'commentCount' => 0,
            'commentDraft' => '',
            'replyToId' => null,
            'commentMaxLength' => self::COMMENT_MAX_LENGTH,
            'commentMaxDepth' => self::COMMENT_MAX_DEPTH,
        ]);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    #[Route('/blog/{slug}', name: 'blog_detail', methods: ['GET', 'POST'])]
    public function detail(string $slug, Request $request): Response
    {
        $post = $this->blogPostRepository->findBySlug($slug);

        if (!$post) {
            throw $this->createNotFoundException('Bericht niet gevonden');
        }

        // Check if post is accessible
        if (!$post->isOnline() && !$this->isGranted('ROLE_AUTHOR')) {
            throw $this->createNotFoundException('Bericht niet gevonden');
        }

        $viewer = $this->getUser() instanceof User ? $this->getUser() : null;

        // Handle comment submission; on an error the page shows again with the text still in the form
        $commentDraft = '';
        $replyToId = null;
        if ($request->isMethod('POST') && $this->isGranted('IS_AUTHENTICATED_FULLY')) {
            $commentDraft = trim((string) $request->request->get('comment', ''));
            $replyToId = $request->request->getInt('parent_id') ?: null;
            $parent = $replyToId ? $this->findReplyTarget($post, $replyToId, $viewer) : null;

            $error = match (true) {
                !$this->isCsrfTokenValid('comment_' . $post->getId(), $request->request->get('_token')) => 'Je sessie is verlopen. Plaats je reactie opnieuw.',
                $commentDraft === '' => 'Schrijf eerst een reactie.',
                mb_strlen($commentDraft) > self::COMMENT_MAX_LENGTH => sprintf('Een reactie mag maximaal %d tekens lang zijn.', self::COMMENT_MAX_LENGTH),
                $replyToId !== null && $parent === null => 'De reactie waarop je reageerde bestaat niet meer.',
                default => null,
            };

            if ($error !== null) {
                $this->addFlash('error', $error);
            } else {
                $comment = new BlogComment();
                $comment->setContent($commentDraft);
                $comment->setBlogPost($post);
                $comment->setAuthor($viewer);
                $comment->setParent($parent);

                $moderationEnabled = $this->settingRepository->getBooleanValue(Setting::COMMENT_MODERATION, true);
                $comment->setApproved(!$moderationEnabled);

                $this->em->persist($comment);
                $this->em->flush();
                $this->commentNotifier->commentPosted($comment);
                $this->activityNotifier->commentPublished($comment);

                $this->addFlash('success', $moderationEnabled
                    ? 'Bedankt! Je reactie wacht op goedkeuring; tot die tijd kun alleen jij hem zien.'
                    : 'Je reactie is geplaatst.');

                return $this->redirect($this->generateUrl('blog_detail', ['slug' => $slug]) . '#comment-' . $comment->getId());
            }
        }

        $categories = $this->categoriesWithPosts();

        // Top-level comments the viewer may see; the template applies the same check to the replies
        $comments = array_values(array_filter(
            $post->getRootComments(),
            fn(BlogComment $c) => $c->isVisibleTo($viewer)
        ));

        // Picture for link previews (og:image), e.g. when the post is shared in WhatsApp
        $firstImage = $post->getImages()->first();
        $ogImage = $firstImage ? $this->shareImage->forImage($firstImage) : null;
        $ogDescription = $this->plainSummary($post->getIntroText() ?: $post->getContent());

        // The newest other posts, for the sidebar (like the upcoming events next to an event)
        $recentPosts = array_slice(array_values(array_filter(
            $this->blogPostRepository->findOnlinePosts(6),
            fn (BlogPost $other) => $other->getId() !== $post->getId()
        )), 0, 5);

        return $this->render('blog/detail.html.twig', [
            'post' => $post,
            'recentPosts' => $recentPosts,
            'categories' => $categories,
            'comments' => $comments,
            'ogImage' => $ogImage,
            'ogDescription' => $ogDescription,
            'commentCount' => $post->getPublishedCommentCount(),
            'commentDraft' => $commentDraft,
            'replyToId' => $replyToId,
            'commentMaxLength' => self::COMMENT_MAX_LENGTH,
            'commentMaxDepth' => self::COMMENT_MAX_DEPTH,
        ]);
    }

    /**
     * The comment a reply goes under: on this post and visible to the writer. Below the deepest
     * level a reply goes next to the comment instead, so the thread stays readable on a phone.
     */
    private function findReplyTarget(BlogPost $post, int $commentId, ?User $viewer): ?BlogComment
    {
        $parent = $this->em->getRepository(BlogComment::class)->find($commentId);
        if (!$parent || $parent->getBlogPost()->getId() !== $post->getId() || !$parent->isVisibleTo($viewer)) {
            return null;
        }

        while ($parent->getDepth() >= self::COMMENT_MAX_DEPTH && $parent->getParent() !== null) {
            $parent = $parent->getParent();
        }

        return $parent;
    }

    /**
     * Stored HTML as one line of plain text for a link preview, cut at a word.
     */
    private function plainSummary(string $html, int $maxLength = 200): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['<br', '</p>'], [' <br', ' </p>'], $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Legacy texts can hold a literal "\n"; also the non-breaking spaces from &nbsp;
        $text = trim(preg_replace('/(\\\\[rn]|\s|\x{00A0})+/u', ' ', $text));

        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        $cut = mb_substr($text, 0, $maxLength);
        $lastSpace = mb_strrpos($cut, ' ');

        return rtrim($lastSpace ? mb_substr($cut, 0, $lastSpace) : $cut, ' ,.;:') . '…';
    }

    /**
     * Categories for the sidebar, without the ones that have no visible posts.
     *
     * @return Category[]
     */
    private function categoriesWithPosts(): array
    {
        return array_values(array_filter(
            $this->categoryRepository->findAllActive(),
            fn (Category $category) => $category->getOnlinePostCount() > 0
        ));
    }
}
