<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/** Inactive (retired) and not yet approved accounts can't log in, also not through a remember-me cookie */
class UserChecker implements UserCheckerInterface
{
    /** The login page offers a new confirmation link with this message */
    public const UNCONFIRMED_MESSAGE = 'Bevestig eerst je e-mailadres via de link in de mail die we je stuurden.';

    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && $user->isPending() && !$user->isEmailVerified()) {
            throw new CustomUserMessageAccountStatusException(self::UNCONFIRMED_MESSAGE);
        }

        if ($user instanceof User && $user->isPending()) {
            throw new CustomUserMessageAccountStatusException('Je account wacht nog op goedkeuring door een beheerder.');
        }

        if ($user instanceof User && !$user->isActive()) {
            throw new CustomUserMessageAccountStatusException('Dit account is gedeactiveerd.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
