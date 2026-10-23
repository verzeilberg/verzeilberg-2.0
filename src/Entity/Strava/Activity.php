<?php

declare(strict_types=1);

namespace App\Entity\Strava;

use App\Repository\Strava\ActivityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActivityRepository::class)]
#[ORM\Table(name: 'strava_activities')]
class Activity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'activity_id', type: 'string', length: 255, unique: true)]
    private string $activityId;

    #[ORM\Column(name: 'athlete_id', type: 'integer')]
    private int $athleteId;

    #[ORM\Column(name: 'name', type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(name: 'distance', type: 'float')]
    private float $distance;

    #[ORM\Column(name: 'moving_time', type: 'integer')]
    private int $movingTime;

    #[ORM\Column(name: 'elapsed_time', type: 'integer')]
    private int $elapsedTime;

    #[ORM\Column(name: 'total_elevation_gain', type: 'float')]
    private float $totalElevationGain;

    #[ORM\Column(name: 'type', type: 'string', length: 255)]
    private string $type;

    #[ORM\Column(name: 'workout_type', type: 'integer', nullable: true)]
    private ?int $workoutType = null;

    #[ORM\Column(name: 'start_date', type: 'datetime_immutable')]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(name: 'start_date_local', type: 'datetime_immutable')]
    private \DateTimeImmutable $startDateLocal;

    #[ORM\Column(name: 'timezone', type: 'string', length: 255)]
    private string $timezone;

    #[ORM\Column(name: 'start_lat', type: 'float', nullable: true)]
    private ?float $startLat = null;

    #[ORM\Column(name: 'start_lng', type: 'float', nullable: true)]
    private ?float $startLng = null;

    #[ORM\Column(name: 'end_lat', type: 'float', nullable: true)]
    private ?float $endLat = null;

    #[ORM\Column(name: 'end_lng', type: 'float', nullable: true)]
    private ?float $endLng = null;

    #[ORM\Column(name: 'summary_polyline', type: 'text', nullable: true)]
    private ?string $summaryPolyline = null;

    #[ORM\Column(name: 'average_speed', type: 'float', nullable: true)]
    private ?float $averageSpeed = null;

    // time_immutable: Doctrine's "time" type returns a mutable DateTime, which this property can't hold
    #[ORM\Column(name: 'average_speed_time', type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $averageSpeedTime = null;

    #[ORM\Column(name: 'max_speed', type: 'float', nullable: true)]
    private ?float $maxSpeed = null;

    #[ORM\Column(name: 'average_heartrate', type: 'float', nullable: true)]
    private ?float $averageHeartrate = null;

    #[ORM\Column(name: 'max_heartrate', type: 'float', nullable: true)]
    private ?float $maxHeartrate = null;

    #[ORM\Column(name: 'elev_high', type: 'float', nullable: true)]
    private ?float $elevHigh = null;

    #[ORM\Column(name: 'elev_low', type: 'float', nullable: true)]
    private ?float $elevLow = null;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private ?string $description = null;

    /** @var Collection<int, Round> */
    #[ORM\OneToMany(mappedBy: 'activity', targetEntity: Round::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $rounds;

    #[ORM\ManyToOne(targetEntity: ActivityImportLog::class, inversedBy: 'activities')]
    #[ORM\JoinColumn(name: 'import_log_id', referencedColumnName: 'id', nullable: true)]
    private ?ActivityImportLog $activityImportLog = null;

    #[ORM\Column(name: 'date_created', type: 'datetime_immutable')]
    private \DateTimeImmutable $dateCreated;

    #[ORM\Column(name: 'date_changed', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateChanged = null;

    public function __construct()
    {
        $this->rounds = new ArrayCollection();
        $this->dateCreated = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getActivityId(): string
    {
        return $this->activityId;
    }

    public function setActivityId(string $activityId): self
    {
        $this->activityId = $activityId;
        return $this;
    }

    public function getAthleteId(): int
    {
        return $this->athleteId;
    }

    public function setAthleteId(int $athleteId): self
    {
        $this->athleteId = $athleteId;
        return $this;
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

    public function getDistance(): float
    {
        return $this->distance;
    }

    public function setDistance(float $distance): self
    {
        $this->distance = $distance;
        return $this;
    }

    public function getMovingTime(): int
    {
        return $this->movingTime;
    }

    public function setMovingTime(int $movingTime): self
    {
        $this->movingTime = $movingTime;
        return $this;
    }

    public function getElapsedTime(): int
    {
        return $this->elapsedTime;
    }

    public function setElapsedTime(int $elapsedTime): self
    {
        $this->elapsedTime = $elapsedTime;
        return $this;
    }

    public function getTotalElevationGain(): float
    {
        return $this->totalElevationGain;
    }

    public function setTotalElevationGain(float $totalElevationGain): self
    {
        $this->totalElevationGain = $totalElevationGain;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getWorkoutType(): ?int
    {
        return $this->workoutType;
    }

    public function setWorkoutType(?int $workoutType): self
    {
        $this->workoutType = $workoutType;
        return $this;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getStartDateLocal(): \DateTimeImmutable
    {
        return $this->startDateLocal;
    }

    public function setStartDateLocal(\DateTimeImmutable $startDateLocal): self
    {
        $this->startDateLocal = $startDateLocal;
        return $this;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function setTimezone(string $timezone): self
    {
        $this->timezone = $timezone;
        return $this;
    }

    public function getStartLat(): ?float
    {
        return $this->startLat;
    }

    public function setStartLat(?float $startLat): self
    {
        $this->startLat = $startLat;
        return $this;
    }

    public function getStartLng(): ?float
    {
        return $this->startLng;
    }

    public function setStartLng(?float $startLng): self
    {
        $this->startLng = $startLng;
        return $this;
    }

    public function getEndLat(): ?float
    {
        return $this->endLat;
    }

    public function setEndLat(?float $endLat): self
    {
        $this->endLat = $endLat;
        return $this;
    }

    public function getEndLng(): ?float
    {
        return $this->endLng;
    }

    public function setEndLng(?float $endLng): self
    {
        $this->endLng = $endLng;
        return $this;
    }

    public function getSummaryPolyline(): ?string
    {
        return $this->summaryPolyline;
    }

    public function setSummaryPolyline(?string $summaryPolyline): self
    {
        $this->summaryPolyline = $summaryPolyline;
        return $this;
    }

    public function getAverageSpeed(): ?float
    {
        return $this->averageSpeed;
    }

    public function setAverageSpeed(?float $averageSpeed): self
    {
        $this->averageSpeed = $averageSpeed;
        return $this;
    }

    public function getAverageSpeedTime(): ?\DateTimeImmutable
    {
        return $this->averageSpeedTime;
    }

    public function setAverageSpeedTime(?\DateTimeImmutable $averageSpeedTime): self
    {
        $this->averageSpeedTime = $averageSpeedTime;
        return $this;
    }

    public function getMaxSpeed(): ?float
    {
        return $this->maxSpeed;
    }

    public function setMaxSpeed(?float $maxSpeed): self
    {
        $this->maxSpeed = $maxSpeed;
        return $this;
    }

    public function getAverageHeartrate(): ?float
    {
        return $this->averageHeartrate;
    }

    public function setAverageHeartrate(?float $averageHeartrate): self
    {
        $this->averageHeartrate = $averageHeartrate;
        return $this;
    }

    public function getMaxHeartrate(): ?float
    {
        return $this->maxHeartrate;
    }

    public function setMaxHeartrate(?float $maxHeartrate): self
    {
        $this->maxHeartrate = $maxHeartrate;
        return $this;
    }

    public function getElevHigh(): ?float
    {
        return $this->elevHigh;
    }

    public function setElevHigh(?float $elevHigh): self
    {
        $this->elevHigh = $elevHigh;
        return $this;
    }

    public function getElevLow(): ?float
    {
        return $this->elevLow;
    }

    public function setElevLow(?float $elevLow): self
    {
        $this->elevLow = $elevLow;
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

    public function getRounds(): Collection
    {
        return $this->rounds;
    }

    public function addRound(Round $round): self
    {
        if (!$this->rounds->contains($round)) {
            $this->rounds->add($round);
            $round->setActivity($this);
        }
        return $this;
    }

    public function removeRound(Round $round): self
    {
        if ($this->rounds->removeElement($round)) {
            if ($round->getActivity() === $this) {
                $round->setActivity(null);
            }
        }
        return $this;
    }

    public function getActivityImportLog(): ?ActivityImportLog
    {
        return $this->activityImportLog;
    }

    public function setActivityImportLog(?ActivityImportLog $activityImportLog): self
    {
        $this->activityImportLog = $activityImportLog;
        return $this;
    }

    public function getDateCreated(): \DateTimeImmutable
    {
        return $this->dateCreated;
    }

    public function setDateCreated(\DateTimeImmutable $dateCreated): self
    {
        $this->dateCreated = $dateCreated;
        return $this;
    }

    public function getDateChanged(): ?\DateTimeImmutable
    {
        return $this->dateChanged;
    }

    public function setDateChanged(?\DateTimeImmutable $dateChanged): self
    {
        $this->dateChanged = $dateChanged;
        return $this;
    }

    public function getDistanceInKm(): float
    {
        return round($this->distance / 1000, 2);
    }

    public function getMovingTimeFormatted(): string
    {
        $hours = floor($this->movingTime / 3600);
        $minutes = floor(($this->movingTime % 3600) / 60);
        $seconds = $this->movingTime % 60;
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    public function getPace(): string
    {
        if ($this->distance > 0) {
            $paceSeconds = (int) floor($this->movingTime / ($this->distance / 1000));
            return sprintf('%d:%02d', intdiv($paceSeconds, 60), $paceSeconds % 60);
        }
        return '0:00';
    }
}