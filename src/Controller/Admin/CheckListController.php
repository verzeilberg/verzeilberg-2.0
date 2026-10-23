<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CheckList;
use App\Entity\CheckListField;
use App\Entity\CheckListFieldType;
use App\Entity\CheckListItem;
use App\Entity\Answer;
use App\Form\CheckListType;
use App\Form\CheckListFieldFormType;
use App\Form\CheckListItemType;
use App\Form\AnswerType;
use App\Form\CheckListFieldTypeType;
use App\Entity\User;
use App\Security\Voter\CheckListVoter;
use App\Service\CheckListConditionEvaluator;
use App\Service\CheckListExporter;
use App\Service\CheckListService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Users with "checklist.manage" (and admins) manage every checklist; users with "checklist.own" only their own
 * (see CheckListVoter). Field types and editing the shared answers are for "checklist.manage" only.
 */
#[Route('/admin/checklist')]
class CheckListController extends AbstractController
{
    public function __construct(
        private CheckListService $checkListService,
        private EntityManagerInterface $em
    ) {}

    #[Route('/', name: 'admin_checklist_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        /** @var PaginationInterface $pagination */
        $pagination = $this->checkListService->getCheckLists($page, 20, $this->ownerFilter());

        return $this->render('admin/checklist/index.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    #[Route('/archive', name: 'admin_checklist_archive', methods: ['GET'])]
    public function archive(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        /** @var PaginationInterface $pagination */
        $pagination = $this->checkListService->getArchivedCheckLists($page, 20, $this->ownerFilter());

        return $this->render('admin/checklist/archive.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    #[Route('/new', name: 'admin_checklist_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $checkList = $this->checkListService->createCheckList();
        $form = $this->createForm(CheckListType::class, $checkList);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->checkListService->saveCheckList($checkList, $this->getUser());
            $this->addFlash('success', 'Checklist aangemaakt.');

            return $this->redirectToRoute('admin_checklist_edit', ['id' => $checkList->getId()]);
        }

        return $this->render('admin/checklist/new.html.twig', [
            'form' => $form,
            'checkList' => $checkList,
        ]);
    }

    #[Route('/{id<\d+>}/edit', name: 'admin_checklist_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id, PaginatorInterface $paginator): Response
    {
        $checkList = $this->checkListService->getCheckListByIdWithRelations($id);

        if (!$checkList) {
            throw $this->createNotFoundException('Checklist niet gevonden');
        }
        $this->denyAccessUnlessGranted(CheckListVoter::MANAGE, $checkList);

        $form = $this->createForm(CheckListType::class, $checkList);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->checkListService->updateCheckList($checkList, $this->getUser());
            $this->addFlash('success', 'Checklist opgeslagen.');

            return $this->redirectToRoute('admin_checklist_edit', ['id' => $checkList->getId()]);
        }

        $fields = $this->checkListService->getCheckListFieldsByCheckListWithChildren($checkList);

        $page = $request->query->getInt('page', 1);
        // In the checklist's default order, like the answers list
        $items = $this->checkListService->findItemsForList($checkList, '', [], $checkList->getDefaultOrder());
        /** @var PaginationInterface $pagination */
        $pagination = $paginator->paginate($items, $page, $checkList->getItemsPerPage());

        // Fieldsets only group fields and have no value, so they never get a column
        $overviewFields = array_values(array_filter(
            $fields,
            fn ($field) => $field->getShowInOverview() && $field->getFormType() !== 'fieldset'
        ));

        return $this->render('admin/checklist/edit.html.twig', [
            'form' => $form,
            'checkList' => $checkList,
            'fields' => $fields,
            'pagination' => $pagination,
            'overviewFields' => $overviewFields,
            'overviewValues' => $this->checkListService->getItemOverviewValues($pagination, $overviewFields),
        ]);
    }

    #[Route('/{id<\d+>}', name: 'admin_checklist_show', methods: ['GET'])]
    public function show(Request $request, int $id, PaginatorInterface $paginator): Response
    {
        $checkList = $this->checkListService->getCheckListByIdWithRelations($id);

        if (!$checkList) {
            throw $this->createNotFoundException('Checklist niet gevonden');
        }
        $this->denyAccessUnlessGranted(CheckListVoter::MANAGE, $checkList);

        $fields = $this->checkListService->getCheckListFieldsByCheckListWithChildren($checkList);

        $listQuery = $this->getListQuery($request, $checkList);
        $items = $this->checkListService->findItemsForList($checkList, $listQuery['search'], $listQuery['filters'], $listQuery['order']);
        /** @var PaginationInterface $pagination */
        $pagination = $paginator->paginate($items, $request->query->getInt('page', 1), $checkList->getItemsPerPage());

        // Choice fields can be used as filter
        $filterFields = array_values(array_filter(
            $fields,
            fn ($field) => in_array($field->getFormType(), CheckListConditionEvaluator::CONDITION_SOURCE_TYPES, true)
        ));

        // Same columns as the Items tab: the fields marked "show in overview"
        $overviewFields = array_values(array_filter(
            $fields,
            fn ($field) => $field->getShowInOverview() && $field->getFormType() !== 'fieldset'
        ));

        return $this->render('admin/checklist/show.html.twig', [
            'checkList' => $checkList,
            'pagination' => $pagination,
            'overviewFields' => $overviewFields,
            'overviewValues' => $this->checkListService->getItemOverviewValues($pagination, $overviewFields),
            'filterFields' => $filterFields,
            'listQuery' => $listQuery,
            'totalItems' => count($this->checkListService->getCheckListItemsByCheckList($checkList)),
        ]);
    }

    /**
     * Search, filter and sort parameters of the answers list (also used by the export).
     * Not "sort"/"direction": KnpPaginator would try to sort the array itself on those.
     *
     * The order: a clicked column first, then the checklist's default order (which alone applies without a click).
     * orderBy/orderDir are the first level, for the column headers.
     *
     * @return array{search: string, filters: array<string, string>, order: list<array{by: string, dir: string}>, orderBy: string, orderDir: string}
     */
    private function getListQuery(Request $request, CheckList $checkList): array
    {
        $filters = $request->query->all('filter');
        $order = $checkList->getDefaultOrder();
        if ($request->query->has('order_by')) {
            $clicked = ['by' => (string) $request->query->get('order_by'), 'dir' => $request->query->get('order_dir') === 'desc' ? 'desc' : 'asc'];
            $order = [$clicked, ...array_values(array_filter($order, fn (array $level) => $level['by'] !== $clicked['by']))];
        }

        return [
            'search' => trim((string) $request->query->get('q', '')),
            'filters' => array_filter(array_map(fn ($value) => is_string($value) ? $value : '', $filters), fn (string $value) => $value !== ''),
            'order' => $order,
            'orderBy' => $order[0]['by'],
            'orderDir' => $order[0]['dir'],
        ];
    }

    #[Route('/{id<\d+>}/export/{format<csv|xlsx>}', name: 'admin_checklist_export', methods: ['GET'])]
    public function export(Request $request, int $id, string $format, CheckListExporter $exporter): Response
    {
        $checkList = $this->checkListService->getCheckListById($id);

        if (!$checkList) {
            throw $this->createNotFoundException('Checklist niet gevonden');
        }
        $this->denyAccessUnlessGranted(CheckListVoter::MANAGE, $checkList);

        // Export the same selection as the answers list shows (search, filters, sorting)
        $listQuery = $this->getListQuery($request, $checkList);
        $items = $this->checkListService->findItemsForList($checkList, $listQuery['search'], $listQuery['filters'], $listQuery['order']);

        return $exporter->createResponse($checkList, $format, $items);
    }

    #[Route('/item/{id<\d+>}', name: 'admin_checklist_item_show', methods: ['GET'])]
    public function showItem(int $id, CheckListConditionEvaluator $conditionEvaluator): Response
    {
        $item = $this->checkListService->getCheckListItemById($id);

        if (!$item) {
            throw $this->createNotFoundException('Item niet gevonden');
        }
        $this->denyAccessUnlessGranted(CheckListVoter::MANAGE, $item->getCheckList());

        $checkList = $item->getCheckList();
        $fields = $this->checkListService->getCheckListFieldsByCheckListWithChildren($checkList);

        // Read-only answers: every field that holds a value (fieldsets only group, hidden fields stay hidden)
        $valueFields = array_values(array_filter(
            $fields,
            fn ($field) => !in_array($field->getFormType(), ['fieldset', 'hidden'], true)
        ));

        return $this->render('admin/checklist/item_show.html.twig', [
            'checkList' => $checkList,
            'item' => $item,
            'fields' => $fields,
            'itemAnswers' => $this->checkListService->getItemOverviewValues([$item], $valueFields)[$item->getId()] ?? [],
            // Fields whose "show only when" condition wasn't met don't apply to this item
            'hiddenFieldIds' => $conditionEvaluator->getHiddenFieldIdsForContent($fields, $item->getItemContent()),
        ]);
    }

    #[Route('/{id<\d+>}/archive', name: 'admin_checklist_archive_item', methods: ['POST'])]
    public function archiveItem(Request $request, int $id): JsonResponse
    {
        if (!$this->isCsrfTokenValid('archive' . $id, $request->headers->get('X-CSRF-Token') ?? $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Ongeldig beveiligingstoken']);
        }

        $checkList = $this->checkListService->getCheckListById($id);

        if (!$checkList) {
            return new JsonResponse(['success' => false, 'message' => 'Checklist niet gevonden']);
        }
        $this->denyAccessUnlessGranted(CheckListVoter::MANAGE, $checkList);

        $this->checkListService->archiveCheckList($checkList);

        return new JsonResponse(['success' => true]);
    }

    #[Route('/{id<\d+>}/duplicate', name: 'admin_checklist_duplicate', methods: ['POST'])]
    public function duplicate(Request $request, int $id): JsonResponse
    {
        if (!$this->isCsrfTokenValid('duplicate' . $id, $request->headers->get('X-CSRF-Token') ?? $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Ongeldig beveiligingstoken']);
        }

        $checkList = $this->checkListService->getCheckListById($id);

        if (!$checkList) {
            return new JsonResponse(['success' => false, 'message' => 'Checklist niet gevonden']);
        }
        $this->denyAccessUnlessGranted(CheckListVoter::MANAGE, $checkList);

        $copy = $this->checkListService->duplicateCheckList($checkList, $this->getAppUser());
        $this->addFlash('success', sprintf('Checklist gekopieerd als "%s".', $copy->getName()));

        return new JsonResponse(['success' => true]);
    }

    #[Route('/{id<\d+>}/unarchive', name: 'admin_checklist_unarchive', methods: ['POST'])]
    public function unarchive(Request $request, int $id): JsonResponse
    {
        if (!$this->isCsrfTokenValid('unarchive' . $id, $request->headers->get('X-CSRF-Token') ?? $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Ongeldig beveiligingstoken']);
        }

        $checkList = $this->checkListService->getCheckListById($id);

        if (!$checkList) {
            return new JsonResponse(['success' => false, 'message' => 'Checklist niet gevonden']);
        }
        $this->denyAccessUnlessGranted(CheckListVoter::MANAGE, $checkList);

        $this->checkListService->unArchiveCheckList($checkList);

        return new JsonResponse(['success' => true]);
    }

    #[Route('/{id<\d+>}', name: 'admin_checklist_delete', methods: ['POST'])]
    public function delete(Request $request, int $id): JsonResponse
    {
        if (!$this->isCsrfTokenValid('delete' . $id, $request->headers->get('X-CSRF-Token') ?? $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Ongeldig beveiligingstoken']);
        }

        $checkList = $this->checkListService->getCheckListById($id);

        if (!$checkList) {
            return new JsonResponse(['success' => false, 'message' => 'Checklist niet gevonden']);
        }
        $this->denyAccessUnlessGranted(CheckListVoter::MANAGE, $checkList);

        $this->checkListService->deleteCheckList($checkList);

        return new JsonResponse(['success' => true]);
    }

    // Field management

    #[Route('/{id<\d+>}/fields/new', name: 'admin_checklist_field_new', methods: ['GET', 'POST'])]
    public function newField(Request $request, int $id): Response
    {
        $checkList = $this->checkListService->getCheckListById($id);

        if (!$checkList) {
            throw $this->createNotFoundException('Checklist niet gevonden');
        }
        $this->denyAccessUnlessGranted(CheckListVoter::MANAGE, $checkList);

        $field = $this->checkListService->createCheckListField();
        $form = $this->createForm(CheckListFieldFormType::class, $field, [
            'parent_choices' => $this->checkListService->getAllowedParentFieldsets($checkList),
            'condition_sources' => $this->checkListService->getConditionSources($checkList),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->checkListService->saveCheckListField($field, $checkList);
            $this->addFlash('success', 'Veld aangemaakt.');

            return $this->redirectToRoute('admin_checklist_edit', ['id' => $checkList->getId(), '_fragment' => 'fields']);
        }

        $fieldTypes = $this->checkListService->getAllFieldTypes();
        $answers = $this->checkListService->getAllAnswers();

        return $this->render('admin/checklist/field_form.html.twig', [
            'form' => $form,
            'checkList' => $checkList,
            'fieldTypes' => $fieldTypes,
            'answers' => $answers,
            'action' => 'new',
        ]);
    }

    #[Route('/field/{id<\d+>}/edit', name: 'admin_checklist_field_edit', methods: ['GET', 'POST'])]
    public function editField(Request $request, int $id): Response
    {
        $field = $this->checkListService->getCheckListFieldByIdWithRelations($id);

        if (!$field) {
            throw $this->createNotFoundException('Veld niet gevonden');
        }
        $this->denyAccessUnlessGranted(CheckListVoter::MANAGE, $field->getCheckList());

        $checkList = $field->getCheckList();

        $form = $this->createForm(CheckListFieldFormType::class, $field, [
            'parent_choices' => $this->checkListService->getAllowedParentFieldsets($checkList, $field),
            'condition_sources' => $this->checkListService->getConditionSources($checkList, $field),
        ]);

        // Default value, placeholder and "show only when" condition live in the options JSON
        $storedOptions = $field->getOptionsAsArray();
        $form->get('defaultValue')->setData($storedOptions['defaultValue'] ?? null);
        $form->get('placeholder')->setData($storedOptions['placeholder'] ?? null);
        if ($condition = CheckListConditionEvaluator::getCondition($field)) {
            $form->get('conditionField')->setData($condition['field']);
            $form->get('conditionValues')->setData(json_encode($condition['values']));
        }

        // Manually set option field data from entity options
        $options = $field->getOptionsAsArray();
        $formType = $field->getFieldType()?->getFormType();
        $isCheckboxType = $formType === 'checkbox';
        $isDateType = $formType === 'date';
        $isTimeType = $formType === 'time';
        $isDateTimeType = $formType === 'datetime';
        $isEmailType = $formType === 'email';
        $isUrlType = $formType === 'url';
        $isRadioType = $formType === 'radio';
        $isFileType = $formType === 'file';

        $noOptionsTypes = [$isCheckboxType, $isTimeType, $isDateTimeType, $isUrlType, $isRadioType];

        if (!in_array(true, $noOptionsTypes, true)) {
            $optionFields = ['readonly', 'disabled', 'maxlength', 'maxLengthValue', 'autofocus', 'resize', 'measurements', 'textareaWidth', 'textareaHeight', 'measurementsType', 'preapp', 'apppreinput'];
            foreach ($optionFields as $optionField) {
                if (isset($options[$optionField]) && $form->has($optionField)) {
                    $value = $options[$optionField];
                    if (in_array($optionField, ['readonly', 'disabled', 'maxlength', 'autofocus', 'resize', 'measurements'])) {
                        $value = (bool)$value;
                    }
                    $form->get($optionField)->setData($value);
                }
            }
        }

        // Handle File field options
        if ($isFileType) {
            if (isset($options['maxUploadSize']) && $form->has('maxUploadSize')) {
                $form->get('maxUploadSize')->setData((int)$options['maxUploadSize']);
            }
            if (isset($options['allowedFileTypes']) && $form->has('allowedFileTypes')) {
                $form->get('allowedFileTypes')->setData($options['allowedFileTypes']);
            }
        }

        // Handle Date field options
        if ($isDateType) {
            if (isset($options['dateFormat']) && $form->has('dateFormat')) {
                $form->get('dateFormat')->setData(trim((string) preg_replace('/\s*\(.*\)$/', '', $options['dateFormat'])));
            }
            if (isset($options['showTime']) && $form->has('showTime')) {
                $form->get('showTime')->setData((bool)$options['showTime']);
            }
        }

        // Handle Email field options
        if ($isEmailType) {
            if (isset($options['allowedEmailDomains']) && $form->has('allowedEmailDomains')) {
                $form->get('allowedEmailDomains')->setData($options['allowedEmailDomains']);
            }
        }

        // Handle prepend/append (overrides preapp/apppreinput from prependappend array)
        if (isset($options['prependappend']) && is_array($options['prependappend'])) {
            if ($form->has('prependappend')) {
                $form->get('prependappend')->setData((bool)($options['prependappend']['on'] ?? false));
            }
            if ($form->has('preapp')) {
                $form->get('preapp')->setData($options['prependappend']['preapp'] ?? 'pre');
            }
            if ($form->has('apppreinput')) {
                $form->get('apppreinput')->setData($options['prependappend']['value'] ?? '');
            }
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->checkListService->updateCheckListField($field);
            $this->addFlash('success', 'Veld opgeslagen.');

            return $this->redirectToRoute('admin_checklist_edit', ['id' => $checkList->getId(), '_fragment' => 'fields']);
        }

        $fieldTypes = $this->checkListService->getAllFieldTypes();
        $answers = $this->checkListService->getAllAnswers();

        return $this->render('admin/checklist/field_form.html.twig', [
            'form' => $form,
            'checkList' => $checkList,
            'field' => $field,
            'fieldTypes' => $fieldTypes,
            'answers' => $answers,
            'action' => 'edit',
        ]);
    }

    #[Route('/field/{id<\d+>}/delete', name: 'admin_checklist_field_delete', methods: ['POST'])]
    public function deleteField(Request $request, int $id): Response
    {
        if (!$this->isCsrfTokenValid('delete_field' . $id, $request->headers->get('X-CSRF-Token') ?? $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Ongeldig beveiligingstoken']);
        }

        $field = $this->checkListService->getCheckListFieldById($id);

        if (!$field) {
            return new JsonResponse(['success' => false, 'message' => 'Veld niet gevonden']);
        }
        $this->denyAccessUnlessGranted(CheckListVoter::MANAGE, $field->getCheckList());

        $checkList = $field->getCheckList();
        $checkListId = $checkList->getId();

        $this->checkListService->removeCheckListField($field);

        if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
            return new JsonResponse(['success' => true]);
        }

        return $this->redirectToRoute('admin_checklist_edit', ['id' => $checkListId]);
    }

    #[Route('/{id<\d+>}/edit/reorder', name: 'admin_checklist_edit_reorder', methods: ['POST'])]
    public function reorderFields(Request $request, int $id): JsonResponse
    {
        $checkList = $this->checkListService->getCheckListById($id);

        if (!$checkList) {
            throw $this->createNotFoundException('Checklist niet gevonden');
        }
        $this->denyAccessUnlessGranted(CheckListVoter::MANAGE, $checkList);

        if (!$this->isCsrfTokenValid('reorder_fields' . $id, $request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Ongeldig beveiligingstoken. Herlaad de pagina en probeer het opnieuw.'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['order']) || !is_array($data['order'])) {
            return new JsonResponse(['success' => false, 'message' => 'Ongeldige gegevens.'], 400);
        }

        // Validate everything first, so an invalid tree changes nothing
        $changes = [];
        foreach ($data['order'] as $entry) {
            $field = $this->checkListService->getCheckListFieldById((int) ($entry['id'] ?? 0));
            if (!$field || $field->getCheckList()->getId() !== $id) {
                return new JsonResponse(['success' => false, 'message' => 'Onbekend veld in de volgorde.'], 400);
            }

            $parent = null;
            $parentId = (int) ($entry['parent_id'] ?? 0);
            if ($parentId > 0) {
                $parent = $this->checkListService->getCheckListFieldById($parentId);
                if (!$parent || $parent->getCheckList()->getId() !== $id) {
                    return new JsonResponse(['success' => false, 'message' => 'Onbekende veldgroep in de volgorde.'], 400);
                }
                if ($parent->getFormType() !== 'fieldset') {
                    return new JsonResponse([
                        'success' => false,
                        'message' => sprintf('"%s" kan niet in "%s" worden geplaatst: velden kunnen alleen in een veldgroep staan.', $field->getName(), $parent->getName()),
                    ], 422);
                }
            }

            $changes[] = [$field, $parent, (int) ($entry['order'] ?? 0)];
        }

        foreach ($changes as [$field, $parent, $order]) {
            $field->setOrder($order);
            $field->setParent($parent);
        }

        $this->checkListService->getEntityManager()->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/{id<\d+>}/items/new', name: 'admin_checklist_item_new', methods: ['GET', 'POST'])]
    public function newItem(Request $request, int $id): Response
    {
        $checkList = $this->checkListService->getCheckListById($id);

        if (!$checkList) {
            throw $this->createNotFoundException('Checklist niet gevonden');
        }
        $this->denyAccessUnlessGranted(CheckListVoter::MANAGE, $checkList);

        $item = $this->checkListService->createCheckListItem();
        $fields = $this->checkListService->getCheckListFieldsByCheckListWithChildren($checkList);
        $form = $this->createForm(CheckListItemType::class, $item, ['check_list' => $checkList, 'fields' => $fields]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->checkListService->storeItemUploads($item, $checkList, $form, $fields);
            $this->checkListService->saveCheckListItem($item, $checkList, $this->getAppUser());
            $this->addFlash('success', 'Item aangemaakt.');

            return $this->redirectToRoute('admin_checklist_edit', ['id' => $checkList->getId(), '_fragment' => 'items']);
        }

        $fields = $this->checkListService->getCheckListFieldsByCheckListWithChildren($checkList);

        return $this->render('admin/checklist/item_form.html.twig', [
            'form' => $form,
            'checkList' => $checkList,
            'fields' => $fields,
            'action' => 'new',
        ]);
    }

    #[Route('/item/{id<\d+>}/edit', name: 'admin_checklist_item_edit', methods: ['GET', 'POST'])]
    public function editItem(Request $request, int $id): Response
    {
        $item = $this->checkListService->getCheckListItemByIdWithAnswers($id);

        if (!$item) {
            throw $this->createNotFoundException('Item niet gevonden');
        }
        $this->denyAccessUnlessGranted(CheckListVoter::MANAGE, $item->getCheckList());

        $checkList = $item->getCheckList();
        $fields = $this->checkListService->getCheckListFieldsByCheckListWithChildren($checkList);

        $form = $this->createForm(CheckListItemType::class, $item, ['check_list' => $checkList, 'fields' => $fields]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->checkListService->storeItemUploads($item, $checkList, $form, $fields);
            $this->checkListService->updateCheckListItem($item, $this->getAppUser());
            $this->addFlash('success', 'Item opgeslagen.');

            return $this->redirectToRoute('admin_checklist_edit', ['id' => $checkList->getId(), '_fragment' => 'items']);
        }

        return $this->render('admin/checklist/item_form.html.twig', [
            'form' => $form,
            'checkList' => $checkList,
            'item' => $item,
            'fields' => $fields,
            'action' => 'edit',
        ]);
    }

    #[Route('/item/{id<\d+>}/delete', name: 'admin_checklist_item_delete', methods: ['POST'])]
    public function deleteItem(Request $request, int $id): Response
    {
        if (!$this->isCsrfTokenValid('delete_item' . $id, $request->headers->get('X-CSRF-Token') ?? $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Ongeldig beveiligingstoken']);
        }

        $item = $this->checkListService->getCheckListItemById($id);

        if (!$item) {
            return new JsonResponse(['success' => false, 'message' => 'Item niet gevonden']);
        }
        $this->denyAccessUnlessGranted(CheckListVoter::MANAGE, $item->getCheckList());

        $checkList = $item->getCheckList();
        $checkListId = $checkList->getId();

        $this->checkListService->removeCheckListItem($item);

        if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
            return new JsonResponse(['success' => true]);
        }

        return $this->redirectToRoute('admin_checklist_edit', ['id' => $checkListId, '_fragment' => 'items']);
    }

    // Field types management
    #[Route('/field-types', name: 'admin_checklist_field_types', methods: ['GET'])]
    public function fieldTypes(): Response
    {
        $this->denyAccessUnlessGranted(CheckListVoter::PERM_MANAGE_ALL);

        $fieldTypes = $this->checkListService->getAllFieldTypes();

        return $this->render('admin/checklist/field_types.html.twig', [
            'fieldTypes' => $fieldTypes,
        ]);
    }

    #[Route('/field-types/new', name: 'admin_checklist_field_type_new', methods: ['GET', 'POST'])]
    public function newFieldType(Request $request): Response
    {
        $this->denyAccessUnlessGranted(CheckListVoter::PERM_MANAGE_ALL);

        $fieldType = $this->checkListService->createFieldType();
        $form = $this->createForm(CheckListFieldTypeType::class, $fieldType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->checkListService->saveFieldType($fieldType);
            $this->addFlash('success', 'Veldtype aangemaakt.');

            return $this->redirectToRoute('admin_checklist_field_types');
        }

        return $this->render('admin/checklist/field_type_form.html.twig', [
            'form' => $form,
            'action' => 'new',
        ]);
    }

    #[Route('/field-types/{id<\d+>}/edit', name: 'admin_checklist_field_type_edit', methods: ['GET', 'POST'])]
    public function editFieldType(Request $request, int $id): Response
    {
        $this->denyAccessUnlessGranted(CheckListVoter::PERM_MANAGE_ALL);

        $fieldType = $this->checkListService->findFieldTypeById($id);

        if (!$fieldType) {
            throw $this->createNotFoundException('Veldtype niet gevonden');
        }

        $form = $this->createForm(CheckListFieldTypeType::class, $fieldType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->checkListService->saveFieldType($fieldType);
            $this->addFlash('success', 'Veldtype opgeslagen.');

            return $this->redirectToRoute('admin_checklist_field_types');
        }

        return $this->render('admin/checklist/field_type_form.html.twig', [
            'form' => $form,
            'fieldType' => $fieldType,
            'action' => 'edit',
        ]);
    }

    // Answers management
    #[Route('/answers', name: 'admin_checklist_answers', methods: ['GET'])]
    public function answers(): Response
    {
        $answers = $this->checkListService->getAllAnswers();

        return $this->render('admin/checklist/answers.html.twig', [
            'answers' => $answers,
        ]);
    }

    #[Route('/answers/new', name: 'admin_checklist_answer_new', methods: ['GET', 'POST'])]
    public function newAnswer(Request $request): Response
    {
        $answer = $this->checkListService->createAnswer();
        $form = $this->createForm(AnswerType::class, $answer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->checkListService->saveAnswer($answer);
            $this->addFlash('success', 'Antwoord aangemaakt.');

            return $this->redirectToRoute('admin_checklist_answers');
        }

        return $this->render('admin/checklist/answer_form.html.twig', [
            'form' => $form,
            'action' => 'new',
        ]);
    }

    #[Route('/answers/{id<\d+>}/edit', name: 'admin_checklist_answer_edit', methods: ['GET', 'POST'])]
    public function editAnswer(Request $request, int $id): Response
    {
        $this->denyAccessUnlessGranted(CheckListVoter::PERM_MANAGE_ALL);

        $answer = $this->checkListService->getAnswerById($id);

        if (!$answer) {
            throw $this->createNotFoundException('Antwoord niet gevonden');
        }

        $form = $this->createForm(AnswerType::class, $answer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->checkListService->saveAnswer($answer);
            $this->addFlash('success', 'Antwoord opgeslagen.');

            return $this->redirectToRoute('admin_checklist_answers');
        }

        return $this->render('admin/checklist/answer_form.html.twig', [
            'form' => $form,
            'answer' => $answer,
            'action' => 'edit',
        ]);
    }

    #[Route('/answers/{id<\d+>}/delete', name: 'admin_checklist_answer_delete', methods: ['POST'])]
    public function deleteAnswer(Request $request, int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(CheckListVoter::PERM_MANAGE_ALL);

        if (!$this->isCsrfTokenValid('delete_answer' . $id, $request->headers->get('X-CSRF-Token') ?? $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Ongeldig beveiligingstoken']);
        }

        $answer = $this->checkListService->getAnswerById($id);

        if (!$answer) {
            return new JsonResponse(['success' => false, 'message' => 'Antwoord niet gevonden']);
        }

        $this->em->remove($answer);
        $this->em->flush();

        return new JsonResponse(['success' => true]);
    }

    /** Owners only see their own checklists; managers see all */
    private function ownerFilter(): ?User
    {
        return $this->isGranted(CheckListVoter::PERM_MANAGE_ALL) ? null : $this->getAppUser();
    }

    private function getAppUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }
}
