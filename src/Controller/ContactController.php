<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Contact;
use App\Form\ContactType;
use App\Service\ContactService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ContactController extends AbstractController
{
    public function __construct(
        private ContactService $contactService
    ) {}

    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $contact = new Contact();
        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->contactService->saveContact($contact);
            
            // Send confirmation email to sender
            try {
                $this->contactService->sendConfirmationEmail($contact);
            } catch (\Throwable $e) {
                // Log error but don't show to user
            }
            
            // Send notification email to admin
            try {
                $this->contactService->sendNotificationEmail($contact);
            } catch (\Throwable $e) {
                // Log error but don't show to user
            }

            $this->addFlash('success', 'Uw bericht is verstuurd. Wij nemen zo spoedig mogelijk contact met u op.');
            
            return $this->redirectToRoute('app_contact');
        }

        return $this->render('contact/index.html.twig', [
            'form' => $form,
        ]);
    }
}