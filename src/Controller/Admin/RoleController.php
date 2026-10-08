<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Permission;
use App\Entity\Role;
use App\Form\RoleType;
use App\Repository\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/role')]
#[IsGranted('ROLE_ADMIN')]
class RoleController extends AbstractController
{
    public function __construct(
        private RoleRepository $roleRepository,
        private EntityManagerInterface $em,
        private PaginatorInterface $paginator
    ) {}

    #[Route('/', name: 'admin_role_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = $request->query->get('search', '');

        $qb = $this->roleRepository->createQueryBuilder('r')
            ->leftJoin('r.parentRoles', 'pr')
            ->addSelect('pr')
            ->leftJoin('r.permissions', 'p')
            ->addSelect('p')
            ->andWhere('r.deleted = false')
            ->orderBy('r.name', 'ASC');

        if ($search) {
            $qb->andWhere('r.name LIKE :search OR r.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $pagination = $this->paginator->paginate(
            $qb->getQuery(),
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('admin/role/index.html.twig', [
            'pagination' => $pagination,
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'admin_role_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $role = new Role();

        $form = $this->createForm(RoleType::class, $role);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($role);
            $this->em->flush();

            $this->addFlash('success', 'Role created successfully.');

            return $this->redirectToRoute('admin_role_edit', ['id' => $role->getId()]);
        }

        return $this->render('admin/role/new.html.twig', [
            'form' => $form,
            'role' => $role,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_role_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Role $role): Response
    {
        $form = $this->createForm(RoleType::class, $role);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            $this->addFlash('success', 'Role updated successfully.');

            return $this->redirectToRoute('admin_role_edit', ['id' => $role->getId()]);
        }

        return $this->render('admin/role/edit.html.twig', [
            'form' => $form,
            'role' => $role,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_role_delete', methods: ['POST'])]
    public function delete(Request $request, Role $role): Response
    {
        if ($this->isCsrfTokenValid('delete' . $role->getId(), $request->request->get('_token'))) {
            $this->em->remove($role);
            $this->em->flush();
            $this->addFlash('success', 'Role deleted successfully.');
        }

        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true]);
        }

        return $this->redirectToRoute('admin_role_index');
    }
}