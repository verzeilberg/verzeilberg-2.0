<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Mailer\DeferredMailer;
use App\Service\SiteStatus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Whether the site runs well, for a server without shell access: see SiteStatus.
 * Only for ROLE_ADMIN (an admin route without a route permission rule).
 */
#[Route('/admin/status')]
class StatusController extends AbstractController
{
    #[Route('', name: 'admin_status', methods: ['GET'])]
    public function index(SiteStatus $status): Response
    {
        return $this->render('admin/status/index.html.twig', [
            'sections' => $status->checks(),
            'errors' => $status->recentErrors(),
        ]);
    }

    /** Sends a mail to the logged-in admin: the only sure way to know mail (and so the login codes) works */
    #[Route('/test-mail', name: 'admin_status_test_mail', methods: ['POST'])]
    public function testMail(Request $request, DeferredMailer $mailer): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$this->isCsrfTokenValid('status_test_mail', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Je sessie is verlopen, probeer het opnieuw.');

            return $this->redirectToRoute('admin_status');
        }

        try {
            // Right away, not after the response: the page reports whether it worked
            $mailer->sendNow((new Email())
                ->from(new Address('noreply@verzeilberg.nl', 'Verzeilberg'))
                ->to($user->getEmail())
                ->subject('Testmail van Verzeilberg.nl')
                ->text("Deze mail is verstuurd vanaf de statuspagina in de admin.\nMail werkt: inlogcodes en notificaties komen aan."));
            $this->addFlash('success', sprintf('Er is een testmail verstuurd naar %s. Controleer of hij aankomt (kijk ook in de spammap).', $user->getEmail()));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Versturen mislukt: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_status');
    }
}
