<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AnswerGivenRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnswerGivenRepository::class)]
#[ORM\Table(name: 'answers_given')]
class AnswerGiven
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CheckList::class, inversedBy: 'answersGiven')]
    #[ORM\JoinColumn(name: 'checklist_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false)]
    private CheckList $checkList;

    #[ORM\ManyToOne(targetEntity: CheckListItem::class, inversedBy: 'answersGiven')]
    #[ORM\JoinColumn(name: 'checklist_item_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false)]
    private CheckListItem $checkListItem;

    #[ORM\ManyToOne(targetEntity: CheckListField::class, inversedBy: 'answersGiven')]
    #[ORM\JoinColumn(name: 'checklist_field_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false)]
    private CheckListField $checkListField;

    #[ORM\ManyToOne(targetEntity: Answer::class, inversedBy: 'answersGiven')]
    #[ORM\JoinColumn(name: 'answer_id', referencedColumnName: 'id', onDelete: 'SET NULL', nullable: true)]
    private ?Answer $answerValue = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $answer = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCheckListItem(): CheckListItem
    {
        return $this->checkListItem;
    }

    public function setCheckListItem(CheckListItem $checkListItem): self
    {
        $this->checkListItem = $checkListItem;
        return $this;
    }

    public function getCheckListField(): CheckListField
    {
        return $this->checkListField;
    }

    public function setCheckListField(CheckListField $checkListField): self
    {
        $this->checkListField = $checkListField;
        return $this;
    }

    public function getAnswerValue(): ?Answer
    {
        return $this->answerValue;
    }

    public function setAnswerValue(?Answer $answerValue): self
    {
        $this->answerValue = $answerValue;
        return $this;
    }

    public function getAnswer(): ?string
    {
        return $this->answer;
    }

    public function setAnswer(?string $answer): self
    {
        $this->answer = $answer;
        return $this;
    }

    public function __toString(): string
    {
        return $this->answer ?? ($this->answerValue?->getLabel() ?? 'Antwoord #' . $this->id);
    }
}