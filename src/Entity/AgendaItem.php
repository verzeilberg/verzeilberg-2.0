<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TrackableTrait;
use App\Repository\AgendaItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * An appointment in an agenda.
 *
 * startAt/endAt are moments. For a whole-day item they are the first day at 00:00 and the last day at 23:59:59,
 * so "overlaps a period" is always: startAt < periodEnd AND endAt >= periodStart.
 */
#[ORM\Entity(repositoryClass: AgendaItemRepository::class)]
#[ORM\Table(name: 'agenda_item')]
#[ORM\Index(name: 'idx_agenda_item_period', columns: ['start_at', 'end_at'])]
#[ORM\UniqueConstraint(name: 'uniq_agenda_item_uid', columns: ['agenda_id', 'uid'])]
#[ORM\HasLifecycleCallbacks]
class AgendaItem
{
    use TrackableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Agenda::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'agenda_id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    // Nullable until an agenda is chosen; always set before the item is saved
    // @phpstan-ignore-next-line doctrine.associationType
    private ?Agenda $agenda = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $title = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $location = null;

    #[ORM\Column(name: 'start_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $startAt;

    #[ORM\Column(name: 'end_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $endAt;

    #[ORM\Column(name: 'all_day', options: ['default' => false])]
    private bool $allDay = false;

    /** UID of the event in an imported iCal calendar; importing again updates the item instead of duplicating it */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $uid = null;

    public function __construct()
    {
        $this->startAt = new \DateTimeImmutable('today 09:00');
        $this->endAt = new \DateTimeImmutable('today 10:00');
        $this->setDateCreated(new \DateTimeImmutable());
    }

    #[Assert\Callback]
    public function validatePeriod(ExecutionContextInterface $context): void
    {
        if ($this->endAt < $this->startAt) {
            $context->buildViolation('The end must be after the start.')->atPath('endAt')->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAgenda(): ?Agenda
    {
        return $this->agenda;
    }

    public function setAgenda(?Agenda $agenda): self
    {
        $this->agenda = $agenda;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
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

    public function getStartAt(): \DateTimeImmutable
    {
        return $this->startAt;
    }

    public function setStartAt(\DateTimeImmutable $startAt): self
    {
        $this->startAt = $startAt;
        return $this;
    }

    public function getEndAt(): \DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(\DateTimeImmutable $endAt): self
    {
        $this->endAt = $endAt;
        return $this;
    }

    public function isAllDay(): bool
    {
        return $this->allDay;
    }

    public function setAllDay(bool $allDay): self
    {
        $this->allDay = $allDay;
        return $this;
    }

    /** Sets a whole-day period: from the start of the first day until the end of the last day */
    public function setAllDayPeriod(\DateTimeImmutable $firstDay, \DateTimeImmutable $lastDay): self
    {
        $this->allDay = true;
        $this->startAt = $firstDay->setTime(0, 0);
        $this->endAt = $lastDay->setTime(23, 59, 59);

        return $this;
    }

    public function getUid(): ?string
    {
        return $this->uid;
    }

    public function setUid(?string $uid): self
    {
        $this->uid = $uid;
        return $this;
    }

    /** Whether the item takes place (partly) on the given day */
    public function isOnDay(\DateTimeImmutable $day): bool
    {
        return $this->startAt < $day->setTime(0, 0)->modify('+1 day') && $this->endAt >= $day->setTime(0, 0);
    }

    /** Spans more than one calendar day */
    public function isMultiDay(): bool
    {
        return $this->startAt->format('Y-m-d') !== $this->endAt->format('Y-m-d');
    }
}
