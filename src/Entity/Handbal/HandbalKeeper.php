<?php

declare(strict_types=1);

namespace App\Entity\Handbal;

use App\Entity\Traits\TrackableTrait;
use App\Repository\Handbal\HandbalKeeperRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: HandbalKeeperRepository::class)]
#[ORM\Table(name: 'handbal_keepers')]
class HandbalKeeper
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

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\Length(max: 10)]
    private ?string $rugnummer = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $actief = true;

    /**
     * @var Collection<int, HandbalScore>
     */
    #[ORM\OneToMany(targetEntity: HandbalScore::class, mappedBy: 'keeper', cascade: ['persist', 'remove'])]
    private Collection $scores;

    public function __construct()
    {
        $this->scores = new ArrayCollection();
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

    public function getRugnummer(): ?string
    {
        return $this->rugnummer;
    }

    public function setRugnummer(?string $rugnummer): self
    {
        $this->rugnummer = $rugnummer;
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
     * @return Collection<int, HandbalScore>
     */
    public function getScores(): Collection
    {
        return $this->scores;
    }

    public function addScore(HandbalScore $score): self
    {
        if (!$this->scores->contains($score)) {
            $this->scores->add($score);
            $score->setKeeper($this);
        }
        return $this;
    }

    public function removeScore(HandbalScore $score): self
    {
        if ($this->scores->contains($score)) {
            $this->scores->removeElement($score);
            // Note: keeper is required in DB, so we don't set it to null
            // The score entity will be removed by cascade or explicitly
        }
        return $this;
    }

    public function __toString(): string
    {
        return $this->naam . ($this->rugnummer ? ' (#' . $this->rugnummer . ')' : '');
    }
}