<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\MediaLibrary;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Media manager: the uploaded blog and event files (public/uploads), and checks that files and database records match.
 */
#[Route('/admin/media')]
class MediaController extends AbstractController
{
    public function __construct(private MediaLibrary $media)
    {
    }

    /** Browse a folder, or search it with ?q= (including sub folders) */
    #[Route('/', name: 'admin_media_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        try {
            $folder = $this->media->normalize((string) $request->query->get('folder', ''));
            $entries = $search !== '' ? $this->media->search($search, $folder) : $this->media->listFolder($folder);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('admin_media_index');
        }

        return $this->render('admin/media/index.html.twig', [
            'folder' => $folder,
            'search' => $search,
            'entries' => $entries,
            'breadcrumb' => $this->media->breadcrumb($folder),
        ]);
    }

    /** Called by the shared delete modal (?path=…); refuses files that are still in use */
    #[Route('/delete', name: 'admin_media_delete', methods: ['POST'])]
    public function delete(Request $request): JsonResponse
    {
        $path = (string) $request->query->get('path', '');
        if (!$this->isCsrfTokenValid('media_delete' . $path, (string) $request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Ongeldig beveiligingstoken, herlaad de pagina.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $this->media->delete($path);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $this->addFlash('success', sprintf('"%s" is verwijderd.', $path));

        return new JsonResponse(['success' => true]);
    }

    /** Files nobody uses, and records whose file is gone */
    #[Route('/check', name: 'admin_media_check', methods: ['GET'])]
    public function check(): Response
    {
        return $this->render('admin/media/check.html.twig', [
            'unusedFiles' => $this->media->findUnusedFiles(),
            'missingFiles' => $this->media->findMissingFiles(),
        ]);
    }

    #[Route('/check/unused-files', name: 'admin_media_delete_unused', methods: ['POST'])]
    public function deleteUnused(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('media_delete_unused', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Ongeldig beveiligingstoken, probeer het opnieuw.');

            return $this->redirectToRoute('admin_media_check');
        }

        $paths = array_values(array_map('strval', $request->request->all('paths')));
        $deleted = $this->media->deleteUnusedFiles($paths);
        $this->addFlash($deleted > 0 ? 'success' : 'warning', sprintf('%d van %d geselecteerde bestand(en) verwijderd.', $deleted, count($paths)));

        return $this->redirectToRoute('admin_media_check');
    }

    #[Route('/check/missing-files', name: 'admin_media_remove_missing', methods: ['POST'])]
    public function removeMissing(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('media_remove_missing', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Ongeldig beveiligingstoken, probeer het opnieuw.');

            return $this->redirectToRoute('admin_media_check', ['_fragment' => 'missing']);
        }

        $keys = array_values(array_map('strval', $request->request->all('records')));
        $removed = $this->media->removeMissingRecords($keys);
        $this->addFlash($removed > 0 ? 'success' : 'warning', sprintf('%d van %d geselecteerde record(s) verwijderd.', $removed, count($keys)));

        return $this->redirectToRoute('admin_media_check', ['_fragment' => 'missing']);
    }
}
