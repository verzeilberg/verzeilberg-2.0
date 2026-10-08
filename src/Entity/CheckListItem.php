<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TrackableTrait;
use App\Repository\CheckListItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CheckListItemRepository::class)]
#[ORM\Table(name: 'checklist_items')]
#[ORM\HasLifecycleCallbacks]
class CheckListItem
{
    use TrackableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $itemContent = null; // JSON encoded

    #[ORM\ManyToOne(targetEntity: CheckList::class, inversedBy: 'checkListItems')]
    #[ORM\JoinColumn(name: 'check_list_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false)]
    // @phpstan-ignore-next-line doctrine.associationType
    private ?CheckList $checkList = null;

    /** @var Collection<int, AnswerGiven> */
    #[ORM\OneToMany(mappedBy: 'checkListItem', targetEntity: AnswerGiven::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $answersGiven;

    public function __construct()
    {
        $this->answersGiven = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getItemContent(): array
    {
        if (empty($this->itemContent)) {
            return [];
        }
        return json_decode($this->itemContent, true) ?? [];
    }

    public function setItemContent(array $itemContent): self
    {
        $this->itemContent = json_encode($itemContent);
        return $this;
    }

    public function getCheckList(): ?CheckList
    {
        return $this->checkList;
    }

    public function setCheckList(CheckList $checkList): self
    {
        $this->checkList = $checkList;
        return $this;
    }

    /** @return Collection<int, AnswerGiven> */
    public function getAnswersGiven(): Collection
    {
        return $this->answersGiven;
    }

    public function addAnswerGiven(AnswerGiven $answerGiven): self
    {
        if (!$this->answersGiven->contains($answerGiven)) {
            $this->answersGiven->add($answerGiven);
            $answerGiven->setCheckListItem($this);
        }
        return $this;
    }

    public function removeAnswerGiven(AnswerGiven $answerGiven): self
    {
        if ($this->answersGiven->contains($answerGiven)) {
            $this->answersGiven->removeElement($answerGiven);
        }
        return $this;
    }

    /**
     * Get answers organized by field for display
     */
    public function getAnswersGivenForDisplay(): array
    {
        $answersGiven = [];
        foreach ($this->answersGiven as $answerGiven) {
            $field = $answerGiven->getCheckListField();
            // getCheckListField returns non-null CheckListField
            $formType = $field->getFormType();
            $fieldId = $field->getId();
            $itemId = $this->getId();

            if (!isset($answersGiven[$itemId][$fieldId])) {
                $answersGiven[$itemId][$fieldId] = [];
            }

            if (in_array($formType, ['text', 'number', 'textarea', 'date', 'email'])) {
                $answersGiven[$itemId][$fieldId][] = $answerGiven->getAnswer();
            } elseif ($formType === 'checkbox') {
                $answersGiven[$itemId][$fieldId][] = $answerGiven->getAnswerValue()?->getId();
            } elseif (in_array($formType, ['select', 'radio'])) {
                $answersGiven[$itemId][$fieldId][] = $answerGiven->getAnswerValue()?->getId();
            }
        }
        return $answersGiven;
    }

    /** @return Collection<int, AnswerGiven> */
    public function getAnswersGivenForDeleting(): Collection
    {
        return $this->answersGiven;
    }

    public function __toString(): string
    {
        return 'Item #' . $this->id;
    }
}