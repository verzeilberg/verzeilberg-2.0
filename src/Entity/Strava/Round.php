<?php

declare(strict_types=1);

namespace App\Entity\Strava;

use App\Repository\Strava\RoundRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RoundRepository::class)]
#[ORM\Table(name: 'strava_rounds')]
class Round
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'distance', type: 'float')]
    private float $distance;

    #[ORM\Column(name: 'elapsed_time', type: 'integer')]
    private int $elapsedTime;

    #[ORM\Column(name: 'elevation_difference', type: 'float', nullable: true)]
    private ?float $elevationDifference = null;

    #[ORM\Column(name: 'moving_time', type: 'integer')]
    private int $movingTime;

    #[ORM\Column(name: 'split', type: 'integer')]
    private int $split;

    #[ORM\Column(name: 'average_speed', type: 'float', nullable: true)]
    private ?float $averageSpeed = null;

    #[ORM\Column(name: 'average_heartrate', type: 'float', nullable: true)]
    private ?float $averageHeartrate = null;

    #[ORM\Column(name: 'pace_zone', type: 'integer', nullable: true)]
    private ?int $paceZone = null;

    #[ORM\ManyToOne(targetEntity: Activity::class, inversedBy: 'rounds')]
    #[ORM\JoinColumn(name: 'activity_id', referencedColumnName: 'id', nullable: false)]
    // Nullable until the round is added to an activity; always set before it is saved
    // @phpstan-ignore-next-line doctrine.associationType
    private ?Activity $activity = null;

    #[ORM\Column(name: 'date_created', type: 'datetime_immutable')]
    private \DateTimeImmutable $dateCreated;

    public function __construct()
    {
        $this->dateCreated = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getElapsedTime(): int
    {
        return $this->elapsedTime;
    }

    public function setElapsedTime(int $elapsedTime): self
    {
        $this->elapsedTime = $elapsedTime;
        return $this;
    }

    public function getElevationDifference(): ?float
    {
        return $this->elevationDifference;
    }

    public function setElevationDifference(?float $elevationDifference): self
    {
        $this->elevationDifference = $elevationDifference;
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

    public function getSplit(): int
    {
        return $this->split;
    }

    public function setSplit(int $split): self
    {
        $this->split = $split;
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

    public function getAverageHeartrate(): ?float
    {
        return $this->averageHeartrate;
    }

    public function setAverageHeartrate(?float $averageHeartrate): self
    {
        $this->averageHeartrate = $averageHeartrate;
        return $this;
    }

    public function getPaceZone(): ?int
    {
        return $this->paceZone;
    }

    public function setPaceZone(?int $paceZone): self
    {
        $this->paceZone = $paceZone;
        return $this;
    }

    public function getActivity(): ?Activity
    {
        return $this->activity;
    }

    public function setActivity(?Activity $activity): self
    {
        $this->activity = $activity;
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

    public function getDistanceInKm(): float
    {
        return round($this->distance / 1000, 2);
    }

    public function getPace(): string
    {
        if ($this->distance > 0 && $this->averageSpeed > 0) {
            $paceSeconds = 1000 / $this->averageSpeed;
            $paceMinutes = floor($paceSeconds / 60);
            $paceSecs = floor($paceSeconds % 60);
            return sprintf('%d:%02d', $paceMinutes, $paceSecs);
        }
        return '0:00';
    }
}