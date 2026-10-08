<?php

declare(strict_types=1);

namespace App\Entity\Steam;

use App\Repository\Steam\SteamAccountRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * The Steam profile whose library is shown (one row), with the outcome of the last sync.
 */
#[ORM\Entity(repositoryClass: SteamAccountRepository::class)]
#[ORM\Table(name: 'steam_account')]
class SteamAccount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private string $steamId;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $personaName = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $avatarUrl = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $profileUrl = null;

    /** Name of the game being played at the last sync */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $currentlyPlaying = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSyncedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lastSyncError = null;

    public function __construct(string $steamId)
    {
        $this->steamId = $steamId;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSteamId(): string
    {
        return $this->steamId;
    }

    public function getPersonaName(): ?string
    {
        return $this->personaName;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function getProfileUrl(): string
    {
        return $this->profileUrl ?: 'https://steamcommunity.com/profiles/' . $this->steamId;
    }

    public function getCurrentlyPlaying(): ?string
    {
        return $this->currentlyPlaying;
    }

    public function updateProfile(?string $personaName, ?string $avatarUrl, ?string $profileUrl, ?string $currentlyPlaying): self
    {
        $this->personaName = $personaName;
        $this->avatarUrl = $avatarUrl;
        $this->profileUrl = $profileUrl;
        $this->currentlyPlaying = $currentlyPlaying;

        return $this;
    }

    public function getLastSyncedAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncedAt;
    }

    public function getLastSyncError(): ?string
    {
        return $this->lastSyncError;
    }

    public function markSynced(?string $error = null): self
    {
        if ($error === null) {
            $this->lastSyncedAt = new \DateTimeImmutable();
        }
        $this->lastSyncError = $error;

        return $this;
    }
}
