<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\BlogVideoRepository;
use App\Security\AdminRouteAccess;
use App\Service\BlogVideoStorage;
use App\Service\NameMasker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Plays a blog post's video, for logged in visitors only. There is no other URL to the file (it's outside public/).
 *
 * To make downloading harder: the video is only handed to a <video> player on this site. Opening the URL in a tab
 * (Sec-Fetch-Dest: document) or from another site (Sec-Fetch-Site: cross-site) is refused, and browsers are told
 * not to keep a copy. Someone logged in who records their screen can't be stopped; no website can.
 */
class BlogVideoController extends AbstractController
{
    /** Request destinations that mean "the player on a page": video, and empty for range requests of some browsers */
    private const PLAYER_DESTINATIONS = ['video', 'empty'];

    #[Route('/blog/video/{id<\d+>}', name: 'blog_video_stream', methods: ['GET', 'HEAD'], priority: 2)]
    public function play(int $id, Request $request, BlogVideoRepository $videos, BlogVideoStorage $storage, NameMasker $nameMasker, AdminRouteAccess $adminRouteAccess): Response
    {
        // Not logged in: as if it doesn't exist, so nothing about the video gives itself away
        $video = $nameMasker->canSeeNames() ? $videos->find($id) : null;
        if ($video === null) {
            throw $this->createNotFoundException();
        }

        $post = $video->getBlogPost();
        if (!$post->isVisibleOn(new \DateTimeImmutable()) && !$adminRouteAccess->canAccess('admin_blog_edit')) {
            throw $this->createNotFoundException();
        }

        // Browsers that send these headers tell us who asks; older ones send none and are let through
        $destination = $request->headers->get('Sec-Fetch-Dest');
        $site = $request->headers->get('Sec-Fetch-Site');
        if (($destination !== null && !in_array($destination, self::PLAYER_DESTINATIONS, true)) || $site === 'cross-site') {
            return new Response('Deze video is alleen op de site zelf te bekijken.', Response::HTTP_FORBIDDEN, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $path = $storage->path($video);
        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        // Range requests (seeking) are handled by BinaryFileResponse
        $response = new BinaryFileResponse($path, 200, ['Content-Type' => $video->getMimeType()], false, ResponseHeaderBag::DISPOSITION_INLINE, false, false);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, 'video');
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
