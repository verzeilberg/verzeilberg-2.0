<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TrackableTrait;
use App\Repository\CheckListFieldRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: CheckListFieldRepository::class)]
#[ORM\Table(name: 'checklist_fields')]
// Answers are stored per "field_<formFieldName>", so it must be unique within a checklist
#[ORM\UniqueConstraint(name: 'uniq_checklist_field_form_name', columns: ['check_list_id', 'form_field_name'])]
#[ORM\HasLifecycleCallbacks]
class CheckListField
{
    use TrackableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: '`order`', type: 'integer', nullable: true)]
    private ?int $order = 1;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $showInOverview = false;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $introText = null;

    // Always set: generateFormFieldName() derives it from the name before insert/update
    #[ORM\Column(length: 255, nullable: false)]
    private string $formFieldName = '';

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $required = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $options = null; // JSON encoded options for select/radio/checkbox

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $requiredMessage = null;

    #[ORM\ManyToOne(targetEntity: CheckList::class, inversedBy: 'checkListFields')]
    #[ORM\JoinColumn(name: 'check_list_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false)]
    private CheckList $checkList;

    #[ORM\ManyToOne(targetEntity: CheckListFieldType::class, inversedBy: 'checkListFields')]
    #[ORM\JoinColumn(name: 'check_list_field_type_id', referencedColumnName: 'id', onDelete: 'SET NULL', nullable: true)]
    private ?CheckListFieldType $fieldType = null;

    /** @var Collection<int, Answer> */
    #[ORM\ManyToMany(targetEntity: Answer::class, inversedBy: 'checkListFields', cascade: ['persist'])]
    #[ORM\JoinTable(name: 'checklist_field_answers')]
    #[ORM\JoinColumn(name: 'checklist_field_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'answer_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $answers;

    /** @var Collection<int, AnswerGiven> */
    #[ORM\OneToMany(mappedBy: 'checkListField', targetEntity: AnswerGiven::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $answersGiven;

    /** @var Collection<int, CheckListField> */
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: CheckListField::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['order' => 'ASC'])]
    private Collection $children;

    #[ORM\ManyToOne(targetEntity: CheckListField::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', onDelete: 'SET NULL', nullable: true)]
    private ?CheckListField $parent = null;

    public function __construct()
    {
        $this->answers = new ArrayCollection();
        $this->answersGiven = new ArrayCollection();
        $this->children = new ArrayCollection();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function generateFormFieldName(): void
    {
        if (empty($this->formFieldName) && !empty($this->name)) {
            $this->formFieldName = $this->clean($this->name);
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): int
    {
        return $this->order ?? 1;
    }

    public function setOrder(?int $order): self
    {
        $this->order = $order ?? 1;
        return $this;
    }

    public function getShowInOverview(): bool
    {
        return $this->showInOverview;
    }

    public function setShowInOverview(bool $showInOverview): self
    {
        $this->showInOverview = $showInOverview;
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

    public function getIntroText(): ?string
    {
        return $this->introText;
    }

    public function setIntroText(?string $introText): self
    {
        $this->introText = $introText;
        return $this;
    }

    public function getFormFieldName(): ?string
    {
        return $this->formFieldName;
    }

    public function setFormFieldName(?string $formFieldName): self
    {
        $this->formFieldName = $formFieldName ? $this->clean($formFieldName) : '';
        return $this;
    }

    public function getRequired(): ?bool
    {
        return $this->required;
    }

    public function setRequired(?bool $required): self
    {
        $this->required = $required;
        return $this;
    }

    public function isRequired(): bool
    {
        return $this->required === true;
    }

    public function getOptions(): ?string
    {
        return $this->options;
    }

    public function setOptions(?string $options): self
    {
        $this->options = $options;
        return $this;
    }

    /**
     * Get options as array (decoded from JSON)
     */
    public function getOptionsAsArray(): array
    {
        if (empty($this->options)) {
            return [];
        }
        // Decode as stored; stripslashes() only as a fallback for old rows saved with escaped quotes,
        // because it would break \u escapes and quotes inside values
        $options = json_decode($this->options, true);
        if (!is_array($options)) {
            $options = json_decode(stripslashes($this->options), true);
        }

        return is_array($options) ? $options : [];
    }

    /**
     * Set options from array (encoded to JSON)
     */
    public function setOptionsAsArray(array $options): self
    {
        $this->options = $options ? json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : null;
        return $this;
    }

    public function getRequiredMessage(): ?string
    {
        return $this->requiredMessage;
    }

    public function setRequiredMessage(?string $requiredMessage): self
    {
        $this->requiredMessage = $requiredMessage;
        return $this;
    }

    public function getCheckList(): CheckList
    {
        return $this->checkList;
    }

    public function setCheckList(CheckList $checkList): self
    {
        $this->checkList = $checkList;
        return $this;
    }

    public function getFieldType(): ?CheckListFieldType
    {
        return $this->fieldType;
    }

    public function setFieldType(?CheckListFieldType $fieldType): self
    {
        $this->fieldType = $fieldType;
        return $this;
    }

    /** @return Collection<int, Answer> */
    public function getAnswers(): Collection
    {
        return $this->answers;
    }

    public function addAnswer(Answer $answer): self
    {
        if (!$this->answers->contains($answer)) {
            $this->answers->add($answer);
        }
        return $this;
    }

    public function removeAnswer(Answer $answer): self
    {
        $this->answers->removeElement($answer);
        return $this;
    }

    public function checkIfAnswerIsLinked(int $answerId): bool
    {
        foreach ($this->answers as $answer) {
            if ($answer->getId() === $answerId) {
                return true;
            }
        }
        return false;
    }

    /** @return Collection<int, AnswerGiven> */
    public function getAnswersGiven(): Collection
    {
        return $this->answersGiven;
    }

    /** @return Collection<int, CheckListField> */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(CheckListField $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }
        return $this;
    }

    public function removeChild(CheckListField $child): self
    {
        if ($this->children->contains($child)) {
            $this->children->removeElement($child);
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }
        return $this;
    }

    public function getParent(): ?CheckListField
    {
        return $this->parent;
    }

    public function setParent(?CheckListField $parent): self
    {
        if ($parent !== null && $parent->getName() !== '') {
            $this->parent = $parent;
        }
        return $this;
    }

    public function hasParent(): bool
    {
        return $this->parent !== null;
    }

    public function getFormType(): string
    {
        return $this->fieldType?->getFormType() ?? 'text';
    }

    /** Form types whose input shows a placeholder (for a select: the text of the empty choice) */
    public const PLACEHOLDER_TYPES = ['text', 'textarea', 'email', 'number', 'url', 'date', 'select'];

    public function supportsPlaceholder(): bool
    {
        return in_array($this->getFormType(), self::PLACEHOLDER_TYPES, true);
    }

    /** The placeholder set in the field options, or null to use the form's default one */
    public function getPlaceholder(): ?string
    {
        $placeholder = trim((string) ($this->getOptionsAsArray()['placeholder'] ?? ''));

        return $placeholder !== '' && $this->supportsPlaceholder() ? $placeholder : null;
    }

    private function clean(string $string): string
    {
        $string = strtolower(str_replace(' ', '_', $string));
        return preg_replace('/[^A-Za-z0-9\-_]/', '', $string);
    }

    public function __toString(): string
    {
        return $this->name;
    }
}