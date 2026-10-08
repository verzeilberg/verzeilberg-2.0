<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\BlogPost;
use App\Entity\BlogComment;
use App\Entity\Category;
use App\Entity\BlogImage;
use App\Entity\BlogFile;
use App\Entity\BlogYouTube;
use App\Form\BlogPostType;
use App\Form\BlogImageType;
use App\Form\BlogFileType;
use App\Form\BlogYouTubeType;
use App\Repository\BlogPostRepository;
use App\Repository\BlogCommentRepository;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/blog')]
#[IsGranted('ROLE_ADMIN')]
class BlogController extends AbstractController
{
    public function __construct(
        private BlogPostRepository $blogPostRepository,
        private CategoryRepository $categoryRepository,
        private BlogCommentRepository $blogCommentRepository,
        private EntityManagerInterface $em,
        private PaginatorInterface $paginator,
        private SluggerInterface $slugger
    ) {}

    #[Route('/', name: 'admin_blog_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $status = $request->query->get('status', 'all');
        $categoryId = $request->query->get('category');

        $qb = $this->blogPostRepository->createQueryBuilder('bp')
            ->leftJoin('bp.categories', 'c')
            ->addSelect('c')
            ->orderBy('bp.dateCreated', 'DESC');

        if ($status !== 'all') {
            $statusMap = [
                'draft' => BlogPost::STATUS_DRAFT,
                'online' => BlogPost::STATUS_ONLINE,
                'offline' => BlogPost::STATUS_OFFLINE,
                'archived' => BlogPost::STATUS_ARCHIVED,
            ];
            if (isset($statusMap[$status])) {
                $qb->andWhere('bp.status = :status')
                    ->setParameter('status', $statusMap[$status]);
            }
        }

        if ($categoryId) {
            $qb->andWhere('c.id = :categoryId')
                ->setParameter('categoryId', $categoryId);
        }

        $query = $qb->getQuery();
        $pagination = $this->paginator->paginate($query, $request->query->getInt('page', 1), 20);

        $categories = $this->categoryRepository->findAllActive();

        return $this->render('admin/blog/index.html.twig', [
            'pagination' => $pagination,
            'categories' => $categories,
            'currentStatus' => $status,
            'currentCategory' => $categoryId,
        ]);
    }

    #[Route('/new', name: 'admin_blog_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $post = new BlogPost();
        $post->setDateOnline(new \DateTimeImmutable());
        $post->setTimeOnline(new \DateTimeImmutable('09:00:00'));

        $form = $this->createForm(BlogPostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // The slug is set by BlogPostType before validation
            $this->handleUploads($post, $request);

            $this->em->persist($post);
            $this->em->flush();

            $this->addFlash('success', 'Blog post created successfully.');

            return $this->redirectToRoute('admin_blog_edit', ['id' => $post->getId()]);
        }

        return $this->render('admin/blog/new.html.twig', [
            'form' => $form,
            'post' => $post,
        ]);
    }

    #[Route('/{id<\d+>}', name: 'admin_blog_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $post = $this->blogPostRepository->findForAdmin($id);

        if (!$post) {
            throw $this->createNotFoundException('Blog post not found');
        }

        return $this->render('admin/blog/show.html.twig', [
            'post' => $post,
        ]);
    }

    #[Route('/{id<\d+>}/edit', name: 'admin_blog_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $post = $this->blogPostRepository->findForAdmin($id);

        if (!$post) {
            throw $this->createNotFoundException('Blog post not found');
        }

        $form = $this->createForm(BlogPostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleUploads($post, $request);

            $this->em->flush();

            $this->addFlash('success', 'Blog post updated successfully.');

            return $this->redirectToRoute('admin_blog_edit', ['id' => $post->getId()]);
        }

        return $this->render('admin/blog/edit.html.twig', [
            'form' => $form,
            'post' => $post,
        ]);
    }

    #[Route('/{id<\d+>}', name: 'admin_blog_delete', methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $post = $this->blogPostRepository->findForAdmin($id);

        if (!$post) {
            throw $this->createNotFoundException('Blog post not found');
        }

        $isAjax = $request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json';

        if (!$this->isCsrfTokenValid('delete' . $post->getId(), $request->request->get('_token'))) {
            // Nothing was deleted: don't report success
            if ($isAjax) {
                return new JsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
            }
            $this->addFlash('error', 'The blog post could not be deleted. Please try again.');

            return $this->redirectToRoute('admin_blog_index');
        }

        $this->em->remove($post);
        $this->em->flush();
        $this->addFlash('success', 'Blog post deleted successfully.');

        if ($isAjax) {
            return new JsonResponse(['success' => true]);
        }

        return $this->redirectToRoute('admin_blog_index');
    }

    #[Route('/{id<\d+>}/status', name: 'admin_blog_status', methods: ['POST'])]
    public function changeStatus(Request $request, int $id): JsonResponse
    {
        $post = $this->blogPostRepository->findForAdmin($id);

        if (!$post) {
            return new JsonResponse(['success' => false, 'message' => 'Blog post not found']);
        }

        $status = (int) $request->request->get('status', BlogPost::STATUS_DRAFT);

        if (!in_array($status, [
            BlogPost::STATUS_DRAFT,
            BlogPost::STATUS_ONLINE,
            BlogPost::STATUS_OFFLINE,
            BlogPost::STATUS_ARCHIVED
        ])) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid status']);
        }

        $post->setStatus($status);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'status' => $post->getStatus()]);
    }

    #[Route('/{id<\d+>}/image/upload', name: 'admin_blog_image_upload', methods: ['POST'])]
    public function uploadImage(Request $request, int $id): JsonResponse
    {
        $post = $this->blogPostRepository->findForAdmin($id);

        if (!$post) {
            return new JsonResponse(['success' => false, 'message' => 'Blog post not found']);
        }

        $file = $request->files->get('image');

        if (!$file instanceof UploadedFile) {
            return new JsonResponse(['success' => false, 'message' => 'No file uploaded']);
        }

        try {
            $image = new BlogImage();
            $image->setFilename($this->generateUniqueFilename($file));
            $image->setPath($this->getUploadPath('images'));
            $image->setMimeType($file->getMimeType());
            $image->setFileSize($file->getSize());

            $file->move($image->getPath(), $image->getFilename());

            // Try to get dimensions
            if (function_exists('getimagesize')) {
                $size = @getimagesize($image->getPath() . '/' . $image->getFilename());
                if ($size) {
                    $image->setWidth($size[0]);
                    $image->setHeight($size[1]);
                }
            }

            $image->setImageType('original');
            $post->addImage($image);

            $this->em->persist($image);
            $this->em->flush();

            return new JsonResponse([
                'success' => true,
                'image' => [
                    'id' => $image->getId(),
                    'name' => $image->getName(),
                    'url' => $image->getPublicPath(),
                    'sortOrder' => $image->getSortOrder(),
                ]
            ]);
        } catch (FileException $e) {
            return new JsonResponse(['success' => false, 'message' => 'Upload failed']);
        }
    }

    #[Route('/{id<\d+>}/image/{imageId<\d+>}/delete', name: 'admin_blog_image_delete', methods: ['POST'])]
    public function deleteImage(Request $request, int $id): JsonResponse
    {
        $post = $this->blogPostRepository->findForAdmin($id);

        if (!$post) {
            return new JsonResponse(['success' => false, 'message' => 'Blog post not found']);
        }

        $imageId = $request->attributes->get('imageId');
        $image = $this->em->getRepository(BlogImage::class)->find($imageId);
        
        if (!$image) {
            return new JsonResponse(['success' => false, 'message' => 'Image not found']);
        }

        $expectedTokenId = 'delete_image' . $image->getId();
        $receivedToken = $request->request->get('_token');
        error_log("CSRF Debug - Expected: $expectedTokenId, Received: $receivedToken");
        error_log("CSRF Debug - Token valid: " . ($this->isCsrfTokenValid($expectedTokenId, $receivedToken) ? 'true' : 'false'));
        
        if (!$this->isCsrfTokenValid($expectedTokenId, $receivedToken)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid token']);
        }

        if ($post->getImages()->contains($image)) {
            $post->removeImage($image);
            $this->em->remove($image);
            $this->em->flush();

            // Delete physical file
            $filePath = $image->getPath() . '/' . $image->getFilename();
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        return new JsonResponse(['success' => true]);
    }

    #[Route('/{id<\d+>}/images/reorder', name: 'admin_blog_images_reorder', methods: ['POST'])]
    public function reorderImages(Request $request, int $id): JsonResponse
    {
        $post = $this->blogPostRepository->findForAdmin($id);

        if (!$post) {
            return new JsonResponse(['success' => false, 'message' => 'Blog post not found']);
        }

        $order = json_decode($request->getContent(), true);

        if (!is_array($order)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid data']);
        }

        foreach ($order as $index => $imageId) {
            $image = $this->em->getRepository(BlogImage::class)->find($imageId);
            if ($image && $post->getImages()->contains($image)) {
                $image->setSortOrder($index);
            }
        }

        $this->em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/{id<\d+>}/file/upload', name: 'admin_blog_file_upload', methods: ['POST'])]
    public function uploadFile(Request $request, int $id): JsonResponse
    {
        $post = $this->blogPostRepository->findForAdmin($id);

        if (!$post) {
            return new JsonResponse(['success' => false, 'message' => 'Blog post not found']);
        }

        $file = $request->files->get('file');

        if (!$file instanceof UploadedFile) {
            return new JsonResponse(['success' => false, 'message' => 'No file uploaded']);
        }

        try {
            $blogFile = new BlogFile();
            $blogFile->setFilename($this->generateUniqueFilename($file));
            $blogFile->setPath($this->getUploadPath('files'));
            $blogFile->setMimeType($file->getMimeType());
            $blogFile->setFileSize($file->getSize());

            $file->move($blogFile->getPath(), $blogFile->getFilename());

            $post->addFile($blogFile);

            $this->em->persist($blogFile);
            $this->em->flush();

            return new JsonResponse([
                'success' => true,
                'file' => [
                    'id' => $blogFile->getId(),
                    'name' => $blogFile->getName(),
                    'url' => $blogFile->getPublicPath(),
                    'size' => $blogFile->getHumanReadableSize(),
                ]
            ]);
        } catch (FileException $e) {
            return new JsonResponse(['success' => false, 'message' => 'Upload failed']);
        }
    }

    #[Route('/{id<\d+>}/file/{fileId<\d+>}/delete', name: 'admin_blog_file_delete', methods: ['POST'])]
    public function deleteFile(Request $request, int $id): JsonResponse
    {
        $post = $this->blogPostRepository->findForAdmin($id);

        if (!$post) {
            return new JsonResponse(['success' => false, 'message' => 'Blog post not found']);
        }

        $fileId = $request->attributes->get('fileId');
        $file = $this->em->getRepository(BlogFile::class)->find($fileId);
        
        if (!$file) {
            return new JsonResponse(['success' => false, 'message' => 'File not found']);
        }

        if (!$this->isCsrfTokenValid('delete_file' . $file->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid token']);
        }

        if ($post->getFiles()->contains($file)) {
            $post->removeFile($file);
            $this->em->remove($file);
            $this->em->flush();

            $filePath = $file->getPath() . '/' . $file->getFilename();
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        return new JsonResponse(['success' => true]);
    }

    #[Route('/{id<\d+>}/youtube/add', name: 'admin_blog_youtube_add', methods: ['POST'])]
    public function addYouTube(Request $request, int $id): JsonResponse
    {
        $post = $this->blogPostRepository->findForAdmin($id);

        if (!$post) {
            return new JsonResponse(['success' => false, 'message' => 'Blog post not found']);
        }

        $videoId = $request->request->get('videoId');
        $title = $request->request->get('title');

        if (empty($videoId)) {
            return new JsonResponse(['success' => false, 'message' => 'Video ID required']);
        }

        $youtube = new BlogYouTube();
        $youtube->setVideoId($videoId);
        $youtube->setTitle($title ?: 'YouTube Video');
        $youtube->setSortOrder($post->getYouTubes()->count());

        $post->addYouTube($youtube);

        $this->em->persist($youtube);
        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'youtube' => [
                'id' => $youtube->getId(),
                'title' => $youtube->getTitle(),
                'videoId' => $youtube->getVideoId(),
                'embedUrl' => $youtube->getEmbedUrl(),
                'thumbnailUrl' => $youtube->getThumbnailUrl(),
            ]
        ]);
    }

    #[Route('/{id<\d+>}/youtube/{youtubeId<\d+>}/delete', name: 'admin_blog_youtube_delete', methods: ['POST'])]
    public function deleteYouTube(Request $request, int $id): JsonResponse
    {
        $post = $this->blogPostRepository->findForAdmin($id);

        if (!$post) {
            return new JsonResponse(['success' => false, 'message' => 'Blog post not found']);
        }

        $youtubeId = $request->attributes->get('youtubeId');
        $youtube = $this->em->getRepository(BlogYouTube::class)->find($youtubeId);
        
        if (!$youtube) {
            return new JsonResponse(['success' => false, 'message' => 'YouTube not found']);
        }

        if (!$this->isCsrfTokenValid('delete_youtube' . $youtube->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid token']);
        }

        if ($post->getYouTubes()->contains($youtube)) {
            $post->removeYouTube($youtube);
            $this->em->remove($youtube);
            $this->em->flush();
        }

        return new JsonResponse(['success' => true]);
    }

    private function handleUploads(BlogPost $post, Request $request): void
    {
        // Handle image uploads via form
        $images = $request->files->get('blog_post')['images'] ?? [];
        foreach ($images as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $image = new BlogImage();
                $image->setFilename($this->generateUniqueFilename($file));
                $image->setPath($this->getUploadPath('images'));
                $image->setMimeType($file->getMimeType());
                $image->setFileSize($file->getSize());

                $file->move($image->getPath(), $image->getFilename());
                $image->setImageType('original');

                $post->addImage($image);
                $this->em->persist($image);
            }
        }

        // Handle file uploads via form
        $files = $request->files->get('blog_post')['files'] ?? [];
        foreach ($files as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $blogFile = new BlogFile();
                $blogFile->setFilename($this->generateUniqueFilename($file));
                $blogFile->setPath($this->getUploadPath('files'));
                $blogFile->setMimeType($file->getMimeType());
                $blogFile->setFileSize($file->getSize());

                $file->move($blogFile->getPath(), $blogFile->getFilename());

                $post->addFile($blogFile);
                $this->em->persist($blogFile);
            }
        }
    }

    private function generateUniqueFilename(UploadedFile $file): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        return $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
    }

    private function getUploadPath(string $type): string
    {
        $path = $this->getParameter('kernel.project_dir') . '/public/uploads/blog/' . $type;
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        return $path;
    }

    #[Route('/comments', name: 'admin_blog_comments', methods: ['GET'])]
    public function comments(Request $request): Response
    {
        $status = $request->query->get('status');

        // Normalize 'all' to null for showing all comments
        if ($status === 'all') {
            $status = null;
        }
        
        $qb = $this->blogCommentRepository->createQueryBuilder('bc')
            ->leftJoin('bc.blogPost', 'bp')
            ->addSelect('bp')
            ->leftJoin('bc.author', 'u')
            ->addSelect('u')
            ->orderBy('bc.dateCreated', 'DESC');

        if ($status === 'pending') {
            $qb->andWhere('bc.isApproved = false');
        } elseif ($status === 'approved') {
            $qb->andWhere('bc.isApproved = true');
        }
        // null or 'all' = show all comments (no filter)

        $pagination = $this->paginator->paginate($qb, $request->query->getInt('page', 1), 20);

        // Get pending count for badge
        $pendingCount = $this->blogCommentRepository->createQueryBuilder('bc')
            ->select('COUNT(bc.id)')
            ->andWhere('bc.isApproved = false')
            ->getQuery()
            ->getSingleScalarResult();

        $displayStatus = $status ?? 'all';
        
        return $this->render('admin/blog/comments.html.twig', [
            'pagination' => $pagination,
            'currentStatus' => $displayStatus,
            'pendingCount' => $pendingCount,
        ]);
    }

    #[Route('/comment/{id}/approve', name: 'admin_blog_comment_approve', methods: ['POST'])]
    public function approveComment(BlogComment $comment): JsonResponse
    {
        $comment->setApproved(true);
        $this->em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/comment/{id}/reject', name: 'admin_blog_comment_reject', methods: ['POST'])]
    public function rejectComment(BlogComment $comment): JsonResponse
    {
        $comment->setApproved(false);
        $this->em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/comment/{id}', name: 'admin_blog_comment_delete', methods: ['POST'])]
    public function deleteComment(Request $request, BlogComment $comment): JsonResponse
    {
        if (!$this->isCsrfTokenValid('delete_comment' . $comment->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid token']);
        }

        $this->em->remove($comment);
        $this->em->flush();

        return new JsonResponse(['success' => true]);
    }
}