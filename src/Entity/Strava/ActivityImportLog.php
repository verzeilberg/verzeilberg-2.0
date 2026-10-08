<?php

declare(strict_types=1);

namespace App\Entity\Strava;

use App\Repository\Strava\ActivityImportLogRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActivityImportLogRepository::class)]
#[ORM\Table(name: 'strava_activity_import_log')]
class ActivityImportLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'import_date', type: 'datetime_immutable')]
    private \DateTimeImmutable $importDate;

    /** @var Collection<int, Activity> */
    #[ORM\OneToMany(mappedBy: 'activityImportLog', targetEntity: Activity::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $activities;

    public function __construct()
    {
        $this->activities = new ArrayCollection();
        $this->importDate = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getImportDate(): \DateTimeImmutable
    {
        return $this->importDate;
    }

    public function setImportDate(\DateTimeImmutable $importDate): self
    {
        $this->importDate = $importDate;
        return $this;
    }

    public function getActivities(): Collection
    {
        return $this->activities;
    }

    public function addActivity(Activity $activity): self
    {
        if (!$this->activities->contains($activity)) {
            $this->activities->add($activity);
            $activity->setActivityImportLog($this);
        }
        return $this;
    }

    public function removeActivity(Activity $activity): self
    {
        if ($this->activities->removeElement($activity)) {
            if ($activity->getActivityImportLog() === $this) {
                $activity->setActivityImportLog(null);
            }
        }
        return $this;
    }

    public function getActivityCount(): int
    {
        return $this->activities->count();
    }
}