<?php

declare(strict_types=1);

namespace App\Form\Model;

use Symfony\Component\Validator\Constraints as Assert;

class ResetPasswordFormModel
{
    #[Assert\NotBlank(message: 'Please enter a password')]
    #[Assert\Length(min: 8, max: 4096, minMessage: 'Your password should be at least {{ limit }} characters')]
    public ?string $plainPassword = null;

    #[Assert\NotBlank(message: 'Please confirm your password')]
    #[Assert\Expression(
        expression: 'this.plainPassword === this.confirmPassword',
        message: 'The password fields must match.'
    )]
    public ?string $confirmPassword = null;
}