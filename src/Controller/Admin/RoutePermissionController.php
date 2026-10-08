<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\RoutePermission;
use App\Form\RoutePermissionType;
use App\Repository\RoutePermissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/route-permission')]
#[IsGranted('ROLE_ADMIN')]
class RoutePermissionController extends AbstractController
{
    public function __construct(
        private RoutePermissionRepository $routePermissionRepository,
        private EntityManagerInterface $em,
        private PaginatorInterface $paginator
    ) {}

    #[Route('/', name: 'admin_route_permission_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = $request->query->get('search', '');

        $qb = $this->routePermissionRepository->createQueryBuilder('rp')
            ->andWhere('rp.deleted = false')
            ->orderBy('rp.priority', 'DESC')
            ->addOrderBy('rp.routePattern', 'ASC');

        if ($search) {
            $qb->andWhere('rp.routePattern LIKE :search OR rp.permissionName LIKE :search OR rp.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $pagination = $this->paginator->paginate(
            $qb->getQuery(),
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('admin/route_permission/index.html.twig', [
            'pagination' => $pagination,
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'admin_route_permission_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $routePermission = new RoutePermission();

        $form = $this->createForm(RoutePermissionType::class, $routePermission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($routePermission);
            $this->em->flush();

            $this->addFlash('success', 'Route permission created successfully.');

            return $this->redirectToRoute('admin_route_permission_edit', ['id' => $routePermission->getId()]);
        }

        return $this->render('admin/route_permission/new.html.twig', [
            'form' => $form,
            'routePermission' => $routePermission,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_route_permission_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, RoutePermission $routePermission): Response
    {
        $form = $this->createForm(RoutePermissionType::class, $routePermission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            $this->addFlash('success', 'Route permission updated successfully.');

            return $this->redirectToRoute('admin_route_permission_edit', ['id' => $routePermission->getId()]);
        }

        return $this->render('admin/route_permission/edit.html.twig', [
            'form' => $form,
            'routePermission' => $routePermission,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_route_permission_delete', methods: ['POST'])]
    public function delete(Request $request, RoutePermission $routePermission): Response
    {
        if ($this->isCsrfTokenValid('delete' . $routePermission->getId(), $request->request->get('_token'))) {
            $this->em->remove($routePermission);
            $this->em->flush();
            $this->addFlash('success', 'Route permission deleted successfully.');
        }

        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true]);
        }

        return $this->redirectToRoute('admin_route_permission_index');
    }

    #[Route('/{id}/toggle', name: 'admin_route_permission_toggle', methods: ['POST'])]
    public function toggle(Request $request, RoutePermission $routePermission): JsonResponse
    {
        $routePermission->setEnabled(!$routePermission->isEnabled());
        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'enabled' => $routePermission->isEnabled(),
        ]);
    }
}