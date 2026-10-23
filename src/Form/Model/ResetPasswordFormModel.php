<?php

declare(strict_types=1);

namespace App\Form\Model;

use Symfony\Component\Validator\Constraints as Assert;

class ResetPasswordFormModel
{
    #[Assert\NotBlank(message: 'Vul een wachtwoord in')]
    #[Assert\Length(min: 8, max: 4096, minMessage: 'Je wachtwoord moet minstens {{ limit }} tekens lang zijn')]
    public ?string $plainPassword = null;

    #[Assert\NotBlank(message: 'Herhaal je wachtwoord')]
    #[Assert\Expression(
        expression: 'this.plainPassword === this.confirmPassword',
        message: 'De wachtwoorden moeten gelijk zijn.'
    )]
    public ?string $confirmPassword = null;
}