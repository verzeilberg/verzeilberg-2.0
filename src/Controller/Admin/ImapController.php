<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\EmailAccount;
use App\Entity\EmailMessage;
use App\Form\EmailComposeType;
use App\Repository\EmailAccountRepository;
use App\Repository\EmailMessageRepository;
use App\Service\ImapService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/imap')]
#[IsGranted('ROLE_ADMIN')]
class ImapController extends AbstractController
{
    public function __construct(
        private EmailAccountRepository $emailAccountRepository,
        private EmailMessageRepository $emailMessageRepository,
        private ImapService $imapService,
        private PaginatorInterface $paginator
    ) {}

    #[Route('/', name: 'admin_imap_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = $request->query->get('search', '');

        $qb = $this->emailAccountRepository->createQueryBuilder('a')
            ->andWhere('a.enabled = true')
            ->orderBy('a.name', 'ASC');

        if ($search) {
            $qb->andWhere('a.name LIKE :search OR a.email LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $pagination = $this->paginator->paginate(
            $qb->getQuery(),
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('admin/imap/index.html.twig', [
            'pagination' => $pagination,
            'search' => $search,
        ]);
    }

    #[Route('/{id}/messages', name: 'admin_imap_messages', methods: ['GET'])]
    public function messages(Request $request, EmailAccount $account): Response
    {
        $folder = $request->query->get('folder', 'INBOX');
        $page = $request->query->getInt('page', 1);

        $messages = $this->emailMessageRepository->findByAccount($account, $folder, 50, ($page - 1) * 50);
        $total = $this->emailMessageRepository->countByAccount($account, $folder);

        $pagination = $this->paginator->paginate(
            $this->emailMessageRepository->createQueryBuilder('m')
                ->andWhere('m.account = :account')
                ->andWhere('m.folder = :folder')
                ->andWhere('m.isDeleted = false')
                ->setParameter('account', $account)
                ->setParameter('folder', $folder)
                ->orderBy('m.receivedAt', 'DESC'),
            $page,
            50
        );

        $foldersResult = $this->imapService->getFolders($account);
        $folders = $foldersResult['folders'] ?? ['INBOX'];

        return $this->render('admin/imap/messages.html.twig', [
            'account' => $account,
            'pagination' => $pagination,
            'currentFolder' => $folder,
            'folders' => $folders,
            'total' => $total,
        ]);
    }

    #[Route('/message/{id}', name: 'admin_imap_message_show', methods: ['GET'])]
    public function messageShow(EmailMessage $message): Response
    {
        $foldersResult = $this->imapService->getFolders($message->getAccount());
        $folders = $foldersResult['folders'] ?? ['INBOX'];

        return $this->render('admin/imap/message_show.html.twig', [
            'message' => $message,
            'folders' => $folders,
        ]);
    }

    #[Route('/message/{id}/read', name: 'admin_imap_message_read', methods: ['POST'])]
    public function messageRead(Request $request, EmailMessage $message): JsonResponse
    {
        if (!$this->isCsrfTokenValid('read' . $message->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid token']);
        }

        $this->imapService->markAsRead($message);

        return new JsonResponse(['success' => true]);
    }

    #[Route('/message/{id}/unread', name: 'admin_imap_message_unread', methods: ['POST'])]
    public function messageUnread(Request $request, EmailMessage $message): JsonResponse
    {
        if (!$this->isCsrfTokenValid('unread' . $message->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid token']);
        }

        $this->imapService->markAsUnread($message);

        return new JsonResponse(['success' => true]);
    }

    #[Route('/message/{id}/delete', name: 'admin_imap_message_delete', methods: ['POST'])]
    public function messageDelete(Request $request, EmailMessage $message): JsonResponse
    {
        if (!$this->isCsrfTokenValid('delete' . $message->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid token']);
        }

        $this->imapService->deleteMessage($message);

        return new JsonResponse(['success' => true]);
    }

    #[Route('/{id}/messages/bulk-delete', name: 'admin_imap_message_bulk_delete', methods: ['POST'])]
    public function messageBulkDelete(Request $request, EmailAccount $account): Response
    {
        if (!$this->isCsrfTokenValid('bulk_delete' . $account->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid token');
            return $this->redirectToRoute('admin_imap_messages', ['id' => $account->getId()]);
        }

        $messageIds = $request->request->get('messageIds', '');
        $folder = $request->request->get('folder', 'INBOX');

        if (!$messageIds) {
            $this->addFlash('error', 'No messages selected');
            return $this->redirectToRoute('admin_imap_messages', ['id' => $account->getId(), 'folder' => $folder]);
        }

        $ids = array_filter(array_map('intval', explode(',', $messageIds)));
        $deleted = 0;
        $failed = 0;

        foreach ($ids as $id) {
            $message = $this->emailMessageRepository->find($id);
            if ($message && $message->getAccount()->getId() === $account->getId()) {
                try {
                    $this->imapService->deleteMessage($message);
                    $deleted++;
                } catch (\Throwable $e) {
                    $failed++;
                }
            }
        }

        if ($deleted > 0) {
            $this->addFlash('success', "Permanently deleted {$deleted} message(s)");
        }
        if ($failed > 0) {
            $this->addFlash('error', "Failed to delete {$failed} message(s)");
        }

        return $this->redirectToRoute('admin_imap_messages', ['id' => $account->getId(), 'folder' => $folder]);
    }

    #[Route('/message/{id}/move', name: 'admin_imap_message_move', methods: ['POST'])]
    public function messageMove(Request $request, EmailMessage $message): JsonResponse
    {
        if (!$this->isCsrfTokenValid('move' . $message->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid token']);
        }

        $folder = $request->request->get('folder');
        if (!$folder) {
            return new JsonResponse(['success' => false, 'message' => 'Folder required']);
        }

        try {
            $this->imapService->moveMessage($message, $folder);
            return new JsonResponse(['success' => true]);
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    #[Route('/{id}/sync', name: 'admin_imap_sync', methods: ['POST'])]
    public function sync(Request $request, EmailAccount $account): JsonResponse
    {
        if (!$this->isCsrfTokenValid('sync' . $account->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid token']);
        }

        $folder = $request->request->get('folder', 'INBOX');
        $limit = (int) $request->request->get('limit', 100);

        $result = $this->imapService->syncAccount($account, $folder, $limit);

        return new JsonResponse($result);
    }

    #[Route('/{id}/folders', name: 'admin_imap_folders', methods: ['GET'])]
    public function folders(EmailAccount $account): JsonResponse
    {
        $result = $this->imapService->getFolders($account);
        return new JsonResponse($result);
    }

    #[Route('/compose', name: 'admin_imap_compose', methods: ['GET', 'POST'])]
    #[Route('/{id}/compose', name: 'admin_imap_compose_account', methods: ['GET', 'POST'])]
    public function compose(Request $request, ?EmailAccount $account = null): Response
    {
        $form = $this->createForm(EmailComposeType::class, ['account' => $account]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $account = $data['account'];

            $to = array_filter(array_map('trim', explode(',', $data['to'])));
            $cc = $data['cc'] ? array_filter(array_map('trim', explode(',', $data['cc']))) : [];
            $bcc = $data['bcc'] ? array_filter(array_map('trim', explode(',', $data['bcc']))) : [];

            // Handle file uploads
            $attachments = [];
            if ($data['attachments']) {
                foreach ($data['attachments'] as $file) {
                    if ($file instanceof UploadedFile && $file->isValid()) {
                        $attachments[] = [
                            'path' => $file->getPathname(),
                            'name' => $file->getClientOriginalName(),
                            'mimeType' => $file->getMimeType(),
                        ];
                    }
                }
            }

            $success = $this->imapService->sendEmail(
                $account,
                $to,
                $data['subject'],
                $data['bodyText'],
                $data['bodyHtml'] ?: null,
                $cc,
                $bcc,
                $attachments
            );

            if ($success) {
                $this->addFlash('success', 'Email sent successfully!');
            } else {
                $this->addFlash('error', 'Failed to send email. Check SMTP settings.');
            }

            return $this->redirectToRoute('admin_imap_compose');
        }

        return $this->render('admin/imap/compose.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/message/{id}/attachment/{partNumber<\d+>}', name: 'admin_imap_attachment_download', methods: ['GET'])]
    public function downloadAttachment(EmailMessage $message, int $partNumber): Response
    {
        $attachments = $message->getAttachments() ?? [];
        $attachmentArray = array_values($attachments);
        $arrayIndex = $partNumber - 1;
        
        if (!isset($attachmentArray[$arrayIndex])) {
            throw $this->createNotFoundException('Attachment index out of bounds');
        }
        
        $storedAttachment = $attachmentArray[$arrayIndex];
        $attachmentName = $storedAttachment['name'] ?? null;
        $attachmentId = $storedAttachment['id'] ?? null;

        // Debug: log what we're looking for
        error_log("DEBUG: Looking for attachment: name='$attachmentName', id='$attachmentId', partNumber=$partNumber, UID={$message->getUid()}, Folder={$message->getFolder()}");

        $attachment = $this->imapService->getAttachment(
            $message->getAccount(),
            $message->getFolder(),
            $message->getUid(),
            $partNumber,
            $attachmentName,
            $attachmentId
        );

        if (!$attachment) {
            throw $this->createNotFoundException('Attachment not found. Part number: ' . $partNumber . ', UID: ' . $message->getUid() . ', Folder: ' . $message->getFolder());
        }

        // If we have a filePath, stream the file directly to avoid memory issues
        if (isset($attachment['filePath']) && $attachment['filePath'] && file_exists($attachment['filePath'])) {
            $filePath = $attachment['filePath'];
            $fileSize = $attachment['size'] ?? filesize($filePath);
            $mimeType = $attachment['mimeType'] ?? 'application/octet-stream';
            $fileName = $attachment['name'] ?? 'attachment';

            return new StreamedResponse(
                function () use ($filePath) {
                    $handle = fopen($filePath, 'rb');
                    if ($handle) {
                        while (!feof($handle)) {
                            echo fread($handle, 8192);
                            flush();
                        }
                        fclose($handle);
                    }
                },
                200,
                [
                    'Content-Type' => $mimeType,
                    'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                    'Content-Length' => (string) $fileSize,
                    'Cache-Control' => 'private, must-revalidate',
                    'Pragma' => 'no-cache',
                ]
            );
        }

        // Fallback for content in memory (IMAP fallback)
        if (isset($attachment['content'])) {
            $response = new Response($attachment['content']);
            $response->headers->set('Content-Type', $attachment['mimeType']);
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $attachment['name'] . '"');
            $response->headers->set('Content-Length', (string) strlen($attachment['content']));
            return $response;
        }

        throw $this->createNotFoundException('Attachment content not available');
    }
}