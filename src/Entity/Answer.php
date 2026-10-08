<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AnswerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnswerRepository::class)]
#[ORM\Table(name: 'answers')]
class Answer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $label;

    #[ORM\Column(length: 255)]
    private string $value;

    /** @var Collection<int, AnswerGiven> */
    #[ORM\OneToMany(mappedBy: 'answerValue', targetEntity: AnswerGiven::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $answersGiven;

    /** @var Collection<int, CheckListField> */
    #[ORM\ManyToMany(targetEntity: CheckListField::class, mappedBy: 'answers')]
    private Collection $checkListFields;

    public function __construct()
    {
        $this->answersGiven = new ArrayCollection();
        $this->checkListFields = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): self
    {
        $this->value = $value;
        return $this;
    }

    /** @return Collection<int, AnswerGiven> */
    public function getAnswersGiven(): Collection
    {
        return $this->answersGiven;
    }

    /** @return Collection<int, CheckListField> */
    public function getCheckListFields(): Collection
    {
        return $this->checkListFields;
    }

    public function addCheckListField(CheckListField $field): self
    {
        if (!$this->checkListFields->contains($field)) {
            $this->checkListFields->add($field);
            $field->addAnswer($this);
        }
        return $this;
    }

    public function removeCheckListField(CheckListField $field): self
    {
        if ($this->checkListFields->contains($field)) {
            $this->checkListFields->removeElement($field);
            $field->removeAnswer($this);
        }
        return $this;
    }

    public function __toString(): string
    {
        return $this->label;
    }
}