<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TrackableTrait;
use App\Repository\EventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: 'events')]
#[ORM\HasLifecycleCallbacks]
class Event
{
    use TrackableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'event_start_date', type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    private \DateTimeImmutable $eventStartDate;

    #[ORM\Column(name: 'event_end_date', type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    private \DateTimeImmutable $eventEndDate;

    #[ORM\Column(name: 'event_start_time', type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $eventStartTime = null;

    #[ORM\Column(name: 'event_end_time', type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $eventEndTime = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $title;

    #[ORM\ManyToOne(targetEntity: BlogImage::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'image_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?BlogImage $eventImage = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $longitude = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $labelText = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $text = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Category $category = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $enabled = true;

    public function __construct()
    {
        $this->dateCreated = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEventStartDate(): \DateTimeImmutable
    {
        return $this->eventStartDate;
    }

    public function setEventStartDate(\DateTimeImmutable $eventStartDate): self
    {
        $this->eventStartDate = $eventStartDate;
        return $this;
    }

    public function getEventEndDate(): \DateTimeImmutable
    {
        return $this->eventEndDate;
    }

    public function setEventEndDate(\DateTimeImmutable $eventEndDate): self
    {
        $this->eventEndDate = $eventEndDate;
        return $this;
    }

    public function getEventStartTime(): ?\DateTimeImmutable
    {
        return $this->eventStartTime;
    }

    public function setEventStartTime(?\DateTimeImmutable $eventStartTime): self
    {
        $this->eventStartTime = $eventStartTime;
        return $this;
    }

    public function getEventEndTime(): ?\DateTimeImmutable
    {
        return $this->eventEndTime;
    }

    public function setEventEndTime(?\DateTimeImmutable $eventEndTime): self
    {
        $this->eventEndTime = $eventEndTime;
        return $this;
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

    public function getEventImage(): ?BlogImage
    {
        return $this->eventImage;
    }

    public function setEventImage(?BlogImage $eventImage): self
    {
        $this->eventImage = $eventImage;
        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(?string $longitude): self
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(?string $latitude): self
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLabelText(): ?string
    {
        return $this->labelText;
    }

    public function setLabelText(?string $labelText): self
    {
        $this->labelText = $labelText;
        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): self
    {
        $this->text = $text;
        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function isUpcoming(): bool
    {
        return $this->eventStartDate >= new \DateTimeImmutable('today');
    }

    public function isOngoing(): bool
    {
        $now = new \DateTimeImmutable();
        return $this->eventStartDate <= $now && $this->eventEndDate >= $now;
    }

    public function isPast(): bool
    {
        return $this->eventEndDate < new \DateTimeImmutable('today');
    }

    public function __toString(): string
    {
        return $this->title;
    }
}