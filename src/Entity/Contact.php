<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TrackableTrait;
use App\Repository\ContactRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContactRepository::class)]
#[ORM\Table(name: 'contact')]
class Contact
{
    use TrackableTrait;

    public const SALUTATION_MR = 1;
    public const SALUTATION_MRS = 2;
    public const SALUTATION_UND = 3;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'salutation', type: 'smallint', nullable: true)]
    #[Assert\Choice(choices: [self::SALUTATION_MR, self::SALUTATION_MRS, self::SALUTATION_UND])]
    private ?int $salutation = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 255)]
    private string $email;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $subject;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $message;

    /** When an admin first opened the message; null = unread (counted in the admin menu) */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSalutation(): ?int
    {
        return $this->salutation;
    }

    public function setSalutation(?int $salutation): self
    {
        $this->salutation = $salutation;
        return $this;
    }

    public function getSalutationLabel(): string
    {
        return match ($this->salutation) {
            self::SALUTATION_MR => 'De heer',
            self::SALUTATION_MRS => 'Mevrouw',
            self::SALUTATION_UND => 'Geen voorkeur',
            default => 'Onbekend',
        };
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function __toString(): string
    {
        return $this->subject;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function isRead(): bool
    {
        return $this->readAt !== null;
    }

    public function markRead(): self
    {
        $this->readAt ??= new \DateTimeImmutable();
        return $this;
    }
}
