<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TrackableTrait;
use App\Repository\CheckListRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: CheckListRepository::class)]
#[ORM\Table(name: 'checklists')]
#[UniqueEntity(fields: ['name'], message: 'Er bestaat al een checklist met deze naam')]
#[ORM\HasLifecycleCallbacks]
class CheckList
{
    use TrackableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private string $name;

    /** Who may fill in this checklist outside the admin */
    public const ACCESS_ADMIN = 'admin';
    public const ACCESS_USER = 'user';
    public const ACCESS_PUBLIC = 'public';
    /** Only the owner's friends (see Friendship) */
    public const ACCESS_FRIENDS = 'friends';

    #[ORM\Column(length: 10, options: ['default' => self::ACCESS_ADMIN])]
    private string $access = self::ACCESS_ADMIN;

    /** The user who made it and manages it; null for site checklists that only admins manage */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $owner = null;

    /** Number of columns the item form is shown in: 1 or 2 */
    #[ORM\Column(name: 'layout_columns', type: 'smallint', options: ['default' => 2])]
    private int $layoutColumns = 2;

    /** Number of items per page in the item lists (Items tab and the answers overview) */
    #[ORM\Column(name: 'items_per_page', type: 'smallint', options: ['default' => 20])]
    private int $itemsPerPage = 20;

    /**
     * How the item lists are sorted until someone clicks a column: levels of ['by' => ..., 'dir' => 'asc'|'desc'],
     * the second deciding when the first is equal, and so on. "by" is 'created', 'id' (the order the items were
     * added, as on the old site), 'createdBy' or the id of a field; see CheckListService::findItemsForList().
     * Null: on creation date.
     *
     * @var list<array{by: string, dir: string}>|null
     */
    #[ORM\Column(name: 'default_order', type: 'json', nullable: true)]
    private ?array $defaultOrder = null;

    /** @var Collection<int, CheckListItem> */
    #[ORM\OneToMany(mappedBy: 'checkList', targetEntity: CheckListItem::class, cascade: ['remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['dateCreated' => 'ASC'])]
    private Collection $checkListItems;

    /** @var Collection<int, CheckListField> */
    #[ORM\OneToMany(mappedBy: 'checkList', targetEntity: CheckListField::class, cascade: ['remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['order' => 'ASC'])]
    private Collection $checkListFields;

    /** @var Collection<int, AnswerGiven> */
    #[ORM\OneToMany(mappedBy: 'checkList', targetEntity: AnswerGiven::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $answersGiven;

    public function __construct()
    {
        $this->checkListItems = new ArrayCollection();
        $this->checkListFields = new ArrayCollection();
        $this->answersGiven = new ArrayCollection();
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

    public function getAccess(): string
    {
        return $this->access;
    }

    public function setAccess(string $access): self
    {
        if (!in_array($access, [self::ACCESS_ADMIN, self::ACCESS_USER, self::ACCESS_PUBLIC, self::ACCESS_FRIENDS], true)) {
            throw new \InvalidArgumentException(sprintf('Unknown checklist access "%s".', $access));
        }
        $this->access = $access;
        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): self
    {
        $this->owner = $owner;
        return $this;
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $this->owner !== null && $this->owner->getId() === $user->getId();
    }

    /** A site checklist has no owner, so no friends to share it with (a new checklist gets its owner on save) */
    #[Assert\Callback]
    public function validateFriendsAccess(ExecutionContextInterface $context): void
    {
        if ($this->access === self::ACCESS_FRIENDS && $this->owner === null && $this->id !== null) {
            $context->buildViolation('A site checklist has no owner, so it can\'t be shared with friends.')
                ->atPath('access')
                ->addViolation();
        }
    }

    /** Can be filled in outside the admin (by everyone, logged-in users or the owner's friends) */
    public function isOpenForSubmissions(): bool
    {
        return $this->access !== self::ACCESS_ADMIN && !$this->isDeleted();
    }

    public function getLayoutColumns(): int
    {
        return $this->layoutColumns;
    }

    public function setLayoutColumns(int $layoutColumns): self
    {
        $this->layoutColumns = $layoutColumns;
        return $this;
    }

    public function getItemsPerPage(): int
    {
        return $this->itemsPerPage;
    }

    public function setItemsPerPage(int $itemsPerPage): self
    {
        $this->itemsPerPage = $itemsPerPage;
        return $this;
    }

    /** @return non-empty-list<array{by: string, dir: string}> the order to sort by: when none is set, on creation date */
    public function getDefaultOrder(): array
    {
        return $this->defaultOrder ?: [['by' => 'created', 'dir' => 'asc']];
    }

    /** @return list<array{by: string, dir: string}> the levels that have been chosen (empty: none) */
    public function getChosenOrder(): array
    {
        return $this->defaultOrder ?? [];
    }

    /**
     * Levels without "by" are left out, as is a field that is already used by an earlier level.
     *
     * @param iterable<array{by?: ?string, dir?: ?string}> $levels
     */
    public function setDefaultOrder(iterable $levels): self
    {
        $order = [];
        foreach ($levels as $level) {
            $by = (string) ($level['by'] ?? '');
            if ($by === '' || in_array($by, array_column($order, 'by'), true)) {
                continue;
            }
            $order[] = ['by' => $by, 'dir' => ($level['dir'] ?? '') === 'desc' ? 'desc' : 'asc'];
        }
        $this->defaultOrder = $order ?: null;

        return $this;
    }

    /** @return Collection<int, CheckListItem> */
    public function getCheckListItems(): Collection
    {
        return $this->checkListItems;
    }

    public function addCheckListItem(CheckListItem $item): self
    {
        if (!$this->checkListItems->contains($item)) {
            $this->checkListItems->add($item);
            $item->setCheckList($this);
        }
        return $this;
    }

    public function removeCheckListItem(CheckListItem $item): self
    {
        if ($this->checkListItems->contains($item)) {
            $this->checkListItems->removeElement($item);
        }
        return $this;
    }

    /** @return Collection<int, CheckListField> */
    public function getCheckListFields(): Collection
    {
        return $this->checkListFields->filter(fn(CheckListField $f) => $f->getParent() === null);
    }

    /** @return Collection<int, CheckListField> */
    public function getAllCheckListFields(): Collection
    {
        return $this->checkListFields;
    }

    /** @return Collection<int, CheckListField> */
    public function getCheckListFieldsForOverview(): Collection
    {
        return $this->checkListFields->filter(fn(CheckListField $f) => $f->getShowInOverview() && $f->getParent() === null);
    }

    public function addCheckListField(CheckListField $field): self
    {
        if (!$this->checkListFields->contains($field)) {
            $this->checkListFields->add($field);
            $field->setCheckList($this);
        }
        return $this;
    }

    public function removeCheckListField(CheckListField $field): self
    {
        if ($this->checkListFields->contains($field)) {
            $this->checkListFields->removeElement($field);
        }
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
            $answerGiven->setCheckList($this);
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

    public function __toString(): string
    {
        return $this->name;
    }
}