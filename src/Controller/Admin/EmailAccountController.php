<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\EmailAccount;
use App\Form\EmailAccountType;
use App\Repository\EmailAccountRepository;
use App\Service\ImapService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/email-accounts')]
#[IsGranted('ROLE_ADMIN')]
class EmailAccountController extends AbstractController
{
    public function __construct(
        private EmailAccountRepository $emailAccountRepository,
        private ImapService $imapService,
        private EntityManagerInterface $em,
        private PaginatorInterface $paginator
    ) {}

    #[Route('/', name: 'admin_email_account_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = $request->query->get('search', '');

        $qb = $this->emailAccountRepository->createQueryBuilder('a')
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

        return $this->render('admin/email_account/index.html.twig', [
            'pagination' => $pagination,
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'admin_email_account_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $account = new EmailAccount();

        $form = $this->createForm(EmailAccountType::class, $account);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($account);
            $this->em->flush();

            $this->addFlash('success', 'Email account created successfully.');

            return $this->redirectToRoute('admin_email_account_edit', ['id' => $account->getId()]);
        }

        return $this->render('admin/email_account/new.html.twig', [
            'form' => $form,
            'account' => $account,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_email_account_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EmailAccount $account): Response
    {
        $form = $this->createForm(EmailAccountType::class, $account);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            $this->addFlash('success', 'Email account updated successfully.');

            return $this->redirectToRoute('admin_email_account_edit', ['id' => $account->getId()]);
        }

        return $this->render('admin/email_account/edit.html.twig', [
            'form' => $form,
            'account' => $account,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_email_account_delete', methods: ['POST'])]
    public function delete(Request $request, EmailAccount $account): Response
    {
        if ($this->isCsrfTokenValid('delete' . $account->getId(), $request->request->get('_token'))) {
            $this->em->remove($account);
            $this->em->flush();
            $this->addFlash('success', 'Email account deleted successfully.');
        }

        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true]);
        }

        return $this->redirectToRoute('admin_email_account_index');
    }

    #[Route('/{id}/test', name: 'admin_email_account_test', methods: ['POST'])]
    public function test(Request $request, EmailAccount $account): JsonResponse
    {
        if (!$this->isCsrfTokenValid('test' . $account->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid token']);
        }

        $result = $this->imapService->testConnection($account);

        return new JsonResponse($result);
    }

    #[Route('/{id}/test-smtp', name: 'admin_email_account_test_smtp', methods: ['POST'])]
    public function testSmtp(Request $request, EmailAccount $account): JsonResponse
    {
        if (!$this->isCsrfTokenValid('test_smtp' . $account->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid token']);
        }

        try {
            if ($account->isUseImapForSmtp()) {
                $smtpHost = $account->getImapHost();
                $smtpPort = $account->getImapPort() === 993 ? 465 : 587;
                $smtpEncryption = $account->getEncryption() === 'ssl' ? 'ssl' : 'tls';
                $smtpUsername = $account->getUsername();
                $smtpPassword = $account->getPassword();
            } elseif ($account->getSmtpHost() && $account->getSmtpUsername()) {
                $smtpHost = $account->getSmtpHost();
                $smtpPort = $account->getSmtpPort();
                $smtpEncryption = $account->getSmtpEncryption();
                $smtpUsername = $account->getSmtpUsername();
                $smtpPassword = $account->getSmtpPassword();
            } else {
                return new JsonResponse(['success' => false, 'message' => 'SMTP not configured. Enable "Use IMAP for SMTP" or configure dedicated SMTP settings.']);
            }

            $tls = match ($smtpEncryption) {
                'ssl' => true,
                'tls' => false,
                default => null,
            };

            $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
                $smtpHost,
                $smtpPort,
                $tls
            );
            $transport->setUsername($smtpUsername);
            $transport->setPassword($smtpPassword);

            $mailer = new \Symfony\Component\Mailer\Mailer($transport);

            $email = (new \Symfony\Component\Mime\Email())
                ->from(new \Symfony\Component\Mime\Address($account->getEmail(), $account->getName()))
                ->to(new \Symfony\Component\Mime\Address($account->getEmail()))
                ->subject('SMTP Test from Verzeilberg')
                ->text('This is a test email to verify SMTP configuration.');

            $mailer->send($email);

            return new JsonResponse(['success' => true, 'message' => 'Test email sent successfully']);
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}