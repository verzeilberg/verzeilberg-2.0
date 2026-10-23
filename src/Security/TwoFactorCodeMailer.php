<?php

declare(strict_types=1);

namespace App\Security;

use Scheb\TwoFactorBundle\Mailer\AuthCodeMailerInterface;
use Scheb\TwoFactorBundle\Model\Email\TwoFactorInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Sends the code of the second login step by email (scheb/2fa-email), in the site's own email style.
 */
class TwoFactorCodeMailer implements AuthCodeMailerInterface
{
    /** Matches User::EMAIL_CODE_LIFETIME */
    private const LIFETIME_MINUTES = 10;

    public function __construct(private MailerInterface $mailer)
    {
    }

    public function sendAuthCode(TwoFactorInterface $user): void
    {
        $code = (string) $user->getEmailAuthCode();

        $email = (new TemplatedEmail())
            ->from(new Address('noreply@verzeilberg.nl', 'Verzeilberg.nl'))
            ->to($user->getEmailAuthRecipient())
            ->subject($code . ' is je inlogcode voor Verzeilberg.nl')
            ->htmlTemplate('email/two_factor_code.html.twig')
            ->context([
                'user' => $user,
                'code' => $code,
                'lifetimeMinutes' => self::LIFETIME_MINUTES,
            ]);

        $this->mailer->send($email);
    }
}
