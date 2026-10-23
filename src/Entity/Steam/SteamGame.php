<?php

declare(strict_types=1);

namespace App\Entity\Steam;

use App\Repository\Steam\SteamGameRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A game in the Steam library, kept up to date by SteamLibrarySync: playtime from the owned games list,
 * store details (fetched once and refreshed now and then) and achievement progress (only re-read after playing).
 */
#[ORM\Entity(repositoryClass: SteamGameRepository::class)]
#[ORM\Table(name: 'steam_game')]
#[ORM\Index(name: 'idx_steam_game_last_played', columns: ['last_played_at'])]
class SteamGame
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(unique: true)]
    private int $appId;

    #[ORM\Column(length: 255)]
    private string $name = '';

    /** Total playtime in minutes */
    #[ORM\Column(options: ['default' => 0])]
    private int $playtimeForever = 0;

    /** Playtime in the last two weeks, in minutes */
    #[ORM\Column(options: ['default' => 0])]
    private int $playtimeTwoWeeks = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastPlayedAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $iconHash = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $headerImage = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $shortDescription = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $genres = [];

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $developers = [];

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $publishers = [];

    /** As Steam gives it, e.g. "21 Oct, 2021" */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $releaseDate = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(nullable: true)]
    private ?int $metacriticScore = null;

    /** Last time the store details were read (also when the store had none, e.g. a removed game) */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $detailsFetchedAt = null;

    /** Null: not read yet; 0: the game has no achievements */
    #[ORM\Column(nullable: true)]
    private ?int $achievementsTotal = null;

    #[ORM\Column(nullable: true)]
    private ?int $achievementsUnlocked = null;

    /** Playtime at the last achievement read: achievements only change by playing, so they're re-read when this differs */
    #[ORM\Column(nullable: true)]
    private ?int $achievementsPlaytime = null;

    /** Steam refuses this player's progress for the game ("Profile is not public": the game is marked private in Steam) */
    #[ORM\Column(options: ['default' => false])]
    private bool $achievementsPrivate = false;

    /** Hidden on the public pages (chosen in the admin) */
    #[ORM\Column(options: ['default' => false])]
    private bool $hidden = false;

    #[ORM\Column]
    private \DateTimeImmutable $addedAt;

    /** Owning side (review_id here), so the review is only loaded when it's used */
    #[ORM\OneToOne(inversedBy: 'game', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\JoinColumn(name: 'review_id', nullable: true, onDelete: 'SET NULL')]
    private ?SteamGameReview $review = null;

    public function __construct(int $appId)
    {
        $this->appId = $appId;
        $this->addedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAppId(): int
    {
        return $this->appId;
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

    public function getPlaytimeForever(): int
    {
        return $this->playtimeForever;
    }

    public function setPlaytimeForever(int $minutes): self
    {
        $this->playtimeForever = $minutes;

        return $this;
    }

    public function getPlaytimeTwoWeeks(): int
    {
        return $this->playtimeTwoWeeks;
    }

    public function setPlaytimeTwoWeeks(int $minutes): self
    {
        $this->playtimeTwoWeeks = $minutes;

        return $this;
    }

    /** Total playtime in hours, one decimal */
    public function getHoursPlayed(): float
    {
        return round($this->playtimeForever / 60, 1);
    }

    public function getHoursTwoWeeks(): float
    {
        return round($this->playtimeTwoWeeks / 60, 1);
    }

    public function getLastPlayedAt(): ?\DateTimeImmutable
    {
        return $this->lastPlayedAt;
    }

    public function setLastPlayedAt(?\DateTimeImmutable $lastPlayedAt): self
    {
        $this->lastPlayedAt = $lastPlayedAt;

        return $this;
    }

    public function getIconHash(): ?string
    {
        return $this->iconHash;
    }

    public function setIconHash(?string $iconHash): self
    {
        $this->iconHash = $iconHash;

        return $this;
    }

    public function getIconUrl(): ?string
    {
        return $this->iconHash ? sprintf('https://media.steampowered.com/steamcommunity/public/images/apps/%d/%s.jpg', $this->appId, $this->iconHash) : null;
    }

    /** The store's header image, or Steam's standard location for it */
    public function getHeaderImage(): string
    {
        return $this->headerImage ?: sprintf('https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/%d/header.jpg', $this->appId);
    }

    public function setHeaderImage(?string $headerImage): self
    {
        $this->headerImage = $headerImage;

        return $this;
    }

    public function getStoreUrl(): string
    {
        return sprintf('https://store.steampowered.com/app/%d/', $this->appId);
    }

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $shortDescription): self
    {
        $this->shortDescription = $shortDescription;

        return $this;
    }

    /** @return list<string> */
    public function getGenres(): array
    {
        return $this->genres;
    }

    /** @param list<string> $genres */
    public function setGenres(array $genres): self
    {
        $this->genres = $genres;

        return $this;
    }

    /** @return list<string> */
    public function getDevelopers(): array
    {
        return $this->developers;
    }

    /** @param list<string> $developers */
    public function setDevelopers(array $developers): self
    {
        $this->developers = $developers;

        return $this;
    }

    /** @return list<string> */
    public function getPublishers(): array
    {
        return $this->publishers;
    }

    /** @param list<string> $publishers */
    public function setPublishers(array $publishers): self
    {
        $this->publishers = $publishers;

        return $this;
    }

    public function getReleaseDate(): ?string
    {
        return $this->releaseDate;
    }

    public function setReleaseDate(?string $releaseDate): self
    {
        $this->releaseDate = $releaseDate;

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

    public function getMetacriticScore(): ?int
    {
        return $this->metacriticScore;
    }

    public function setMetacriticScore(?int $metacriticScore): self
    {
        $this->metacriticScore = $metacriticScore;

        return $this;
    }

    public function getDetailsFetchedAt(): ?\DateTimeImmutable
    {
        return $this->detailsFetchedAt;
    }

    public function setDetailsFetchedAt(?\DateTimeImmutable $detailsFetchedAt): self
    {
        $this->detailsFetchedAt = $detailsFetchedAt;

        return $this;
    }

    public function getAchievementsTotal(): ?int
    {
        return $this->achievementsTotal;
    }

    public function getAchievementsUnlocked(): ?int
    {
        return $this->achievementsUnlocked;
    }

    /** Achievements with known progress (not for a game whose progress is private) */
    public function hasAchievements(): bool
    {
        return !$this->achievementsPrivate && ($this->achievementsTotal ?? 0) > 0;
    }

    /** Percentage of the achievements unlocked, null when the game has none (or they weren't read yet) */
    public function getAchievementPercentage(): ?int
    {
        return $this->hasAchievements() ? (int) floor(100 * (int) $this->achievementsUnlocked / (int) $this->achievementsTotal) : null;
    }

    public function setAchievements(int $unlocked, int $total): self
    {
        $this->achievementsUnlocked = $unlocked;
        $this->achievementsTotal = $total;
        $this->achievementsPlaytime = $this->playtimeForever;
        $this->achievementsPrivate = false;

        return $this;
    }

    /**
     * Steam won't give this player's progress for the game; only the number of achievements is known.
     *
     * @param int|null $total from the game's public achievement list, null when that isn't known either
     */
    public function setAchievementsPrivate(?int $total): self
    {
        $this->achievementsPrivate = true;
        $this->achievementsUnlocked = null;
        $this->achievementsTotal = $total;
        $this->achievementsPlaytime = $this->playtimeForever;

        return $this;
    }

    public function isAchievementsPrivate(): bool
    {
        return $this->achievementsPrivate;
    }

    /**
     * Achievements may have changed: never read, played since the last read, or private in Steam
     * (asked again every sync, so making the game public again in Steam is picked up)
     */
    public function needsAchievementRefresh(): bool
    {
        return $this->playtimeForever > 0 && ($this->achievementsPrivate || $this->achievementsPlaytime !== $this->playtimeForever);
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): self
    {
        $this->hidden = $hidden;

        return $this;
    }

    public function getAddedAt(): \DateTimeImmutable
    {
        return $this->addedAt;
    }

    /** My review, also when it's a draft */
    public function getReview(): ?SteamGameReview
    {
        return $this->review;
    }

    /** The review visitors may see */
    public function getPublishedReview(): ?SteamGameReview
    {
        return $this->review?->isPublished() ? $this->review : null;
    }

    public function setReview(?SteamGameReview $review): self
    {
        $this->review = $review;

        return $this;
    }
}
