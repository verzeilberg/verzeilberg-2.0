<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\BlogController as PublicBlogController;
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
use App\Service\ActivityNotifier;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/blog')]
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

            $this->addFlash('success', 'Bericht aangemaakt.');

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
            throw $this->createNotFoundException('Bericht niet gevonden');
        }

        return $this->render('admin/blog/show.html.twig', [
            'post' => $post,
        ]);
    }

    #[Route('/{id<\d+>}/edit', name: 'admin_blog_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id, UriSigner $uriSigner): Response
    {
        $post = $this->blogPostRepository->findForAdmin($id);

        if (!$post) {
            throw $this->createNotFoundException('Bericht niet gevonden');
        }

        $form = $this->createForm(BlogPostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleUploads($post, $request);

            $this->em->flush();

            $this->addFlash('success', 'Bericht opgeslagen.');

            return $this->redirectToRoute('admin_blog_edit', ['id' => $post->getId()]);
        }

        // A post that isn't online can be shown to someone with a signed link that works for a while
        $previewUntil = new \DateTimeImmutable(sprintf('+%d days', PublicBlogController::PREVIEW_DAYS));
        $previewUrl = $post->isOnline() ? null : $uriSigner->sign(
            $this->generateUrl('blog_preview', ['id' => $post->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            $previewUntil
        );

        return $this->render('admin/blog/edit.html.twig', [
            'form' => $form,
            'post' => $post,
            'previewUrl' => $previewUrl,
            'previewUntil' => $previewUntil,
        ]);
    }

    #[Route('/{id<\d+>}', name: 'admin_blog_delete', methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $post = $this->blogPostRepository->findForAdmin($id);

        if (!$post) {
            throw $this->createNotFoundException('Bericht niet gevonden');
        }

        $isAjax = $request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json';

        if (!$this->isCsrfTokenValid('delete' . $post->getId(), $request->request->get('_token'))) {
            // Nothing was deleted: don't report success
            if ($isAjax) {
                return new JsonResponse(['success' => false, 'message' => 'Ongeldig beveiligingstoken'], 403);
            }
            $this->addFlash('error', 'Het bericht kon niet worden verwijderd. Probeer het opnieuw.');

            return $this->redirectToRoute('admin_blog_index');
        }

        $this->em->remove($post);
        $this->em->flush();
        $this->addFlash('success', 'Bericht verwijderd.');

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
            return new JsonResponse(['success' => false, 'message' => 'Bericht niet gevonden']);
        }

        $status = (int) $request->request->get('status', BlogPost::STATUS_DRAFT);

        if (!in_array($status, [
            BlogPost::STATUS_DRAFT,
            BlogPost::STATUS_ONLINE,
            BlogPost::STATUS_OFFLINE,
            BlogPost::STATUS_ARCHIVED
        ])) {
            return new JsonResponse(['success' => false, 'message' => 'Ongeldige status']);
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
            return new JsonResponse(['success' => false, 'message' => 'Bericht niet gevonden']);
        }

        $file = $request->files->get('image');

        if (!$file instanceof UploadedFile) {
            return new JsonResponse(['success' => false, 'message' => 'Geen bestand geüpload']);
        }

        try {
            $image = new BlogImage();
            $image->setFilename($this->generateUniqueFilename($file));
            $image->setPath($this->getUploadPath('images'));
            $image->setMimeType($file->getMimeType());
            $image->setFileSize($file->getSize());

            $file->move($image->getFolder($this->publicDir()), $image->getFilename());

            // Try to get dimensions
            if (function_exists('getimagesize')) {
                $size = @getimagesize($image->getFilePath($this->publicDir()));
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
            return new JsonResponse(['success' => false, 'message' => 'Uploaden mislukt']);
        }
    }

    #[Route('/{id<\d+>}/image/{imageId<\d+>}/delete', name: 'admin_blog_image_delete', methods: ['POST'])]
    public function deleteImage(Request $request, int $id): JsonResponse
    {
        $post = $this->blogPostRepository->findForAdmin($id);

        if (!$post) {
            return new JsonResponse(['success' => false, 'message' => 'Bericht niet gevonden']);
        }

        $imageId = $request->attributes->get('imageId');
        $image = $this->em->getRepository(BlogImage::class)->find($imageId);
        
        if (!$image) {
            return new JsonResponse(['success' => false, 'message' => 'Afbeelding niet gevonden']);
        }

        $expectedTokenId = 'delete_image' . $image->getId();
        $receivedToken = $request->request->get('_token');
        
        if (!$this->isCsrfTokenValid($expectedTokenId, $receivedToken)) {
            return new JsonResponse(['success' => false, 'message' => 'Ongeldig beveiligingstoken']);
        }

        if ($post->getImages()->contains($image)) {
            $post->removeImage($image);
            $this->em->remove($image);
            $this->em->flush();

            // Delete physical file
            $filePath = $image->getFilePath($this->publicDir());
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
            return new JsonResponse(['success' => false, 'message' => 'Bericht niet gevonden']);
        }

        $order = json_decode($request->getContent(), true);

        if (!is_array($order)) {
            return new JsonResponse(['success' => false, 'message' => 'Ongeldige gegevens']);
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
            return new JsonResponse(['success' => false, 'message' => 'Bericht niet gevonden']);
        }

        $file = $request->files->get('file');

        if (!$file instanceof UploadedFile) {
            return new JsonResponse(['success' => false, 'message' => 'Geen bestand geüpload']);
        }

        try {
            $blogFile = new BlogFile();
            $blogFile->setFilename($this->generateUniqueFilename($file));
            $blogFile->setPath($this->getUploadPath('files'));
            $blogFile->setMimeType($file->getMimeType());
            $blogFile->setFileSize($file->getSize());

            $file->move($blogFile->getFolder($this->publicDir()), $blogFile->getFilename());

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
            return new JsonResponse(['success' => false, 'message' => 'Uploaden mislukt']);
        }
    }

    #[Route('/{id<\d+>}/file/{fileId<\d+>}/delete', name: 'admin_blog_file_delete', methods: ['POST'])]
    public function deleteFile(Request $request, int $id): JsonResponse
    {
        $post = $this->blogPostRepository->findForAdmin($id);

        if (!$post) {
            return new JsonResponse(['success' => false, 'message' => 'Bericht niet gevonden']);
        }

        $fileId = $request->attributes->get('fileId');
        $file = $this->em->getRepository(BlogFile::class)->find($fileId);
        
        if (!$file) {
            return new JsonResponse(['success' => false, 'message' => 'Bestand niet gevonden']);
        }

        if (!$this->isCsrfTokenValid('delete_file' . $file->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Ongeldig beveiligingstoken']);
        }

        if ($post->getFiles()->contains($file)) {
            $post->removeFile($file);
            $this->em->remove($file);
            $this->em->flush();

            $filePath = $file->getFilePath($this->publicDir());
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
            return new JsonResponse(['success' => false, 'message' => 'Bericht niet gevonden']);
        }

        $videoId = $request->request->get('videoId');
        $title = $request->request->get('title');

        if (empty($videoId)) {
            return new JsonResponse(['success' => false, 'message' => 'Video-ID is verplicht']);
        }

        $youtube = new BlogYouTube();
        $youtube->setVideoId($videoId);
        $youtube->setTitle($title ?: 'YouTube-video');
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
            return new JsonResponse(['success' => false, 'message' => 'Bericht niet gevonden']);
        }

        $youtubeId = $request->attributes->get('youtubeId');
        $youtube = $this->em->getRepository(BlogYouTube::class)->find($youtubeId);
        
        if (!$youtube) {
            return new JsonResponse(['success' => false, 'message' => 'YouTube-video niet gevonden']);
        }

        if (!$this->isCsrfTokenValid('delete_youtube' . $youtube->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Ongeldig beveiligingstoken']);
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

                $file->move($image->getFolder($this->publicDir()), $image->getFilename());
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

                $file->move($blogFile->getFolder($this->publicDir()), $blogFile->getFilename());

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

    /** Upload folder relative to public/, as stored on the record; created when missing */
    private function getUploadPath(string $type): string
    {
        $path = 'uploads/blog/' . $type;
        if (!is_dir($this->publicDir() . '/' . $path)) {
            mkdir($this->publicDir() . '/' . $path, 0777, true);
        }
        return $path;
    }

    private function publicDir(): string
    {
        return $this->getParameter('kernel.project_dir') . '/public';
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
    public function approveComment(BlogComment $comment, ActivityNotifier $activityNotifier): JsonResponse
    {
        $wasApproved = $comment->isApproved();
        $comment->setApproved(true);
        $this->em->flush();

        // The author of the comment it replies to hears of it once it is visible
        if (!$wasApproved) {
            $activityNotifier->commentPublished($comment);
        }

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
            return new JsonResponse(['success' => false, 'message' => 'Ongeldig beveiligingstoken']);
        }

        $this->em->remove($comment);
        $this->em->flush();

        return new JsonResponse(['success' => true]);
    }
}