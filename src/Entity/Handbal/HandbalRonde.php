<?php

declare(strict_types=1);

namespace App\Entity\Handbal;

use App\Entity\Traits\TrackableTrait;
use App\Repository\Handbal\HandbalRondeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: HandbalRondeRepository::class)]
#[ORM\Table(name: 'handbal_rondes')]
class HandbalRonde
{
    use TrackableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: HandbalSeizoen::class, inversedBy: 'rondes')]
    #[ORM\JoinColumn(name: 'seizoen_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private HandbalSeizoen $seizoen;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Assert\NotNull]
    private ?\DateTimeInterface $datum = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $omschrijving = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $actief = true;

    #[ORM\ManyToOne(targetEntity: HandbalKeeper::class)]
    #[ORM\JoinColumn(name: 'keeper_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?HandbalKeeper $keeper = null;

    /**
     * @var Collection<int, HandbalScore>
     */
    #[ORM\OneToMany(targetEntity: HandbalScore::class, mappedBy: 'ronde', cascade: ['persist', 'remove'])]
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

    public function getSeizoen(): ?HandbalSeizoen
    {
        return $this->seizoen;
    }

    public function setSeizoen(?HandbalSeizoen $seizoen): self
    {
        $this->seizoen = $seizoen;
        return $this;
    }

    public function getDatum(): ?\DateTimeInterface
    {
        return $this->datum;
    }

    public function setDatum(\DateTimeInterface|string|null $datum): self
    {
        if (is_string($datum)) {
            $this->datum = new \DateTime($datum);
        } else {
            $this->datum = $datum;
        }
        return $this;
    }

    public function getOmschrijving(): ?string
    {
        return $this->omschrijving;
    }

    public function setOmschrijving(?string $omschrijving): self
    {
        $this->omschrijving = $omschrijving;
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

    public function getKeeper(): ?HandbalKeeper
    {
        return $this->keeper;
    }

    public function setKeeper(?HandbalKeeper $keeper): self
    {
        $this->keeper = $keeper;
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
            $score->setRonde($this);
        }
        return $this;
    }

    public function removeScore(HandbalScore $score): self
    {
        if ($this->scores->contains($score)) {
            $this->scores->removeElement($score);
            // Note: ronde is required in DB, so we don't set it to null
            // The score entity will be removed by cascade or explicitly
        }
        return $this;
    }

    public function __toString(): string
    {
        return $this->omschrijving ?? $this->datum->format('d-m-Y');
    }
}