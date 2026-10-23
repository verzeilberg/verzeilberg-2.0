<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TrackableTrait;
use App\Repository\AgendaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A calendar with its own colour. Items always belong to one agenda.
 * A public agenda is shown (read-only) on the site; every agenda has a secret iCal feed link.
 */
#[ORM\Entity(repositoryClass: AgendaRepository::class)]
#[ORM\Table(name: 'agenda')]
#[ORM\HasLifecycleCallbacks]
class Agenda
{
    use TrackableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $title = '';

    /** Hex colour, e.g. "#0d6efd" */
    #[ORM\Column(length: 7)]
    #[Assert\Regex(pattern: '/^#[0-9a-fA-F]{6}$/', message: 'Kies een geldige kleur.')]
    private string $color = '#0d6efd';

    /** Shown read-only on the site (/agenda) and its iCal feed is public */
    #[ORM\Column(name: 'is_public', options: ['default' => false])]
    private bool $public = false;

    /** Secret part of the iCal feed URL, so a private agenda can still be subscribed to */
    #[ORM\Column(name: 'feed_token', length: 64, unique: true)]
    private string $feedToken;

    /** Google account whose calendar this agenda shows; such an agenda follows Google and is read-only here */
    #[ORM\ManyToOne(targetEntity: GoogleAccount::class)]
    #[ORM\JoinColumn(name: 'google_account_id', nullable: true, onDelete: 'SET NULL')]
    private ?GoogleAccount $googleAccount = null;

    #[ORM\Column(name: 'google_calendar_id', length: 255, nullable: true)]
    private ?string $googleCalendarId = null;

    #[ORM\Column(name: 'last_synced_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastSyncedAt = null;

    #[ORM\Column(name: 'last_sync_error', type: 'text', nullable: true)]
    private ?string $lastSyncError = null;

    /** @var Collection<int, AgendaItem> */
    #[ORM\OneToMany(mappedBy: 'agenda', targetEntity: AgendaItem::class, cascade: ['remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['startAt' => 'ASC'])]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->feedToken = bin2hex(random_bytes(24));
        $this->setDateCreated(new \DateTimeImmutable());
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): self
    {
        $this->color = strtolower($color);
        return $this;
    }

    /** Black or white, whichever is readable on the agenda colour (WCAG relative luminance) */
    public function getTextColor(): string
    {
        [$r, $g, $b] = array_map(
            function (string $hex): float {
                $channel = hexdec($hex) / 255;

                return $channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
            },
            str_split(ltrim($this->color, '#'), 2)
        );
        $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

        return $luminance > 0.179 ? '#000000' : '#ffffff';
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): self
    {
        $this->public = $public;
        return $this;
    }

    public function getFeedToken(): string
    {
        return $this->feedToken;
    }

    /** A new token makes the old (shared) feed link stop working */
    public function regenerateFeedToken(): self
    {
        $this->feedToken = bin2hex(random_bytes(24));
        return $this;
    }

    public function getGoogleAccount(): ?GoogleAccount
    {
        return $this->googleAccount;
    }

    public function getGoogleCalendarId(): ?string
    {
        return $this->googleCalendarId;
    }

    public function linkToGoogle(?GoogleAccount $account, ?string $calendarId): self
    {
        $this->googleAccount = $account;
        $this->googleCalendarId = $calendarId;
        return $this;
    }

    /** Shows a Google calendar: its items come from Google and can't be edited here */
    public function isGoogleLinked(): bool
    {
        return $this->googleAccount !== null && $this->googleCalendarId !== null;
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

    /** @return Collection<int, AgendaItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
