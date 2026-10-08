<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TrackableTrait;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: \App\Repository\UserRepository::class)]
#[ORM\Table(name: 'user')]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use TrackableTrait;

    public const STATUS_ACTIVE = 1;
    public const STATUS_RETIRED = 2;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private string $email;

    #[ORM\Column(length: 255)]
    private string $password;

    #[ORM\Column(length: 255)]
    private string $fullName;

    #[ORM\Column(type: 'smallint', options: ['default' => self::STATUS_ACTIVE])]
    private int $status = self::STATUS_ACTIVE;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $passwordResetToken = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $passwordResetTokenCreationDate = null;

    /** @var Collection<int, Role> */
    #[ORM\ManyToMany(targetEntity: Role::class)]
    #[ORM\JoinTable(
        name: 'user_role',
        joinColumns: [new ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'role_id', referencedColumnName: 'id')]
    )]
    private Collection $roles;

    #[ORM\OneToOne(targetEntity: UserDetails::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?UserDetails $details = null;

    public function __construct()
    {
        $this->roles = new ArrayCollection();
        $this->dateCreated = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): self
    {
        $this->fullName = $fullName;
        return $this;
    }

    public function getInitials(): string
    {
        $words = array_filter(array_map('trim', explode(' ', $this->fullName)));
        $initials = '';
        foreach ($words as $word) {
            $initials .= mb_strtoupper(mb_substr($word, 0, 1));
        }
        return $initials ?: '?';
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getPasswordResetToken(): ?string
    {
        return $this->passwordResetToken;
    }

    public function setPasswordResetToken(?string $token): self
    {
        $this->passwordResetToken = $token;
        return $this;
    }

    public function getPasswordResetTokenCreationDate(): ?\DateTimeImmutable
    {
        return $this->passwordResetTokenCreationDate;
    }

    public function setPasswordResetTokenCreationDate(?\DateTimeImmutable $date): self
    {
        $this->passwordResetTokenCreationDate = $date;
        return $this;
    }

    /** @return Collection<int, Role> */
    public function getDoctrineRoles(): Collection
    {
        return $this->roles;
    }

    /** @return array<string> */
    public function getRoles(): array
    {
        return $this->roles->map(fn(Role $role) => $role->getName())->toArray();
    }

    public function addRole(Role $role): self
    {
        if (!$this->getDoctrineRoles()->contains($role)) {
            $this->getDoctrineRoles()->add($role);
        }
        return $this;
    }

    public function removeRole(Role $role): self
    {
        $this->getDoctrineRoles()->removeElement($role);
        return $this;
    }

    public function hasRole(string $roleName): bool
    {
        return $this->getDoctrineRoles()->exists(fn(int $key, Role $role) => $role->getName() === $roleName);
    }

    public function hasPermission(string $permissionName): bool
    {
        foreach ($this->getDoctrineRoles() as $role) {
            if ($role->hasPermission($permissionName)) {
                return true;
            }
        }
        return false;
    }

    public function getDetails(): ?UserDetails
    {
        return $this->details;
    }

    public function setDetails(?UserDetails $details): self
    {
        $this->details = $details;
        if ($details !== null && $details->getUser() !== $this) {
            $details->setUser($this);
        }
        return $this;
    }

    public function __toString(): string
    {
        return $this->fullName;
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }
}