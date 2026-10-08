<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\GoogleAccountRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A Google account connected through OAuth, used to read its calendars into agendas.
 * Tokens are stored encrypted (see TokenEncryptor).
 */
#[ORM\Entity(repositoryClass: GoogleAccountRepository::class)]
#[ORM\Table(name: 'google_account')]
class GoogleAccount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private string $email;

    #[ORM\Column(name: 'access_token', type: 'text')]
    private string $encryptedAccessToken;

    #[ORM\Column(name: 'refresh_token', type: 'text')]
    private string $encryptedRefreshToken;

    #[ORM\Column(name: 'access_token_expires_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $accessTokenExpiresAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'connected_by', nullable: true, onDelete: 'SET NULL')]
    private ?User $connectedBy = null;

    #[ORM\Column(name: 'connected_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $connectedAt;

    /** Set when Google refused the refresh token (revoked access); the account has to be connected again */
    #[ORM\Column(name: 'last_error', type: 'text', nullable: true)]
    private ?string $lastError = null;

    public function __construct(string $email, string $encryptedAccessToken, string $encryptedRefreshToken, \DateTimeImmutable $accessTokenExpiresAt)
    {
        $this->email = $email;
        $this->encryptedAccessToken = $encryptedAccessToken;
        $this->encryptedRefreshToken = $encryptedRefreshToken;
        $this->accessTokenExpiresAt = $accessTokenExpiresAt;
        $this->connectedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getEncryptedAccessToken(): string
    {
        return $this->encryptedAccessToken;
    }

    public function getEncryptedRefreshToken(): string
    {
        return $this->encryptedRefreshToken;
    }

    public function getAccessTokenExpiresAt(): \DateTimeImmutable
    {
        return $this->accessTokenExpiresAt;
    }

    public function updateAccessToken(string $encryptedAccessToken, \DateTimeImmutable $expiresAt): self
    {
        $this->encryptedAccessToken = $encryptedAccessToken;
        $this->accessTokenExpiresAt = $expiresAt;
        $this->lastError = null;

        return $this;
    }

    /** After connecting again: new tokens */
    public function reconnect(string $encryptedAccessToken, string $encryptedRefreshToken, \DateTimeImmutable $expiresAt): self
    {
        $this->encryptedRefreshToken = $encryptedRefreshToken;
        $this->connectedAt = new \DateTimeImmutable();

        return $this->updateAccessToken($encryptedAccessToken, $expiresAt);
    }

    public function getConnectedBy(): ?User
    {
        return $this->connectedBy;
    }

    public function setConnectedBy(?User $connectedBy): self
    {
        $this->connectedBy = $connectedBy;
        return $this;
    }

    public function getConnectedAt(): \DateTimeImmutable
    {
        return $this->connectedAt;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setLastError(?string $lastError): self
    {
        $this->lastError = $lastError;
        return $this;
    }
}
