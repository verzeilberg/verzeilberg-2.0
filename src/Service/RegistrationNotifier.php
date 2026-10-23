<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * The mails around a registration: the confirmation link to the new user, the site's owner once the address
 * is confirmed (the account then waits for approval), and the user when an admin approved or rejected it.
 * A mail that can't be sent is logged.
 */
class RegistrationNotifier
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private LoggerInterface $logger,
        private string $notificationEmail,
    ) {
    }

    public function confirmEmail(User $user, string $confirmLink): void
    {
        $this->send((new Email())
            ->to($user->getEmail())
            ->subject('Bevestig je e-mailadres')
            ->html($this->twig->render('email/registration_confirm.html.twig', ['user' => $user, 'confirmLink' => $confirmLink])));
    }

    public function userRegistered(User $user): void
    {
        $this->send((new Email())
            ->to($this->notificationEmail)
            ->subject('Nieuwe registratie wacht op goedkeuring: ' . $user->getFullName())
            ->html($this->twig->render('email/registration_notification.html.twig', ['user' => $user])));
    }

    public function userApproved(User $user): void
    {
        $this->send((new Email())
            ->to($user->getEmail())
            ->subject('Je account is goedgekeurd')
            ->html($this->twig->render('email/registration_approved.html.twig', ['user' => $user])));
    }

    public function userRejected(User $user): void
    {
        $this->send((new Email())
            ->to($user->getEmail())
            ->subject('Je registratie is niet goedgekeurd')
            ->html($this->twig->render('email/registration_rejected.html.twig', ['user' => $user])));
    }

    private function send(Email $email): void
    {
        try {
            $this->mailer->send($email->from(new Address('noreply@verzeilberg.nl', 'Verzeilberg')));
        } catch (\Throwable $e) {
            $this->logger->error('Mailing about a registration failed: ' . $e->getMessage());
        }
    }
}
