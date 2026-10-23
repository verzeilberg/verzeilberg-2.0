<?php

declare(strict_types=1);

namespace App\Entity\Steam;

use App\Repository\Steam\SteamGameReviewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * My review of a game: a rating from 1 (bad) to 5 (excellent) and a text. One per game;
 * a draft (not published) is only visible in the admin.
 */
#[ORM\Entity(repositoryClass: SteamGameReviewRepository::class)]
#[ORM\Table(name: 'steam_game_review')]
class SteamGameReview
{
    public const RATINGS = [1 => 'Slecht', 2 => 'Matig', 3 => 'Oké', 4 => 'Goed', 5 => 'Uitstekend'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Set in the constructor; the game holds the reference (steam_game.review_id) */
    #[ORM\OneToOne(mappedBy: 'review')]
    private ?SteamGame $game = null;

    /** 1 (bad) to 5 (excellent); 0 while none is chosen, which the validation refuses */
    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 1, max: 5, notInRangeMessage: 'Kies een beoordeling van {{ min }} tot {{ max }} sterren.')]
    private int $rating = 0;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Schrijf de review.')]
    #[Assert\Length(max: 20000)]
    private string $body = '';

    #[ORM\Column(options: ['default' => true])]
    private bool $published = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct(SteamGame $game)
    {
        $this->game = $game;
        $this->createdAt = new \DateTimeImmutable();
        $game->setReview($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGame(): SteamGame
    {
        return $this->game ?? throw new \LogicException('A review always belongs to a game.');
    }

    public function getRating(): int
    {
        return $this->rating;
    }

    public function setRating(?int $rating): self
    {
        $this->rating = (int) $rating;

        return $this;
    }

    /** "Good" for 4 */
    public function getRatingLabel(): string
    {
        return self::RATINGS[$this->rating] ?? '';
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(?string $body): self
    {
        $this->body = trim((string) $body);

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): self
    {
        $this->published = $published;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function markUpdated(): self
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
