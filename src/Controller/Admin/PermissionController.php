<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Permission;
use App\Form\PermissionType;
use App\Repository\PermissionRepository;
use App\Security\AdminRouteAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/permission')]
#[IsGranted('ROLE_ADMIN')]
class PermissionController extends AbstractController
{
    public function __construct(
        private PermissionRepository $permissionRepository,
        private EntityManagerInterface $em,
    ) {}

    #[Route('/', name: 'admin_permission_index', methods: ['GET'])]
    public function index(): Response
    {
        $groups = [];
        foreach ($this->permissionRepository->findAllActive() as $permission) {
            $groups[$permission->getGroup()][] = $permission;
        }

        return $this->render('admin/permission/index.html.twig', [
            'groups' => $groups,
            'adminAccessPermission' => AdminRouteAccess::ADMIN_ACCESS_PERMISSION,
        ]);
    }

    #[Route('/new', name: 'admin_permission_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $permission = new Permission();
        $form = $this->createForm(PermissionType::class, $permission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($permission);
            $this->em->flush();
            $this->addFlash('success', sprintf('Recht "%s" aangemaakt. Vink het aan bij een rol om het aan gebruikers te geven.', $permission->getName()));

            return $this->redirectToRoute('admin_permission_index');
        }

        return $this->render('admin/permission/form.html.twig', [
            'form' => $form,
            'permission' => $permission,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_permission_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Permission $permission): Response
    {
        $form = $this->createForm(PermissionType::class, $permission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Recht opgeslagen.');

            return $this->redirectToRoute('admin_permission_index');
        }

        return $this->render('admin/permission/form.html.twig', [
            'form' => $form,
            'permission' => $permission,
        ]);
    }

    /** Also removes it from its roles and deletes the route rules that use it (the database cascades both) */
    #[Route('/{id}/delete', name: 'admin_permission_delete', methods: ['POST'])]
    public function delete(Request $request, Permission $permission): Response
    {
        if ($this->isCsrfTokenValid('delete' . $permission->getId(), $request->request->get('_token'))) {
            if ($permission->getName() === AdminRouteAccess::ADMIN_ACCESS_PERMISSION) {
                $this->addFlash('danger', sprintf('"%s" is nodig om in de admin te komen en kan niet worden verwijderd.', $permission->getName()));
            } else {
                $this->em->remove($permission);
                $this->em->flush();
                $this->addFlash('success', 'Recht verwijderd.');
            }
        }

        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true]);
        }

        return $this->redirectToRoute('admin_permission_index');
    }
}
