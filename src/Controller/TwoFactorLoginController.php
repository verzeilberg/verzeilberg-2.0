<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Email\Generator\CodeGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * "Send a new code" on the code form of the second login step (email codes). Under /2fa, so it is
 * reachable halfway the login (see access_control).
 */
class TwoFactorLoginController extends AbstractController
{
    private const SESSION_LAST_SENT = 'two_factor_email_last_sent';
    /** Seconds between two new codes, against flooding someone's mailbox */
    private const RESEND_INTERVAL = 30;

    public function __construct(
        private Security $security,
        #[Autowire(service: 'scheb_two_factor.security.email.code_generator')] private CodeGeneratorInterface $codeGenerator,
    ) {}

    #[Route('/2fa/resend', name: 'app_two_factor_resend', methods: ['POST'])]
    public function resend(Request $request): Response
    {
        $token = $this->security->getToken();
        $user = $token?->getUser();
        if (!$token instanceof TwoFactorTokenInterface || !$user instanceof User || !$user->isEmailAuthEnabled()) {
            return $this->redirectToRoute('2fa_login');
        }

        if (!$this->isCsrfTokenValid('two_factor_resend', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Je sessie is verlopen, probeer het opnieuw.');
            return $this->redirectToRoute('2fa_login');
        }

        $session = $request->getSession();
        $wait = (int) $session->get(self::SESSION_LAST_SENT, 0) + self::RESEND_INTERVAL - time();
        if ($wait > 0) {
            $this->addFlash('warning', sprintf('Wacht nog %d seconden voordat je een nieuwe code aanvraagt.', $wait));
            return $this->redirectToRoute('2fa_login');
        }

        // A fresh code (and a fresh 10 minutes), not the old one again
        $this->codeGenerator->generateAndSend($user);
        $session->set(self::SESSION_LAST_SENT, time());
        $this->addFlash('success', 'We hebben je een nieuwe code gestuurd. De vorige werkt niet meer.');

        return $this->redirectToRoute('2fa_login');
    }
}
