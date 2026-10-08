<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TrackableTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\UserDetailsRepository::class)]
#[ORM\Table(name: 'user_details')]
#[ORM\HasLifecycleCallbacks]
class UserDetails
{
    use TrackableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $birthDate = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $catchLine = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $socialLinks = null;

    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'details')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false, unique: true)]
    private User $user;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getBirthDate(): ?\DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function setBirthDate(?\DateTimeImmutable $birthDate): self
    {
        $this->birthDate = $birthDate;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): self
    {
        $this->bio = $bio;
        return $this;
    }

    public function getCatchLine(): ?string
    {
        return $this->catchLine;
    }

    public function setCatchLine(?string $catchLine): self
    {
        $this->catchLine = $catchLine;
        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): self
    {
        $this->avatar = $avatar;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): self
    {
        $this->website = $website;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;
        return $this;
    }

    public function getSocialLinks(): ?array
    {
        return $this->socialLinks;
    }

    public function setSocialLinks(?array $socialLinks): self
    {
        $this->socialLinks = $socialLinks;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getFullName(): string
    {
        return trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));
    }

    public function getInitials(): string
    {
        $first = $this->firstName !== null && $this->firstName !== '' ? $this->firstName[0] : '';
        $last = $this->lastName !== null && $this->lastName !== '' ? $this->lastName[0] : '';
        return strtoupper($first . $last);
    }

    public function getAge(): ?int
    {
        if ($this->birthDate === null) {
            return null;
        }
        $now = new \DateTimeImmutable();
        $diff = $now->diff($this->birthDate);
        return $diff->y;
    }

    public function hasSocialLinks(): bool
    {
        return !empty($this->socialLinks);
    }

    public function __toString(): string
    {
        return $this->getFullName() ?: 'User Details';
    }
}