<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Menu;
use App\Entity\MenuItem;
use App\Form\MenuType;
use App\Form\MenuItemType;
use App\Repository\MenuRepository;
use App\Repository\MenuItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/menu')]
class MenuController extends AbstractController
{
    public function __construct(
        private MenuRepository $menuRepository,
        private MenuItemRepository $menuItemRepository,
        private EntityManagerInterface $em
    ) {}

    #[Route('/', name: 'admin_menu_index', methods: ['GET'])]
    public function index(): Response
    {
        $menus = $this->menuRepository->findAllActive();

        return $this->render('admin/menu/index.html.twig', [
            'menus' => $menus,
        ]);
    }

    #[Route('/new', name: 'admin_menu_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $menu = new Menu();
        $form = $this->createForm(MenuType::class, $menu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($menu);
            $this->em->flush();

            $this->addFlash('success', 'Menu aangemaakt.');

            return $this->redirectToRoute('admin_menu_index');
        }

        return $this->render('admin/menu/new.html.twig', [
            'menu' => $menu,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_menu_show', methods: ['GET'])]
    public function show(Menu $menu): Response
    {
        $rootItems = $this->menuItemRepository->findRootItemsByMenu($menu);

        return $this->render('admin/menu/show.html.twig', [
            'menu' => $menu,
            'rootItems' => $rootItems,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_menu_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Menu $menu): Response
    {
        $form = $this->createForm(MenuType::class, $menu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            $this->addFlash('success', 'Menu opgeslagen.');

            return $this->redirectToRoute('admin_menu_index');
        }

        return $this->render('admin/menu/edit.html.twig', [
            'menu' => $menu,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_menu_delete', methods: ['POST'])]
    public function delete(Request $request, Menu $menu): Response
    {
        if ($this->isCsrfTokenValid('delete' . $menu->getId(), $request->request->get('_token'))) {
            $this->em->remove($menu);
            $this->em->flush();
            $this->addFlash('success', 'Menu verwijderd.');
        }

        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true]);
        }

        return $this->redirectToRoute('admin_menu_index');
    }

    #[Route('/{id}/item/new', name: 'admin_menu_item_new', methods: ['GET', 'POST'])]
    public function newItem(Request $request, Menu $menu): Response
    {
        $item = new MenuItem();
        $item->setMenu($menu);
        $item->setSortOrder($this->menuItemRepository->getMaxSortOrder($menu));

        $form = $this->createForm(MenuItemType::class, $item, [
            'menu' => $menu,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($item);
            $this->em->flush();

            $this->addFlash('success', 'Menu-item aangemaakt.');

            return $this->redirectToRoute('admin_menu_show', ['id' => $menu->getId()]);
        }

        return $this->render('admin/menu/item/new.html.twig', [
            'menu' => $menu,
            'item' => $item,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/item/{item}/edit', name: 'admin_menu_item_edit', methods: ['GET', 'POST'])]
    public function editItem(Request $request, Menu $menu, MenuItem $item): Response
    {
        $form = $this->createForm(MenuItemType::class, $item, [
            'menu' => $menu,
            'item_id' => $item->getId(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            $this->addFlash('success', 'Menu-item opgeslagen.');

            return $this->redirectToRoute('admin_menu_show', ['id' => $menu->getId()]);
        }

        return $this->render('admin/menu/item/edit.html.twig', [
            'menu' => $menu,
            'item' => $item,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/item/{item}', name: 'admin_menu_item_delete', methods: ['POST'])]
    public function deleteItem(Request $request, Menu $menu, MenuItem $item): Response
    {
        if ($this->isCsrfTokenValid('delete' . $item->getId(), $request->request->get('_token'))) {
            $this->em->remove($item);
            $this->em->flush();
            $this->addFlash('success', 'Menu-item verwijderd.');
        }

        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true]);
        }

        return $this->redirectToRoute('admin_menu_show', ['id' => $menu->getId()]);
    }

    /**
     * Saves the order after a drag: for each list that changed, its parent item (null = top level) and its items
     * in their new order. All of them are numbered 1, 2, 3, ..., so no two items share a position.
     * JSON body: {"_token": "...", "lists": [{"parentId": 12|null, "items": [5, 3, 9]}]}
     */
    #[Route('/{id}/reorder', name: 'admin_menu_reorder', methods: ['POST'])]
    public function reorder(Request $request, Menu $menu): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !$this->isCsrfTokenValid('menu_reorder' . $menu->getId(), (string) ($data['_token'] ?? ''))) {
            return new JsonResponse(['success' => false, 'message' => 'Je sessie is verlopen, herlaad de pagina.'], 403);
        }

        $items = [];
        foreach ($menu->getMenuItems() as $item) {
            $items[$item->getId()] = $item;
        }

        foreach ((array) ($data['lists'] ?? []) as $list) {
            $parentId = $list['parentId'] ?? null;
            $parent = $parentId !== null ? ($items[(int) $parentId] ?? null) : null;
            if ($parentId !== null && $parent === null) {
                return new JsonResponse(['success' => false, 'message' => 'Onbekend bovenliggend item.'], 400);
            }
            foreach (array_values((array) ($list['items'] ?? [])) as $position => $id) {
                $item = $items[(int) $id] ?? null;
                if ($item === null) {
                    return new JsonResponse(['success' => false, 'message' => 'Onbekend menu-item.'], 400);
                }
                // An item can't go under itself or one of its own sub-items
                for ($ancestor = $parent; $ancestor !== null; $ancestor = $ancestor->getParent()) {
                    if ($ancestor === $item) {
                        return new JsonResponse(['success' => false, 'message' => 'Een item kan niet in zijn eigen submenu worden geplaatst.'], 400);
                    }
                }
                $item->setParent($parent);
                $item->setSortOrder($position + 1);
            }
        }

        $this->em->flush();

        return new JsonResponse(['success' => true]);
    }
}