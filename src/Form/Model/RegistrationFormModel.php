<?php

declare(strict_types=1);

namespace App\Form\Model;

use Symfony\Component\Validator\Constraints as Assert;

class RegistrationFormModel
{
    #[Assert\NotBlank(message: 'Please enter your first name')]
    #[Assert\Length(min: 2, max: 50)]
    public ?string $firstName = null;

    #[Assert\NotBlank(message: 'Please enter your last name')]
    #[Assert\Length(min: 2, max: 50)]
    public ?string $lastName = null;

    #[Assert\NotBlank(message: 'Please enter an email address')]
    #[Assert\Email(message: 'Please enter a valid email address')]
    public ?string $email = null;

    #[Assert\NotBlank(message: 'Please enter a password')]
    #[Assert\Length(min: 8, max: 4096, minMessage: 'Your password should be at least {{ limit }} characters')]
    public ?string $plainPassword = null;

    #[Assert\IsTrue(message: 'You must accept the terms and conditions')]
    public ?bool $termsAccepted = false;
}