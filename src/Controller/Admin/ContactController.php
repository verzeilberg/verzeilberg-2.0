<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Contact;
use App\Entity\User;
use App\Service\ContactService;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/contact')]
#[IsGranted('ROLE_ADMIN')]
class ContactController extends AbstractController
{
    public function __construct(
        private ContactService $contactService
    ) {}

    #[Route('/', name: 'admin_contact_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $contacts = $this->contactService->getContacts($page, 20);

        return $this->render('admin/contact/index.html.twig', [
            'contacts' => $contacts,
        ]);
    }

    #[Route('/{id}', name: 'admin_contact_show', methods: ['GET'])]
    public function show(Contact $contact): Response
    {
        return $this->render('admin/contact/show.html.twig', [
            'contact' => $contact,
        ]);
    }

    #[Route('/{id}/reply', name: 'admin_contact_reply', methods: ['GET', 'POST'])]
    public function reply(Request $request, Contact $contact): Response
    {
        if ($request->isMethod('POST')) {
            $replyMessage = $request->request->get('reply_message', '');
            $user = $this->getUser();
            $adminName = $user instanceof User ? $user->getFullName() : (string) $user?->getUserIdentifier();

            if (trim($replyMessage) === '') {
                $this->addFlash('error', 'Het antwoord mag niet leeg zijn.');
            } else {
                $this->contactService->sendReplyEmail($contact, $replyMessage, $adminName);
                $this->addFlash('success', 'Antwoord is verzonden naar ' . $contact->getEmail());
                return $this->redirectToRoute('admin_contact_show', ['id' => $contact->getId()]);
            }
        }

        return $this->render('admin/contact/reply.html.twig', [
            'contact' => $contact,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_contact_delete', methods: ['POST'])]
    public function delete(Request $request, Contact $contact): Response
    {
        if ($this->isCsrfTokenValid('delete' . $contact->getId(), $request->request->get('_token'))) {
            $this->contactService->deleteContact($contact);
            $this->addFlash('success', 'Contactformulier verwijderd.');
        }

        return $this->redirectToRoute('admin_contact_index');
    }
}