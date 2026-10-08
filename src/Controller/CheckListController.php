<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CheckList;
use App\Entity\CheckListItem;
use App\Entity\User;
use App\Form\CheckListItemType;
use App\Repository\CheckListItemRepository;
use App\Repository\CheckListRepository;
use App\Service\CheckListConditionEvaluator;
use App\Service\CheckListService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Filling in checklists outside the admin, depending on the checklist's access setting:
 * "public" for everyone, "user" for logged-in users. Logged-in users can view and edit their own submissions.
 */
#[Route('/checklists')]
class CheckListController extends AbstractController
{
    public function __construct(
        private CheckListService $checkListService,
        private CheckListRepository $checkListRepository,
        private CheckListItemRepository $checkListItemRepository,
    ) {
    }

    #[Route('', name: 'app_checklists', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('checklist/index.html.twig', [
            'checkLists' => $this->checkListRepository->findOpenForSubmissions($this->getAppUser() !== null),
        ]);
    }

    #[Route('/{id<\d+>}/fill', name: 'app_checklist_fill', methods: ['GET', 'POST'])]
    public function fill(Request $request, int $id, RateLimiterFactoryInterface $checklistSubmissionLimiter): Response
    {
        $checkList = $this->getOpenCheckList($id);
        $user = $this->getAppUser();

        $item = $this->checkListService->createCheckListItem();
        $fields = $this->checkListService->getCheckListFieldsByCheckListWithChildren($checkList);
        $form = $this->createForm(CheckListItemType::class, $item, ['check_list' => $checkList, 'fields' => $fields, 'honeypot' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // Spam protection: a limited number of submissions per visitor (logged-in user or IP address) per checklist
            $visitor = $user ? 'user-' . $user->getId() : 'ip-' . $request->getClientIp();
            $limit = $checklistSubmissionLimiter->create($visitor . '-checklist-' . $checkList->getId())->consume();
            if (!$limit->isAccepted()) {
                return $this->render('checklist/form.html.twig', [
                    'form' => $form,
                    'checkList' => $checkList,
                    'fields' => $fields,
                    'action' => 'new',
                    'rateLimited' => true,
                ], new Response(null, Response::HTTP_TOO_MANY_REQUESTS));
            }

            // A filled in honeypot means a bot: act as if it worked, but save nothing
            if ($form->get(CheckListItemType::HONEYPOT_FIELD)->getData()) {
                return $user === null
                    ? $this->redirectToRoute('app_checklist_thanks', ['id' => $checkList->getId()])
                    : $this->redirectToRoute('app_checklist_mine', ['id' => $checkList->getId()]);
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $this->checkListService->storeItemUploads($item, $checkList, $form, $fields);
            $this->checkListService->saveCheckListItem($item, $checkList, $user);

            if ($user === null) {
                return $this->redirectToRoute('app_checklist_thanks', ['id' => $checkList->getId()]);
            }

            $this->addFlash('success', 'Thank you, your answers have been saved.');

            return $this->redirectToRoute('app_checklist_mine', ['id' => $checkList->getId()]);
        }

        return $this->render('checklist/form.html.twig', [
            'form' => $form,
            'checkList' => $checkList,
            'fields' => $fields,
            'action' => 'new',
        ]);
    }

    #[Route('/{id<\d+>}/thanks', name: 'app_checklist_thanks', methods: ['GET'])]
    public function thanks(int $id): Response
    {
        return $this->render('checklist/thanks.html.twig', [
            'checkList' => $this->getOpenCheckList($id),
        ]);
    }

    #[Route('/{id<\d+>}/mine', name: 'app_checklist_mine', methods: ['GET'])]
    public function mine(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $checkList = $this->getOpenCheckList($id);

        return $this->render('checklist/mine.html.twig', [
            'checkList' => $checkList,
            'items' => $this->checkListItemRepository->findByCheckListAndCreator($checkList, $this->requireUser()),
        ]);
    }

    #[Route('/item/{id<\d+>}', name: 'app_checklist_item', methods: ['GET'])]
    public function showItem(int $id, CheckListConditionEvaluator $conditionEvaluator): Response
    {
        $item = $this->getOwnItem($id);
        $checkList = $item->getCheckList();
        $fields = $this->checkListService->getCheckListFieldsByCheckListWithChildren($checkList);
        $valueFields = array_values(array_filter(
            $fields,
            fn ($field) => !in_array($field->getFormType(), ['fieldset', 'hidden'], true)
        ));

        return $this->render('checklist/item.html.twig', [
            'checkList' => $checkList,
            'item' => $item,
            'fields' => $fields,
            'itemAnswers' => $this->checkListService->getItemOverviewValues([$item], $valueFields)[$item->getId()] ?? [],
            'hiddenFieldIds' => $conditionEvaluator->getHiddenFieldIdsForContent($fields, $item->getItemContent()),
        ]);
    }

    #[Route('/item/{id<\d+>}/edit', name: 'app_checklist_item_edit', methods: ['GET', 'POST'])]
    public function editItem(Request $request, int $id): Response
    {
        $item = $this->getOwnItem($id);
        $checkList = $item->getCheckList();
        $fields = $this->checkListService->getCheckListFieldsByCheckListWithChildren($checkList);

        $form = $this->createForm(CheckListItemType::class, $item, ['check_list' => $checkList, 'fields' => $fields]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->checkListService->storeItemUploads($item, $checkList, $form, $fields);
            $this->checkListService->updateCheckListItem($item, $this->getAppUser());
            $this->addFlash('success', 'Your answers have been updated.');

            return $this->redirectToRoute('app_checklist_item', ['id' => $item->getId()]);
        }

        return $this->render('checklist/form.html.twig', [
            'form' => $form,
            'checkList' => $checkList,
            'fields' => $fields,
            'item' => $item,
            'action' => 'edit',
        ]);
    }

    /**
     * An uploaded file of an item. Uploads are stored outside public/, so this is the only way to open them:
     * admins can open every file, users only the files of their own items.
     */
    #[Route('/item/{id<\d+>}/file/{field}', name: 'app_checklist_item_file', requirements: ['field' => '[A-Za-z0-9_-]+'], methods: ['GET'])]
    public function itemFile(int $id, string $field): Response
    {
        $item = $this->isGranted('ROLE_ADMIN')
            ? ($this->checkListService->getCheckListItemById($id) ?? throw $this->createNotFoundException('Item not found'))
            : $this->getOwnItem($id);

        $path = $this->checkListService->getUploadAbsolutePath($item->getItemContent()['field_' . $field] ?? null);
        if ($path === null || !is_file($path)) {
            throw $this->createNotFoundException('File not found');
        }

        $response = new BinaryFileResponse($path);
        // Only images open in the browser; anything else (html, svg, pdf, ...) is downloaded, so it can't run on this site
        $inline = in_array($response->getFile()->getMimeType(), ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true);
        $response->setContentDisposition($inline ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT, basename($path));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setPrivate();

        return $response;
    }

    /**
     * A checklist that may be filled in by the current visitor. Admin-only and archived checklists don't exist here;
     * a "logged-in users" checklist sends an anonymous visitor to the login page first.
     */
    private function getOpenCheckList(int $id): CheckList
    {
        $checkList = $this->checkListService->getCheckListById($id);

        if (!$checkList || !$checkList->isOpenForSubmissions()) {
            throw $this->createNotFoundException('Checklist not found');
        }

        if ($checkList->getAccess() === CheckList::ACCESS_USER) {
            $this->denyAccessUnlessGranted('ROLE_USER');
        }

        return $checkList;
    }

    /** An item of the logged-in user in a checklist that is still open; anything else is "not found". */
    private function getOwnItem(int $id): CheckListItem
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $item = $this->checkListService->getCheckListItemById($id);

        if (!$item || $item->getCreatedBy() === null || $item->getCreatedBy()->getId() !== $this->requireUser()->getId()) {
            throw $this->createNotFoundException('Item not found');
        }
        $this->getOpenCheckList((int) $item->getCheckList()->getId());

        return $item;
    }

    private function getAppUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function requireUser(): User
    {
        $user = $this->getAppUser();
        if ($user === null) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
