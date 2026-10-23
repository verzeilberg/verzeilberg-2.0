<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CheckList;
use App\Entity\CheckListField;
use App\Entity\CheckListFieldType;
use App\Entity\CheckListItem;
use App\Entity\Answer;
use App\Entity\AnswerGiven;
use App\Entity\User;
use App\Repository\CheckListRepository;
use App\Repository\CheckListFieldRepository;
use App\Repository\CheckListFieldTypeRepository;
use App\Repository\CheckListItemRepository;
use App\Repository\AnswerRepository;
use App\Repository\AnswerGivenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class CheckListService
{
    private const UPLOAD_DIR = 'uploads/checklist';

    /**
     * Uploads are stored outside public/ so they can't be opened by URL without an access check;
     * they are served by CheckListController::itemFile(). The stored value stays "uploads/checklist/...".
     */
    private const STORAGE_DIR = '/var/storage/';

    /** Maximum nesting of fields (fieldset > fieldset > field); the Fields tab renders three levels. */
    public const MAX_FIELD_DEPTH = 3;

    public function __construct(
        private CheckListRepository $checkListRepository,
        private CheckListFieldRepository $checkListFieldRepository,
        private CheckListFieldTypeRepository $checkListFieldTypeRepository,
        private CheckListItemRepository $checkListItemRepository,
        private AnswerRepository $answerRepository,
        private AnswerGivenRepository $answerGivenRepository,
        private EntityManagerInterface $em,
        private SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->em;
    }

    /** @param User|null $owner only the checklists of this user; null for all */
    public function getCheckLists(int $page = 1, int $limit = 10, ?User $owner = null): PaginationInterface
    {
        return $this->checkListRepository->findAllOrdered($page, $limit, $owner);
    }

    /** @param User|null $owner only the checklists of this user; null for all */
    public function getArchivedCheckLists(int $page = 1, int $limit = 10, ?User $owner = null): PaginationInterface
    {
        return $this->checkListRepository->findAllArchived($page, $limit, $owner);
    }

    public function getCheckListById(int $id): ?CheckList
    {
        return $this->checkListRepository->findById($id);
    }

    public function getCheckListByIdWithRelations(int $id): ?CheckList
    {
        return $this->checkListRepository->findByIdWithRelations($id);
    }

    public function createCheckList(): CheckList
    {
        return new CheckList();
    }

    public function saveCheckList(CheckList $checkList, ?UserInterface $user = null): void
    {
        // A new checklist belongs to whoever made it
        if ($checkList->getOwner() === null && $user instanceof User) {
            $checkList->setOwner($user);
        }
        $this->em->persist($checkList);
        $this->em->flush();
    }

    public function updateCheckList(CheckList $checkList, ?UserInterface $user = null): void
    {
        $this->em->flush();
    }

    public function archiveCheckList(CheckList $checkList): void
    {
        $checkList->setDeleted(true);
        $this->em->flush();
    }

    public function unArchiveCheckList(CheckList $checkList): void
    {
        $checkList->setDeleted(false);
        $this->em->flush();
    }

    /**
     * Copies a checklist with all its settings and fields (incl. fieldsets, options and linked answers).
     * Items are not copied. The copy gets a unique name: "Name (copy)", "Name (copy 2)", ...
     */
    /** @param User|null $owner owner of the copy (whoever duplicates it) */
    public function duplicateCheckList(CheckList $original, ?User $owner = null): CheckList
    {
        $copy = new CheckList();
        $copy->setOwner($owner ?? $original->getOwner());
        $copy->setName($this->getUniqueCopyName($original->getName()));
        $copy->setLayoutColumns($original->getLayoutColumns());
        $copy->setItemsPerPage($original->getItemsPerPage());
        $copy->setAccess($original->getAccess());
        $this->em->persist($copy);

        $copyField = function (CheckListField $field, ?CheckListField $parent) use (&$copyField, $copy): void {
            $new = new CheckListField();
            $new->setName($field->getName());
            // Same formFieldName, so conditions that refer to other fields keep working in the copy
            $new->setFormFieldName($field->getFormFieldName());
            $new->setIntroText($field->getIntroText());
            $new->setOrder($field->getOrder());
            $new->setShowInOverview($field->getShowInOverview());
            $new->setRequired($field->getRequired());
            $new->setRequiredMessage($field->getRequiredMessage());
            $new->setOptions($field->getOptions());
            $new->setFieldType($field->getFieldType());
            foreach ($field->getAnswers() as $answer) {
                $new->addAnswer($answer);
            }
            $new->setCheckList($copy);
            if ($parent) {
                $new->setParent($parent);
                $parent->addChild($new);
            }
            $this->em->persist($new);

            foreach ($field->getChildren() as $child) {
                $copyField($child, $new);
            }
        };

        foreach ($this->checkListFieldRepository->findByCheckList($original) as $topLevelField) {
            $copyField($topLevelField, null);
        }

        $this->em->flush();

        return $copy;
    }

    private function getUniqueCopyName(string $name): string
    {
        $candidate = $name . ' (copy)';
        for ($i = 2; $this->checkListRepository->findOneBy(['name' => $candidate]) !== null; ++$i) {
            $candidate = sprintf('%s (copy %d)', $name, $i);
        }

        return $candidate;
    }

    public function deleteCheckList(CheckList $checkList): void
    {
        // Doctrine clears the id after removal, so resolve the upload directory first
        $uploadDir = $this->getCheckListUploadDir($checkList);

        $this->em->remove($checkList);
        $this->em->flush();

        // Items are removed by the database cascade, so remove all their uploads at once
        (new Filesystem())->remove($uploadDir);
    }

    // Field methods
    public function getCheckListFieldsByCheckList(CheckList $checkList): array
    {
        return $this->checkListFieldRepository->findByCheckList($checkList);
    }

    public function getCheckListFieldsByCheckListWithChildren(CheckList $checkList): array
    {
        return $this->checkListFieldRepository->findByCheckListWithChildren($checkList);
    }

    /**
     * Fieldsets of the checklist that $field may be placed in, without making the tree deeper
     * than MAX_FIELD_DEPTH and without placing a field inside itself or one of its own descendants.
     *
     * @return CheckListField[]
     */
    public function getAllowedParentFieldsets(CheckList $checkList, ?CheckListField $field = null): array
    {
        $subtreeHeight = $field ? $this->getFieldSubtreeHeight($field) : 1;

        return array_values(array_filter(
            $this->getCheckListFieldsByCheckListWithChildren($checkList),
            fn (CheckListField $candidate) => $candidate->getFormType() === 'fieldset'
                && ($field === null || !$this->isSameOrDescendant($candidate, $field))
                && $this->getFieldDepth($candidate) + $subtreeHeight <= self::MAX_FIELD_DEPTH
        ));
    }

    /**
     * Fields that $field can depend on with a "show only when" condition: the radio/select/checkbox fields
     * of the checklist, except the field itself and its own sub-fields (that would hide itself).
     *
     * @return array<string, array{name: string, answers: array<string, string>}> keyed by formFieldName
     */
    public function getConditionSources(CheckList $checkList, ?CheckListField $field = null): array
    {
        $sources = [];
        foreach ($this->getCheckListFieldsByCheckListWithChildren($checkList) as $candidate) {
            if (!in_array($candidate->getFormType(), CheckListConditionEvaluator::CONDITION_SOURCE_TYPES, true)
                || ($field !== null && $this->isSameOrDescendant($candidate, $field))) {
                continue;
            }
            $answers = [];
            foreach ($candidate->getAnswers() as $answer) {
                $answers[$answer->getValue()] = $answer->getLabel();
            }
            $sources[$candidate->getFormFieldName()] = ['name' => $candidate->getName(), 'answers' => $answers];
        }

        return $sources;
    }

    /** Depth of a field in the tree; a top-level field has depth 1. */
    private function getFieldDepth(CheckListField $field): int
    {
        $depth = 1;
        for ($parent = $field->getParent(); $parent !== null; $parent = $parent->getParent()) {
            ++$depth;
        }

        return $depth;
    }

    /** Number of levels of a field including its children; a field without children has height 1. */
    private function getFieldSubtreeHeight(CheckListField $field): int
    {
        $height = 1;
        foreach ($field->getChildren() as $child) {
            $height = max($height, 1 + $this->getFieldSubtreeHeight($child));
        }

        return $height;
    }

    private function isSameOrDescendant(CheckListField $candidate, CheckListField $field): bool
    {
        for ($node = $candidate; $node !== null; $node = $node->getParent()) {
            if ($node === $field) {
                return true;
            }
        }

        return false;
    }

    public function getCheckListFieldById(int $id): ?CheckListField
    {
        return $this->checkListFieldRepository->findById($id);
    }

    public function getCheckListFieldByIdWithRelations(int $id): ?CheckListField
    {
        return $this->checkListFieldRepository->findByIdWithRelations($id);
    }

    public function createCheckListField(): CheckListField
    {
        return new CheckListField();
    }

    public function saveCheckListField(CheckListField $field, CheckList $checkList): void
    {
        $field->setCheckList($checkList);
        $this->ensureUniqueFormFieldName($field);
        $this->em->persist($field);
        $this->em->flush();
    }

    public function updateCheckListField(CheckListField $field): void
    {
        $this->ensureUniqueFormFieldName($field);
        $this->em->flush();
    }

    /**
     * Item answers are stored under "field_<formFieldName>", so two fields of one checklist must never share it.
     * "naam" becomes "naam_2", "naam_3", ... when it is already taken.
     */
    private function ensureUniqueFormFieldName(CheckListField $field): void
    {
        if ($field->getFormFieldName() === '' || $field->getFormFieldName() === null) {
            $field->setFormFieldName($field->getName());
        }

        $taken = [];
        foreach ($this->checkListFieldRepository->findByCheckListWithChildren($field->getCheckList()) as $other) {
            if ($other !== $field && ($field->getId() === null || $other->getId() !== $field->getId())) {
                $taken[$other->getFormFieldName()] = true;
            }
        }

        $base = $field->getFormFieldName();
        $candidate = $base;
        for ($i = 2; isset($taken[$candidate]); ++$i) {
            $candidate = $base . '_' . $i;
        }
        if ($candidate !== $base) {
            $field->setFormFieldName($candidate);
        }
    }

    public function removeCheckListField(CheckListField $field): void
    {
        // Sub-fields are removed by cascade, so their answers have to go as well
        $removedFields = [];
        $collect = function (CheckListField $node) use (&$collect, &$removedFields): void {
            $removedFields[] = $node;
            foreach ($node->getChildren() as $child) {
                $collect($child);
            }
        };
        $collect($field);

        $uploadedFiles = [];
        foreach ($this->checkListItemRepository->findAllByCheckList($field->getCheckList()) as $item) {
            $itemContent = $item->getItemContent();
            $changed = false;
            foreach ($removedFields as $removed) {
                $key = 'field_' . $removed->getFormFieldName();
                if (!array_key_exists($key, $itemContent)) {
                    continue;
                }
                if ($removed->getFormType() === 'file') {
                    $uploadedFiles = array_merge($uploadedFiles, $this->getUploadPaths([$itemContent[$key]]));
                }
                unset($itemContent[$key]);
                $changed = true;
            }
            if ($changed) {
                $item->setItemContent($itemContent);
            }
        }

        $this->em->remove($field);
        $this->em->flush();

        // Only remove the files once the field is gone from the database
        (new Filesystem())->remove($uploadedFiles);
    }

    // Field type methods
    public function getAllFieldTypes(): array
    {
        return $this->checkListFieldTypeRepository->findAllOrdered();
    }

    public function findFieldTypeByName(string $name): ?CheckListFieldType
    {
        return $this->checkListFieldTypeRepository->findByName($name);
    }

    public function findFieldTypeById(int $id): ?CheckListFieldType
    {
        return $this->checkListFieldTypeRepository->find($id);
    }

    public function findFieldTypeByFormType(string $formType): ?CheckListFieldType
    {
        return $this->checkListFieldTypeRepository->findByFormType($formType);
    }

    public function createFieldType(): CheckListFieldType
    {
        return new CheckListFieldType();
    }

    public function saveFieldType(CheckListFieldType $fieldType): void
    {
        $this->em->persist($fieldType);
        $this->em->flush();
    }

    // Item methods
    public function getCheckListItemsByCheckList(CheckList $checkList): array
    {
        return $this->checkListItemRepository->findByCheckList($checkList);
    }

    public function getCheckListItemsByCheckListPaginated(CheckList $checkList, int $page = 1, int $limit = 10): PaginationInterface
    {
        return $this->checkListItemRepository->findByCheckListPaginated($checkList, $page, $limit);
    }

    public function getCheckListItemById(int $id): ?CheckListItem
    {
        return $this->checkListItemRepository->findById($id);
    }

    public function getCheckListItemByIdWithAnswers(int $id): ?CheckListItem
    {
        return $this->checkListItemRepository->findByIdWithAnswers($id);
    }

    public function createCheckListItem(): CheckListItem
    {
        return new CheckListItem();
    }

    public function saveCheckListItem(CheckListItem $item, CheckList $checkList, ?User $user = null): void
    {
        $item->setCheckList($checkList);
        $item->setCreatedBy($user);
        $this->em->persist($item);
        $this->em->flush();
        $this->syncItemAnswers($item, $checkList);
    }

    /**
     * Readable values per item for the fields marked "show in overview".
     * Choice values are translated to their answer labels; file values stay a path (rendered as link).
     *
     * @param iterable<CheckListItem>  $items
     * @param CheckListField[]         $overviewFields
     *
     * @return array<int, array<int, string|null>> [item id => [field id => value]]
     */
    public function getItemOverviewValues(iterable $items, array $overviewFields): array
    {
        $labels = [];
        foreach ($overviewFields as $field) {
            foreach ($field->getAnswers() as $answer) {
                $labels[$field->getId()][$answer->getValue()] = $answer->getLabel();
            }
        }

        $values = [];
        foreach ($items as $item) {
            $itemContent = $item->getItemContent();
            foreach ($overviewFields as $field) {
                $value = $itemContent['field_' . $field->getFormFieldName()] ?? null;

                if (is_array($value)) {
                    $value = implode(', ', array_map(
                        fn ($v) => $labels[$field->getId()][$v] ?? (string) $v,
                        $value
                    ));
                } elseif ($value !== null && isset($labels[$field->getId()][$value])) {
                    $value = $labels[$field->getId()][$value];
                }

                $values[$item->getId()][$field->getId()] = ($value === null || $value === '') ? null : (string) $value;
            }
        }

        return $values;
    }

    /**
     * Searches, filters and sorts all items of a checklist (answers list and export).
     * Works on the readable values, so search and sort also match answer labels.
     *
     * @param array<string, string>                 $filters formFieldName => answer value (choice fields)
     * @param list<array{by: string, dir: string}> $order   sort levels; "by" is 'id', 'created', 'createdBy' or a field id
     *
     * @return CheckListItem[]
     */
    public function findItemsForList(CheckList $checkList, string $search = '', array $filters = [], array $order = [['by' => 'created', 'dir' => 'asc']]): array
    {
        $items = $this->checkListItemRepository->findAllByCheckList($checkList);
        $fields = array_values(array_filter(
            $this->getCheckListFieldsByCheckListWithChildren($checkList),
            fn (CheckListField $field) => !in_array($field->getFormType(), ['fieldset', 'hidden'], true)
        ));
        $values = $this->getItemOverviewValues($items, $fields);

        $filters = array_filter($filters, fn (string $value) => $value !== '');
        $search = mb_strtolower(trim($search));

        $items = array_values(array_filter($items, function (CheckListItem $item) use ($filters, $search, $values): bool {
            $content = $item->getItemContent();
            foreach ($filters as $name => $wanted) {
                $stored = $content['field_' . $name] ?? null;
                if (!(is_array($stored) ? in_array($wanted, array_map('strval', $stored), true) : (string) $stored === $wanted)) {
                    return false;
                }
            }
            if ($search === '') {
                return true;
            }
            $haystack = array_merge(
                ['#' . $item->getId(), (string) $item->getId(), $item->getCreatedBy()?->getFullName() ?? ''],
                array_map('strval', array_filter($values[$item->getId()] ?? []))
            );
            foreach ($haystack as $text) {
                if (str_contains(mb_strtolower($text), $search)) {
                    return true;
                }
            }

            return false;
        }));

        $sortValue = function (CheckListItem $item, string $orderBy) use ($values): string|int|null {
            return match ($orderBy) {
                'id' => $item->getId(),
                'created' => $item->getDateCreated()->getTimestamp(),
                'createdBy' => $item->getCreatedBy()?->getFullName(),
                default => $values[$item->getId()][(int) $orderBy] ?? null,
            };
        };
        $compare = function (CheckListItem $a, CheckListItem $b, string $orderBy, string $orderDir) use ($sortValue): int {
            [$va, $vb] = [$sortValue($a, $orderBy), $sortValue($b, $orderBy)];
            // Empty values always last
            if ($va === null || $va === '' || $vb === null || $vb === '') {
                return ($va === null || $va === '') <=> ($vb === null || $vb === '');
            }
            $result = is_numeric($va) && is_numeric($vb) ? $va <=> $vb : strnatcasecmp((string) $va, (string) $vb);

            return $orderDir === 'desc' ? -$result : $result;
        };
        // Level by level: the next one decides when the previous ones are equal; finally the order they were added
        usort($items, function (CheckListItem $a, CheckListItem $b) use ($compare, $order): int {
            foreach ($order as $level) {
                $result = $compare($a, $b, $level['by'], $level['dir']);
                if ($result !== 0) {
                    return $result;
                }
            }

            return $a->getId() <=> $b->getId();
        });

        return $items;
    }

    /**
     * Moves files uploaded through the item form to public/uploads/checklist/{checklist id}/
     * and stores their public path in the item content. A replaced file is removed from disk.
     *
     * @param iterable<CheckListField> $fields
     */
    public function storeItemUploads(CheckListItem $item, CheckList $checkList, FormInterface $form, iterable $fields): void
    {
        $itemContent = $item->getItemContent();
        $relativeDir = self::UPLOAD_DIR . '/' . $checkList->getId();
        $filesystem = new Filesystem();

        foreach ($fields as $field) {
            $fieldName = 'field_' . $field->getFormFieldName();
            if ($field->getFormType() !== 'file' || !$form->has($fieldName)) {
                continue;
            }

            $file = $form->get($fieldName)->getData();
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $safeFilename = $this->slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            $filename = $safeFilename . '-' . uniqid() . '.' . ($file->guessExtension() ?? 'bin');
            $file->move($this->projectDir . self::STORAGE_DIR . $relativeDir, $filename);

            $oldPath = $itemContent[$fieldName] ?? null;
            if (is_string($oldPath) && str_starts_with($oldPath, self::UPLOAD_DIR . '/')) {
                $filesystem->remove($this->projectDir . self::STORAGE_DIR . $oldPath);
            }

            $itemContent[$fieldName] = $relativeDir . '/' . $filename;
        }

        $item->setItemContent($itemContent);
    }

    public function updateCheckListItem(CheckListItem $item, ?User $user = null): void
    {
        // Files the item referred to when it was loaded (Doctrine keeps the original database values)
        $originalContent = $this->em->getUnitOfWork()->getOriginalEntityData($item)['itemContent'] ?? null;
        $filesBefore = $this->getUploadPaths(json_decode((string) $originalContent, true) ?: []);

        $item->setDateChanged(new \DateTimeImmutable());
        $item->setChangedBy($user);
        $this->em->flush();

        // Remove files that are no longer referenced, e.g. a field that became hidden by its condition or a replaced file
        (new Filesystem())->remove(array_diff($filesBefore, $this->getUploadPaths($item->getItemContent())));
        $this->syncItemAnswers($item, $item->getCheckList());
    }

    /**
     * Rebuilds the AnswerGiven records of an item from its itemContent, which is the single source of truth.
     * Every save starts from scratch, so a cleared answer also disappears here.
     * Choice fields get one record per selected value, linked to the field's Answer with that value.
     */
    public function syncItemAnswers(CheckListItem $item, CheckList $checkList): void
    {
        foreach ($item->getAnswersGiven()->toArray() as $answerGiven) {
            $item->removeAnswerGiven($answerGiven);
            $this->em->remove($answerGiven);
        }

        // All fields, including those inside a fieldset ($checkList->getCheckListFields() only returns top-level fields)
        $fieldMap = [];
        foreach ($this->checkListFieldRepository->findByCheckListWithChildren($checkList) as $field) {
            $fieldMap['field_' . $field->getFormFieldName()] = $field;
        }

        foreach ($item->getItemContent() as $fieldName => $value) {
            $field = $fieldMap[$fieldName] ?? null;
            if ($field === null || $value === null || $value === '' || $value === []) {
                continue;
            }

            if (in_array($field->getFormType(), ['checkbox', 'radio', 'select'], true)) {
                $answersByValue = [];
                foreach ($field->getAnswers() as $answer) {
                    $answersByValue[$answer->getValue()] = $answer;
                }
                foreach ((array) $value as $selected) {
                    $this->addAnswerGiven($item, $checkList, $field, (string) $selected, $answersByValue[(string) $selected] ?? null);
                }
            } else {
                $this->addAnswerGiven($item, $checkList, $field, is_scalar($value) ? (string) $value : (string) json_encode($value), null);
            }
        }

        $this->em->flush();
    }

    private function addAnswerGiven(CheckListItem $item, CheckList $checkList, CheckListField $field, string $answer, ?Answer $answerValue): void
    {
        $answerGiven = new AnswerGiven();
        $answerGiven->setCheckList($checkList);
        $answerGiven->setCheckListItem($item);
        $answerGiven->setCheckListField($field);
        $answerGiven->setAnswer($answer);
        $answerGiven->setAnswerValue($answerValue);
        $item->addAnswerGiven($answerGiven);
        $this->em->persist($answerGiven);
    }

    public function removeCheckListItem(CheckListItem $item): void
    {
        $uploadedFiles = $this->getItemUploadPaths($item);

        $this->em->remove($item);
        $this->em->flush();

        // Only remove the files once the item is gone from the database
        (new Filesystem())->remove($uploadedFiles);
    }

    /**
     * Absolute path of a stored upload value ("uploads/checklist/..."), or null when the value isn't a checklist upload.
     */
    public function getUploadAbsolutePath(mixed $value): ?string
    {
        if (!is_string($value) || !str_starts_with($value, self::UPLOAD_DIR . '/') || str_contains($value, '..')) {
            return null;
        }

        return $this->projectDir . self::STORAGE_DIR . $value;
    }

    private function getCheckListUploadDir(CheckList $checkList): string
    {
        return $this->projectDir . self::STORAGE_DIR . self::UPLOAD_DIR . '/' . $checkList->getId();
    }

    /**
     * Absolute paths of the files uploaded for this item (see storeItemUploads()).
     *
     * @return string[]
     */
    private function getItemUploadPaths(CheckListItem $item): array
    {
        return $this->getUploadPaths($item->getItemContent());
    }

    /**
     * @param array<mixed> $values stored item values
     *
     * @return string[] absolute paths of the values that are checklist uploads
     */
    private function getUploadPaths(array $values): array
    {
        $paths = [];
        foreach ($values as $value) {
            if (($path = $this->getUploadAbsolutePath($value)) !== null) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    // Answer methods
    public function getAllAnswers(): array
    {
        return $this->answerRepository->findAllOrdered();
    }

    public function getAnswerById(int $id): ?Answer
    {
        return $this->answerRepository->findById($id);
    }

    public function findAnswerByLabelAndValue(string $label, string $value): ?Answer
    {
        return $this->answerRepository->findByLabelAndValue($label, $value);
    }

    public function createAnswer(): Answer
    {
        return new Answer();
    }

    public function saveAnswer(Answer $answer): void
    {
        $this->em->persist($answer);
        $this->em->flush();
    }

    // Given answer methods
    public function getGivenAnswersByChecklistId(int $checklistId): array
    {
        $checkList = $this->checkListRepository->findById($checklistId);
        if (!$checkList) {
            return [];
        }
        return $this->answerGivenRepository->findByCheckList($checkList);
    }

    public function getGivenAnswersByChecklistAndItem(CheckList $checkList, CheckListItem $item): array
    {
        return $this->answerGivenRepository->findByCheckListAndItem($checkList, $item);
    }

    public function getGivenAnswersByItem(CheckListItem $item): array
    {
        return $this->answerGivenRepository->findByCheckListItem($item);
    }

    public function saveAnswerGiven(AnswerGiven $answerGiven): void
    {
        $this->em->persist($answerGiven);
        $this->em->flush();
    }
}