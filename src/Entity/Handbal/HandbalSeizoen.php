<?php

declare(strict_types=1);

namespace App\Entity\Handbal;

use App\Entity\Traits\TrackableTrait;
use App\Repository\Handbal\HandbalSeizoenRepository;
use App\Validator\Constraint\NoOverlappingSeasons;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: HandbalSeizoenRepository::class)]
#[ORM\Table(name: 'handbal_seizoenen')]
#[NoOverlappingSeasons]
class HandbalSeizoen
{
    use TrackableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $naam;

    #[ORM\Column(name: 'start_datum', type: 'date')]
    #[Assert\NotNull]
    #[Assert\LessThanOrEqual(propertyPath: 'eindDatum', message: 'Start datum mag niet na eind datum liggen.')]
    private \DateTimeInterface $startDatum;

    #[ORM\Column(name: 'eind_datum', type: 'date')]
    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual(propertyPath: 'startDatum', message: 'Eind datum mag niet voor start datum liggen.')]
    private \DateTimeInterface $eindDatum;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $actief = true;

    /**
     * @var Collection<int, HandbalRonde>
     */
    #[ORM\OneToMany(targetEntity: HandbalRonde::class, mappedBy: 'seizoen', cascade: ['persist', 'remove'])]
    private Collection $rondes;

    public function __construct()
    {
        $this->rondes = new ArrayCollection();
        $this->startDatum = new \DateTime();
        $this->eindDatum = new \DateTime();
        $this->dateCreated = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNaam(): string
    {
        return $this->naam;
    }

    public function setNaam(string $naam): self
    {
        $this->naam = $naam;
        return $this;
    }

    public function getStartDatum(): \DateTimeInterface
    {
        return $this->startDatum;
    }

    public function setStartDatum(\DateTimeInterface|string $startDatum): self
    {
        if (is_string($startDatum)) {
            $this->startDatum = new \DateTime($startDatum);
        } else {
            $this->startDatum = $startDatum;
        }
        return $this;
    }

    public function getEindDatum(): \DateTimeInterface
    {
        return $this->eindDatum;
    }

    public function setEindDatum(\DateTimeInterface|string $eindDatum): self
    {
        if (is_string($eindDatum)) {
            $this->eindDatum = new \DateTime($eindDatum);
        } else {
            $this->eindDatum = $eindDatum;
        }
        return $this;
    }

    public function isActief(): bool
    {
        return $this->actief;
    }

    public function setActief(bool $actief): self
    {
        $this->actief = $actief;
        return $this;
    }

    /**
     * @return Collection<int, HandbalRonde>
     */
    public function getRondes(): Collection
    {
        return $this->rondes;
    }

    public function addRonde(HandbalRonde $ronde): self
    {
        if (!$this->rondes->contains($ronde)) {
            $this->rondes->add($ronde);
            $ronde->setSeizoen($this);
        }
        return $this;
    }

    public function removeRonde(HandbalRonde $ronde): self
    {
        if ($this->rondes->contains($ronde)) {
            $this->rondes->removeElement($ronde);
            if ($ronde->getSeizoen() === $this) {
                $ronde->setSeizoen(null);
            }
        }
        return $this;
    }

    public function __toString(): string
    {
        return $this->naam;
    }
}