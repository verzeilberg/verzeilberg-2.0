<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Entity\Event;
use App\Entity\BlogImage;
use App\Form\EventType;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/event')]
class EventController extends AbstractController
{
    public function __construct(
        private EventRepository $eventRepository,
        private EntityManagerInterface $em,
        private PaginatorInterface $paginator,
        private SluggerInterface $slugger
    ) {}

    #[Route('/', name: 'admin_event_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $status = $request->query->get('status', 'all');
        $search = $request->query->get('search', '');

        $qb = $this->eventRepository->createQueryBuilder('e')
            ->leftJoin('e.category', 'c')
            ->addSelect('c')
            ->leftJoin('e.eventImage', 'i')
            ->addSelect('i')
            ->orderBy('e.eventStartDate', 'ASC');

        switch ($status) {
            case 'past':
                $qb->andWhere('e.eventEndDate < :today')
                    ->setParameter('today', new \DateTimeImmutable('today'));
                break;
            case 'ongoing':
                $today = new \DateTimeImmutable('today');
                $qb->andWhere('e.eventStartDate <= :today')
                    ->andWhere('e.eventEndDate >= :today')
                    ->setParameter('today', $today);
                break;
            case 'upcoming':
                $qb->andWhere('e.eventStartDate >= :today')
                    ->setParameter('today', new \DateTimeImmutable('today'));
                break;
            case 'all':
            default:
                // No date filter for 'all'
                break;
        }

        if ($search) {
            $qb->andWhere('e.title LIKE :search OR e.text LIKE :search OR e.labelText LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $upcomingCount = (int) $this->eventRepository->createQueryBuilder('e')
            ->select('count(e.id)')
            ->andWhere('e.eventStartDate >= :today')
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->getQuery()->getSingleScalarResult();

        $ongoingCount = (int) $this->eventRepository->createQueryBuilder('e')
            ->select('count(e.id)')
            ->andWhere('e.eventStartDate <= :today')
            ->andWhere('e.eventEndDate >= :today')
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->getQuery()->getSingleScalarResult();

        $pastCount = (int) $this->eventRepository->createQueryBuilder('e')
            ->select('count(e.id)')
            ->andWhere('e.eventEndDate < :today')
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->getQuery()->getSingleScalarResult();

        $pagination = $this->paginator->paginate(
            $qb->getQuery(),
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('admin/event/index.html.twig', [
            'pagination' => $pagination,
            'currentStatus' => $status,
            'search' => $search,
            'upcomingCount' => $upcomingCount,
            'ongoingCount' => $ongoingCount,
            'pastCount' => $pastCount,
        ]);
    }

    #[Route('/new', name: 'admin_event_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $event = new Event();
        $event->setEventStartDate(new \DateTimeImmutable('today'));
        $event->setEventEndDate(new \DateTimeImmutable('today'));

        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleImageUpload($event, $form);
            $this->em->persist($event);
            $this->em->flush();

            $this->addFlash('success', 'Event aangemaakt.');

            return $this->redirectToRoute('admin_event_index');
        }

        return $this->render('admin/event/new.html.twig', [
            'form' => $form,
            'event' => $event,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_event_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Event $event): Response
    {
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleImageUpload($event, $form);
            $this->em->flush();

            $this->addFlash('success', 'Event opgeslagen.');

            return $this->redirectToRoute('admin_event_index');
        }

        return $this->render('admin/event/edit.html.twig', [
            'form' => $form,
            'event' => $event,
        ]);
    }

    private function handleImageUpload(Event $event, \Symfony\Component\Form\FormInterface $form): void
    {
        /** @var UploadedFile|null $file */
        $file = $form->get('eventImageFile')->getData();

        // Check for cropped image data (base64)
        $croppedData = $form->get('event_image_cropped')->getData();
        
        if (!$file instanceof UploadedFile && !$croppedData) {
            return;
        }

        // Validate image dimensions (server-side)
        $tempPath = null;
        if ($file instanceof UploadedFile) {
            $tempPath = $file->getRealPath();
        } elseif ($croppedData && preg_match('/^data:image\/(\w+);base64,/', $croppedData, $matches)) {
            $base64Data = preg_replace('/^data:image\/\w+;base64,/', '', $croppedData);
            $binaryData = base64_decode($base64Data);
            $tempPath = sys_get_temp_dir() . '/crop_check_' . uniqid() . '.' . ($matches[1] === 'jpeg' ? 'jpg' : $matches[1]);
            file_put_contents($tempPath, $binaryData);
        }

        if ($tempPath && function_exists('getimagesize')) {
            $size = @getimagesize($tempPath);
            if ($size) {
                $minWidth = 1200;
                $minHeight = 675;
                if ($size[0] < $minWidth || $size[1] < $minHeight) {
                    $this->addFlash('error', 
                        sprintf('Afbeelding te klein (%dx%dpx). Minimum: %dx%dpx voor scherpe weergave.', 
                            $size[0], $size[1], $minWidth, $minHeight)
                    );
                    if ($tempPath !== $file->getRealPath()) {
                        @unlink($tempPath);
                    }
                    return;
                }
            }
        }

        if ($tempPath && $tempPath !== ($file->getRealPath() ?: '')) {
            @unlink($tempPath);
        }

        try {
            // Remove old image if exists
            if ($event->getEventImage()) {
                $oldImage = $event->getEventImage();
                $filePath = $oldImage->getFilePath($this->publicDir());
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
                $this->em->remove($oldImage);
            }

            $image = new BlogImage();
            
            if ($croppedData) {
                // Handle base64 cropped image
                if (preg_match('/^data:image\/(\w+);base64,/', $croppedData, $matches)) {
                    $extension = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
                    $base64Data = preg_replace('/^data:image\/\w+;base64,/', '', $croppedData);
                    $binaryData = base64_decode($base64Data);
                    
                    $filename = $this->generateUniqueFilename($file) . '.' . $extension;
                    $image->setFilename($filename);
                    $image->setPath($this->getUploadPath('events'));
                    $image->setMimeType('image/' . $extension);
                    $image->setFileSize(strlen($binaryData));
                    
                    // Save the cropped image
                    $uploadPath = $image->getFolder($this->publicDir());
                    if (!is_dir($uploadPath)) {
                        mkdir($uploadPath, 0777, true);
                    }
                    file_put_contents($uploadPath . '/' . $filename, $binaryData);
                    
                    // Get dimensions
                    $size = @getimagesize($uploadPath . '/' . $filename);
                    if ($size) {
                        $image->setWidth($size[0]);
                        $image->setHeight($size[1]);
                    }
                }
            } elseif ($file instanceof UploadedFile) {
                // Original file upload (fallback) - resize if too large
                $image->setFilename($this->generateUniqueFilename($file));
                $image->setPath($this->getUploadPath('events'));
                $image->setMimeType($file->getMimeType());
                $image->setFileSize($file->getSize());

                $file->move($image->getFolder($this->publicDir()), $image->getFilename());

                // Resize if larger than 2560px wide (for retina displays)
                $this->resizeImageIfNeeded($image->getFilePath($this->publicDir()), 2560, 1440, 95);

                // Try to get dimensions
                if (function_exists('getimagesize')) {
                    $size = @getimagesize($image->getFilePath($this->publicDir()));
                    if ($size) {
                        $image->setWidth($size[0]);
                        $image->setHeight($size[1]);
                    }
                }
            }

            $image->setImageType('original');
            $event->setEventImage($image);

            $this->em->persist($image);
        } catch (FileException $e) {
            $this->addFlash('error', 'Uploaden van afbeelding mislukt: ' . $e->getMessage());
        }
    }

    private function resizeImageIfNeeded(string $filePath, int $maxWidth, int $maxHeight, int $quality = 95): void
    {
        if (!function_exists('getimagesize') || !function_exists('imagecreatetruecolor')) {
            return; // GD not available
        }

        $size = @getimagesize($filePath);
        if (!$size) {
            return;
        }

        $width = $size[0];
        $height = $size[1];
        $mime = $size['mime'];

        // No resize needed
        if ($width <= $maxWidth && $height <= $maxHeight) {
            return;
        }

        // Calculate new dimensions maintaining aspect ratio
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = (int)($width * $ratio);
        $newHeight = (int)($height * $ratio);

        // Create source image
        $source = null;
        switch ($mime) {
            case 'image/jpeg':
                $source = @imagecreatefromjpeg($filePath);
                break;
            case 'image/png':
                $source = @imagecreatefrompng($filePath);
                break;
            case 'image/webp':
                $source = @imagecreatefromwebp($filePath);
                break;
        }

        if (!$source) {
            return;
        }

        // Create destination image
        $dest = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG/WebP
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($dest, false);
            imagesavealpha($dest, true);
        }

        // Resize
        imagecopyresampled($dest, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Save with quality
        switch ($mime) {
            case 'image/jpeg':
                imagejpeg($dest, $filePath, $quality);
                break;
            case 'image/png':
                imagepng($dest, $filePath, 9);
                break;
            case 'image/webp':
                imagewebp($dest, $filePath, $quality);
                break;
        }

        imagedestroy($source);
        imagedestroy($dest);
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
        $path = 'uploads/events/' . $type;
        if (!is_dir($this->publicDir() . '/' . $path)) {
            mkdir($this->publicDir() . '/' . $path, 0777, true);
        }
        return $path;
    }

    private function publicDir(): string
    {
        return $this->getParameter('kernel.project_dir') . '/public';
    }

    #[Route('/{id}/delete', name: 'admin_event_delete', methods: ['POST'])]
    public function delete(Request $request, Event $event): Response
    {
        if ($this->isCsrfTokenValid('delete' . $event->getId(), $request->request->get('_token'))) {
            if ($event->getEventImage()) {
                $image = $event->getEventImage();
                $filePath = $image->getFilePath($this->publicDir());
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
                $this->em->remove($image);
            }
            $this->em->remove($event);
            $this->em->flush();
            $this->addFlash('success', 'Event verwijderd.');
        }

        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true]);
        }

        return $this->redirectToRoute('admin_event_index');
    }
}