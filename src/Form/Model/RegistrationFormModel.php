<?php

declare(strict_types=1);

namespace App\Form\Model;

use Symfony\Component\Validator\Constraints as Assert;

class RegistrationFormModel
{
    #[Assert\NotBlank(message: 'Vul je voornaam in')]
    #[Assert\Length(min: 2, max: 50)]
    public ?string $firstName = null;

    #[Assert\NotBlank(message: 'Vul je achternaam in')]
    #[Assert\Length(min: 2, max: 50)]
    public ?string $lastName = null;

    #[Assert\NotBlank(message: 'Vul een e-mailadres in')]
    #[Assert\Email(message: 'Vul een geldig e-mailadres in')]
    public ?string $email = null;

    #[Assert\NotBlank(message: 'Vul een wachtwoord in')]
    #[Assert\Length(min: 8, max: 4096, minMessage: 'Je wachtwoord moet minstens {{ limit }} tekens lang zijn')]
    public ?string $plainPassword = null;

    #[Assert\IsTrue(message: 'Je moet akkoord gaan met de voorwaarden')]
    public ?bool $termsAccepted = false;
}