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

#[Route('/admin/contact')]
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

    /** Deletes the ticked messages, or all of them ("all" = every message, also those on other pages) */
    #[Route('/bulk-delete', name: 'admin_contact_bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('contact_bulk_delete', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Ongeldig beveiligingstoken, probeer het opnieuw.');

            return $this->redirectToRoute('admin_contact_index');
        }

        $ids = $request->request->getBoolean('all')
            ? null
            : array_values(array_filter(array_map('intval', $request->request->all('ids'))));

        if ($ids === []) {
            $this->addFlash('error', 'Selecteer eerst een of meer contactformulieren.');

            return $this->redirectToRoute('admin_contact_index');
        }

        $deleted = $this->contactService->deleteContacts($ids);
        $this->addFlash('success', $deleted === 1 ? '1 contactformulier verwijderd.' : $deleted . ' contactformulieren verwijderd.');

        return $this->redirectToRoute('admin_contact_index');
    }

    #[Route('/{id}', name: 'admin_contact_show', methods: ['GET'])]
    public function show(Contact $contact): Response
    {
        if (!$contact->isRead()) {
            $contact->markRead();
            $this->contactService->saveContact($contact);
        }

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