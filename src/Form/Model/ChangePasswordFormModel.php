<?php

declare(strict_types=1);

namespace App\Form\Model;

use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints as Assert;

/** Changing the password of the logged-in user: the current one first, then the new one twice */
class ChangePasswordFormModel
{
    #[Assert\NotBlank(message: 'Vul je huidige wachtwoord in')]
    #[UserPassword(message: 'Dit is niet je huidige wachtwoord')]
    public ?string $currentPassword = null;

    #[Assert\NotBlank(message: 'Vul een nieuw wachtwoord in')]
    #[Assert\Length(min: 8, max: 4096, minMessage: 'Je wachtwoord moet minstens {{ limit }} tekens lang zijn')]
    #[Assert\NotEqualTo(propertyPath: 'currentPassword', message: 'Je nieuwe wachtwoord moet anders zijn dan je huidige')]
    public ?string $plainPassword = null;

    #[Assert\NotBlank(message: 'Herhaal je nieuwe wachtwoord')]
    #[Assert\Expression(
        expression: 'this.plainPassword === this.confirmPassword',
        message: 'De wachtwoorden moeten gelijk zijn.'
    )]
    public ?string $confirmPassword = null;
}
