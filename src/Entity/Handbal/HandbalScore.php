<?php

declare(strict_types=1);

namespace App\Entity\Handbal;

use App\Entity\Traits\TrackableTrait;
use App\Repository\Handbal\HandbalScoreRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: HandbalScoreRepository::class)]
#[ORM\Table(name: 'handbal_scores')]
#[ORM\UniqueConstraint(name: 'unique_speler_ronde', columns: ['speler_id', 'ronde_id'])]
class HandbalScore
{
    use TrackableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: HandbalSpeler::class, inversedBy: 'scores')]
    #[ORM\JoinColumn(name: 'speler_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?HandbalSpeler $speler = null;

    #[ORM\ManyToOne(targetEntity: HandbalKeeper::class, inversedBy: 'scores')]
    #[ORM\JoinColumn(name: 'keeper_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private HandbalKeeper $keeper;

    #[ORM\ManyToOne(targetEntity: HandbalRonde::class, inversedBy: 'scores')]
    #[ORM\JoinColumn(name: 'ronde_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private HandbalRonde $ronde;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $gescored = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSpeler(): ?HandbalSpeler
    {
        return $this->speler;
    }

    public function setSpeler(?HandbalSpeler $speler): self
    {
        $this->speler = $speler;
        return $this;
    }

    public function getKeeper(): HandbalKeeper
    {
        return $this->keeper;
    }

    public function setKeeper(HandbalKeeper $keeper): self
    {
        $this->keeper = $keeper;
        return $this;
    }

    public function getRonde(): HandbalRonde
    {
        return $this->ronde;
    }

    public function setRonde(HandbalRonde $ronde): self
    {
        $this->ronde = $ronde;
        return $this;
    }

    public function isGescored(): bool
    {
        return $this->gescored;
    }

    public function setGescored(bool $gescored): self
    {
        $this->gescored = $gescored;
        return $this;
    }

    public function __toString(): string
    {
        return ($this->speler ? $this->speler->getNaam() : 'Geen speler') . ' - ' . $this->ronde->__toString();
    }
}