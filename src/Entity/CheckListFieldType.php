<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CheckListFieldTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: CheckListFieldTypeRepository::class)]
#[ORM\Table(name: 'checklist_field_types')]
#[UniqueEntity(fields: ['name'], message: 'A field type with this name already exists')]
class CheckListFieldType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private string $name;

    #[ORM\Column(length: 50)]
    private string $formType; // text, number, textarea, checkbox, select, radio, date, email, etc.

    /** @var Collection<int, CheckListField> */
    #[ORM\OneToMany(mappedBy: 'fieldType', targetEntity: CheckListField::class, cascade: ['remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['order' => 'ASC'])]
    private Collection $checkListFields;

    public function __construct()
    {
        $this->checkListFields = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getFormType(): string
    {
        return $this->formType;
    }

    public function setFormType(string $formType): self
    {
        $this->formType = $formType;
        return $this;
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
            $field->setFieldType($this);
        }
        return $this;
    }

    public function removeCheckListField(CheckListField $field): self
    {
        if ($this->checkListFields->contains($field)) {
            $this->checkListFields->removeElement($field);
            if ($field->getFieldType() === $this) {
                $field->setFieldType(null);
            }
        }
        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}