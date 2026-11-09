<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\BlogVideo;
use App\Repository\BlogPostRepository;
use App\Repository\BlogVideoRepository;
use App\Service\BlogVideoStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Videos of a blog post that only logged in visitors can watch: uploaded in chunks (see BlogVideoStorage and
 * js/modules/blog/video-upload.js), and deleted.
 */
#[Route('/admin/blog/{id<\d+>}/video')]
class BlogVideoController extends AbstractController
{
    public function __construct(
        private BlogPostRepository $blogPostRepository,
        private BlogVideoRepository $blogVideoRepository,
        private BlogVideoStorage $storage,
        private EntityManagerInterface $em,
    ) {}

    /**
     * One chunk of a video. Fields: uploadId (32 hex), offset (bytes so far), chunk (the file part);
     * with the last chunk also last=1, size (total bytes), name (original file name) and title.
     */
    #[Route('/upload', name: 'admin_blog_video_upload', methods: ['POST'])]
    public function upload(Request $request, int $id): JsonResponse
    {
        $post = $this->blogPostRepository->findForAdmin($id);
        if (!$post) {
            return new JsonResponse(['success' => false, 'message' => 'Bericht niet gevonden'], 404);
        }
        if (!$this->isCsrfTokenValid('blog_video_upload' . $post->getId(), $request->request->getString('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Ongeldig beveiligingstoken. Herlaad de pagina.'], 403);
        }

        $uploadId = $request->request->getString('uploadId');
        $chunk = $request->files->get('chunk');
        if (!BlogVideoStorage::isValidUploadId($uploadId) || !$chunk instanceof UploadedFile || !$chunk->isValid()) {
            return new JsonResponse(['success' => false, 'message' => 'Het stuk video kwam niet goed aan.'], 400);
        }

        try {
            $received = $this->storage->appendChunk($uploadId, $request->request->getInt('offset'), $chunk);
            if (!$request->request->getBoolean('last')) {
                return new JsonResponse(['success' => true, 'received' => $received]);
            }

            $stored = $this->storage->finish($uploadId, $request->request->getInt('size'));
        } catch (\RuntimeException $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $video = new BlogVideo($post, $stored['filename'], $stored['mimeType'], $stored['size']);
        $video->setTitle($request->request->getString('title'));
        $video->setOriginalName(mb_substr(basename($request->request->getString('name')), 0, 255) ?: null);
        $video->setSortOrder($post->getVideos()->count());
        $post->addVideo($video);
        $this->em->persist($video);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'done' => true, 'id' => $video->getId()]);
    }

    #[Route('/{videoId<\d+>}/delete', name: 'admin_blog_video_delete', methods: ['POST'])]
    public function delete(Request $request, int $id, int $videoId): JsonResponse
    {
        $video = $this->blogVideoRepository->find($videoId);
        if (!$video || $video->getBlogPost()->getId() !== $id) {
            return new JsonResponse(['success' => false, 'message' => 'Video niet gevonden'], 404);
        }
        if (!$this->isCsrfTokenValid('delete_video' . $video->getId(), $request->request->getString('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Ongeldig beveiligingstoken'], 403);
        }

        // The file goes with it (BlogVideoFileListener)
        $video->getBlogPost()->removeVideo($video);
        $this->em->remove($video);
        $this->em->flush();

        return new JsonResponse(['success' => true]);
    }
}
