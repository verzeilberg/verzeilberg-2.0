<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TrackableTrait;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Scheb\TwoFactorBundle\Model\BackupCodeInterface;
use Scheb\TwoFactorBundle\Model\Email\TwoFactorInterface as EmailTwoFactorInterface;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Scheb\TwoFactorBundle\Model\TrustedDeviceInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: \App\Repository\UserRepository::class)]
#[ORM\Table(name: 'user')]
#[UniqueEntity(fields: ['email'], message: 'Er bestaat al een account met dit e-mailadres')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface, TwoFactorInterface, EmailTwoFactorInterface, BackupCodeInterface, TrustedDeviceInterface
{
    use TrackableTrait;

    public const STATUS_ACTIVE = 1;
    public const STATUS_RETIRED = 2;
    /** Registered through the site; can't log in until an admin approves the account */
    public const STATUS_PENDING = 3;

    /** Second login step: a code by email (the default for everyone) or from an authenticator app */
    public const TWO_FACTOR_EMAIL = 'email';
    public const TWO_FACTOR_APP = 'totp';

    /** How long an emailed login code works */
    private const EMAIL_CODE_LIFETIME = '+10 minutes';

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

    /** Whether the user is listed in the user directory and their page is visible to others */
    #[ORM\Column(options: ['default' => false])]
    private bool $profilePublic = false;

    /** sha256 of the token in the reset link, so a database dump holds no usable links */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $passwordResetToken = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $passwordResetTokenCreationDate = null;

    /** When the user clicked the link in the confirmation mail sent after registering */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    /** sha256 of the token in the confirmation link, like the password reset token */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $emailVerificationToken = null;

    /** Whether the user gets mail about replies to their comments and about friend requests */
    #[ORM\Column(options: ['default' => true])]
    private bool $emailNotifications = true;

    #[ORM\Column(length: 10, options: ['default' => self::TWO_FACTOR_EMAIL])]
    private string $twoFactorMethod = self::TWO_FACTOR_EMAIL;

    /** Secret of the authenticator app (only with TWO_FACTOR_APP) */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $totpSecret = null;

    /** The code that was last emailed, and when; it works for EMAIL_CODE_LIFETIME */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $emailAuthCode = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $emailAuthCodeCreatedAt = null;

    /** @var list<string> Hashes of the unused backup codes (the codes themselves are only shown once) */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $backupCodes = [];

    /** Raising this logs out every "trusted" device */
    #[ORM\Column(options: ['default' => 0])]
    private int $trustedTokenVersion = 0;

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

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProfilePublic(): bool
    {
        return $this->profilePublic;
    }

    public function setProfilePublic(bool $profilePublic): self
    {
        $this->profilePublic = $profilePublic;
        return $this;
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

    /** The hash of the token, see hashPasswordResetToken() */
    public function getPasswordResetToken(): ?string
    {
        return $this->passwordResetToken;
    }

    /** Takes the token from the reset link; only its hash is stored */
    public function setPasswordResetToken(?string $token): self
    {
        $this->passwordResetToken = $token !== null ? self::hashPasswordResetToken($token) : null;
        return $this;
    }

    public static function hashPasswordResetToken(string $token): string
    {
        return hash('sha256', $token);
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

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    /** Takes the token from the confirmation link; only its hash is stored */
    public function setEmailVerificationToken(?string $token): self
    {
        $this->emailVerificationToken = $token !== null ? self::hashPasswordResetToken($token) : null;
        return $this;
    }

    public function getEmailVerificationToken(): ?string
    {
        return $this->emailVerificationToken;
    }

    public function markEmailVerified(): self
    {
        $this->emailVerifiedAt = new \DateTimeImmutable();
        $this->emailVerificationToken = null;
        return $this;
    }

    public function wantsEmailNotifications(): bool
    {
        return $this->emailNotifications;
    }

    public function setEmailNotifications(bool $emailNotifications): self
    {
        $this->emailNotifications = $emailNotifications;
        return $this;
    }

    /** Whether the user can be mailed about activity: an active account that didn't switch notifications off */
    public function canReceiveNotifications(): bool
    {
        return $this->emailNotifications && $this->isActive() && !$this->isDeleted();
    }

    /** @return Collection<int, Role> */
    public function getDoctrineRoles(): Collection
    {
        return $this->roles;
    }

    /** @return list<string> the role names; every user has at least ROLE_USER, also with only custom roles */
    public function getRoles(): array
    {
        $roles = $this->roles->map(fn(Role $role) => $role->getName())->toArray();
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
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

    // Two-factor login (scheb/2fa): a code by email or from an authenticator app, backup codes and trusted devices

    public function getTwoFactorMethod(): string
    {
        return $this->twoFactorMethod;
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return $this->twoFactorMethod === self::TWO_FACTOR_APP && $this->totpSecret !== null;
    }

    public function isEmailAuthEnabled(): bool
    {
        return !$this->isTotpAuthenticationEnabled();
    }

    public function getEmailAuthRecipient(): string
    {
        return $this->email;
    }

    public function getEmailAuthCode(): ?string
    {
        // An old code no longer works; the code form offers to send a new one
        if ($this->emailAuthCodeCreatedAt === null || $this->emailAuthCodeCreatedAt->modify(self::EMAIL_CODE_LIFETIME) < new \DateTimeImmutable()) {
            return null;
        }

        return $this->emailAuthCode;
    }

    public function setEmailAuthCode(string $authCode): void
    {
        $this->emailAuthCode = $authCode;
        $this->emailAuthCodeCreatedAt = new \DateTimeImmutable();
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->email;
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        // SHA1, 30 seconds and 6 digits: what every authenticator app supports
        return $this->totpSecret === null ? null : new TotpConfiguration($this->totpSecret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    /**
     * Log in with the authenticator app from now on.
     *
     * @param list<string> $backupCodes the plain codes; only their hashes are stored
     */
    public function useAuthenticatorApp(string $totpSecret, array $backupCodes): self
    {
        $this->twoFactorMethod = self::TWO_FACTOR_APP;
        $this->totpSecret = $totpSecret;
        $this->setBackupCodes($backupCodes);
        // Devices trusted before don't skip the code any more
        $this->trustedTokenVersion++;

        return $this;
    }

    /**
     * Back to a code by email (the default); the app's secret and backup codes are removed.
     */
    public function useEmailCodes(): self
    {
        $this->twoFactorMethod = self::TWO_FACTOR_EMAIL;
        $this->totpSecret = null;
        $this->setBackupCodes([]);
        $this->trustedTokenVersion++;

        return $this;
    }

    /**
     * @param list<string> $codes the plain codes; only their hashes are stored
     */
    public function setBackupCodes(array $codes): self
    {
        $this->backupCodes = array_map(fn (string $code) => password_hash(self::normalizeBackupCode($code), PASSWORD_DEFAULT), $codes);

        return $this;
    }

    public function getBackupCodeCount(): int
    {
        return count($this->backupCodes ?? []);
    }

    public function isBackupCode(string $code): bool
    {
        return $this->findBackupCode($code) !== null;
    }

    public function invalidateBackupCode(string $code): void
    {
        $index = $this->findBackupCode($code);
        if ($index !== null) {
            unset($this->backupCodes[$index]);
            $this->backupCodes = array_values($this->backupCodes);
        }
    }

    public function getTrustedTokenVersion(): int
    {
        return $this->trustedTokenVersion;
    }

    public function forgetTrustedDevices(): self
    {
        $this->trustedTokenVersion++;

        return $this;
    }

    private function findBackupCode(string $code): ?int
    {
        $code = self::normalizeBackupCode($code);
        foreach ($this->backupCodes ?? [] as $index => $hash) {
            if (password_verify($code, $hash)) {
                return $index;
            }
        }

        return null;
    }

    /** "ab12-cd34", "AB12 CD34" and "ab12cd34" are the same code */
    private static function normalizeBackupCode(string $code): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $code));
    }
}
