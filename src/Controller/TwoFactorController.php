<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Two-factor login on the profile. It is always on: by default a code by email; here the user can
 * switch to an authenticator app (and back), see how many backup codes are left, make new ones and
 * forget trusted devices. The login step itself is scheb/2fa (config/packages/scheb_2fa.yaml).
 */
#[Route('/profile/two-factor')]
class TwoFactorController extends AbstractController
{
    /** Secret of a setup that isn't confirmed yet; it only lands on the user after a correct code */
    private const SESSION_PENDING_SECRET = 'two_factor_pending_secret';
    /** New backup codes, shown once on the next page */
    private const SESSION_NEW_CODES = 'two_factor_new_backup_codes';

    private const BACKUP_CODE_COUNT = 10;

    public function __construct(
        private TotpAuthenticatorInterface $totpAuthenticator,
        private EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'app_two_factor', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->currentUser();
        $session = $request->getSession();

        // Backup codes that were just made: show them this one time
        $newCodes = $session->get(self::SESSION_NEW_CODES);
        $session->remove(self::SESSION_NEW_CODES);

        if ($user->isTotpAuthenticationEnabled()) {
            return $this->render('auth/two_factor.html.twig', [
                'user' => $user,
                'newCodes' => $newCodes,
            ]);
        }

        // Not on yet: a new secret for this setup (kept while the user scans it and types a code)
        $secret = $session->get(self::SESSION_PENDING_SECRET);
        if (!is_string($secret)) {
            $secret = $this->totpAuthenticator->generateSecret();
            $session->set(self::SESSION_PENDING_SECRET, $secret);
        }

        $qrContent = $this->totpAuthenticator->getQRContent($this->withSecret($user, $secret));
        $qrCode = (new Builder(writer: new SvgWriter(), data: $qrContent, size: 220, margin: 0))->build();

        return $this->render('auth/two_factor.html.twig', [
            'user' => $user,
            'newCodes' => null,
            'qrCode' => $qrCode->getDataUri(),
            // Grouped per four characters, for typing it into an app by hand
            'secret' => trim(chunk_split($secret, 4, ' ')),
        ]);
    }

    #[Route('/enable', name: 'app_two_factor_enable', methods: ['POST'])]
    public function enable(Request $request): Response
    {
        $user = $this->currentUser();
        $session = $request->getSession();
        $secret = $session->get(self::SESSION_PENDING_SECRET);

        if (!$this->isCsrfTokenValid('two_factor_enable', $request->request->getString('_token')) || !is_string($secret)) {
            $this->addFlash('error', 'Je sessie is verlopen, scan de code opnieuw.');
            return $this->redirectToRoute('app_two_factor');
        }
        if ($user->isTotpAuthenticationEnabled()) {
            return $this->redirectToRoute('app_two_factor');
        }

        $code = preg_replace('/\s+/', '', $request->request->getString('code'));
        if (!$this->totpAuthenticator->checkCode($this->withSecret($user, $secret), $code)) {
            $this->addFlash('error', 'Die code klopt niet. Controleer of je de code hierboven hebt gescand en vul de code in die je app nu toont.');
            return $this->redirectToRoute('app_two_factor');
        }

        $codes = $this->generateBackupCodes();
        $user->useAuthenticatorApp($secret, $codes);
        $this->em->flush();

        $session->remove(self::SESSION_PENDING_SECRET);
        $session->set(self::SESSION_NEW_CODES, $codes);
        $this->addFlash('success', 'Klaar: voortaan log je in met een code uit je app in plaats van een code per e-mail.');

        return $this->redirectToRoute('app_two_factor');
    }

    #[Route('/backup-codes', name: 'app_two_factor_backup_codes', methods: ['POST'])]
    public function regenerateBackupCodes(Request $request): Response
    {
        $user = $this->currentUser();
        if (!$this->isCsrfTokenValid('two_factor_backup_codes', $request->request->getString('_token')) || !$user->isTotpAuthenticationEnabled()) {
            $this->addFlash('error', 'Je sessie is verlopen, probeer het opnieuw.');
            return $this->redirectToRoute('app_two_factor');
        }

        $codes = $this->generateBackupCodes();
        $user->setBackupCodes($codes);
        $this->em->flush();

        $request->getSession()->set(self::SESSION_NEW_CODES, $codes);
        $this->addFlash('success', 'Je hebt nieuwe back-upcodes; de oude werken niet meer.');

        return $this->redirectToRoute('app_two_factor');
    }

    #[Route('/forget-devices', name: 'app_two_factor_forget_devices', methods: ['POST'])]
    public function forgetTrustedDevices(Request $request): Response
    {
        $user = $this->currentUser();
        if (!$this->isCsrfTokenValid('two_factor_forget_devices', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Je sessie is verlopen, probeer het opnieuw.');
            return $this->redirectToRoute('app_two_factor');
        }

        $user->forgetTrustedDevices();
        $this->em->flush();
        $this->addFlash('success', 'Elk vertrouwd apparaat vraagt bij de volgende keer inloggen weer om een code.');

        return $this->redirectToRoute('app_two_factor');
    }

    #[Route('/use-email', name: 'app_two_factor_use_email', methods: ['POST'])]
    public function useEmailCodes(Request $request): Response
    {
        $user = $this->currentUser();
        if (!$this->isCsrfTokenValid('two_factor_use_email', $request->request->getString('_token')) || !$user->isTotpAuthenticationEnabled()) {
            $this->addFlash('error', 'Je sessie is verlopen, probeer het opnieuw.');
            return $this->redirectToRoute('app_two_factor');
        }

        // Someone at an unlocked computer shouldn't be able to change it: ask for a current code
        $code = trim($request->request->getString('code'));
        if (!$this->totpAuthenticator->checkCode($user, preg_replace('/\s+/', '', $code)) && !$user->isBackupCode($code)) {
            $this->addFlash('error', 'Die code klopt niet; je logt nog steeds in met je app.');
            return $this->redirectToRoute('app_two_factor');
        }

        $user->useEmailCodes();
        $this->em->flush();
        $this->addFlash('success', 'Voortaan krijg je je inlogcode per e-mail. Je kunt Verzeilberg.nl uit je app verwijderen.');

        return $this->redirectToRoute('app_two_factor');
    }

    private function currentUser(): User
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    /**
     * The user as the authenticator should see it with a secret that isn't saved (yet).
     */
    private function withSecret(User $user, string $secret): User
    {
        $copy = clone $user;
        $copy->useAuthenticatorApp($secret, []);

        return $copy;
    }

    /**
     * @return list<string> e.g. "4f2a-9c1e": 8 random hex characters, grouped for reading
     */
    private function generateBackupCodes(): array
    {
        $codes = [];
        while (count($codes) < self::BACKUP_CODE_COUNT) {
            $code = bin2hex(random_bytes(4));
            $codes[$code] = substr($code, 0, 4) . '-' . substr($code, 4);
        }

        return array_values($codes);
    }
}
