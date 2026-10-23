<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Role;
use App\Entity\RoutePermission;
use App\Form\RoutePermissionType;
use App\Repository\RoleRepository;
use App\Repository\RoutePermissionRepository;
use App\Security\AdminRouteAccess;
use Doctrine\ORM\EntityManagerInterface;
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
    /** @var list<Role>|null */
    private ?array $roles = null;

    public function __construct(
        private RoutePermissionRepository $routePermissionRepository,
        private RoleRepository $roleRepository,
        private AdminRouteAccess $adminRouteAccess,
        private EntityManagerInterface $em,
    ) {}

    /** The rules, and per admin section who can open it */
    #[Route('/', name: 'admin_route_permission_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query->get('search', ''));

        return $this->render('admin/route_permission/index.html.twig', [
            'rules' => $this->routePermissionRepository->findAllForOverview($search),
            'search' => $search,
            'sections' => $this->buildSectionOverview(),
            'adminAccessPermission' => AdminRouteAccess::ADMIN_ACCESS_PERMISSION,
            'rolesWithAdminAccess' => $this->rolesWithPermission(AdminRouteAccess::ADMIN_ACCESS_PERMISSION),
        ]);
    }

    #[Route('/new', name: 'admin_route_permission_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        // "Add rule" on the overview passes the section's pattern
        $routePermission = (new RoutePermission())->setRoutePattern($request->query->get('route', ''));

        $form = $this->createForm(RoutePermissionType::class, $routePermission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($routePermission);
            $this->em->flush();
            $this->addFlash('success', sprintf('Regel opgeslagen: %s.', $routePermission));

            return $this->redirectToRoute('admin_route_permission_index');
        }

        return $this->render('admin/route_permission/form.html.twig', [
            'form' => $form,
            'routePermission' => $routePermission,
            'routes' => $this->adminRouteAccess->getAdminRoutes(),
            'sections' => $this->adminRouteAccess->getAdminRouteSections(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_route_permission_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, RoutePermission $routePermission): Response
    {
        $form = $this->createForm(RoutePermissionType::class, $routePermission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', sprintf('Regel opgeslagen: %s.', $routePermission));

            return $this->redirectToRoute('admin_route_permission_index');
        }

        return $this->render('admin/route_permission/form.html.twig', [
            'form' => $form,
            'routePermission' => $routePermission,
            'routes' => $this->adminRouteAccess->getAdminRoutes(),
            'sections' => $this->adminRouteAccess->getAdminRouteSections(),
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_route_permission_delete', methods: ['POST'])]
    public function delete(Request $request, RoutePermission $routePermission): Response
    {
        if ($this->isCsrfTokenValid('delete' . $routePermission->getId(), $request->request->get('_token'))) {
            $this->em->remove($routePermission);
            $this->em->flush();
            $this->addFlash('success', 'Routerecht verwijderd.');
        }

        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true]);
        }

        return $this->redirectToRoute('admin_route_permission_index');
    }

    #[Route('/{id}/toggle', name: 'admin_route_permission_toggle', methods: ['POST'])]
    public function toggle(Request $request, RoutePermission $routePermission): JsonResponse
    {
        if (!$this->isCsrfTokenValid('toggle_rp' . $routePermission->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'error' => 'Ongeldig beveiligingstoken'], Response::HTTP_FORBIDDEN);
        }

        $routePermission->setEnabled(!$routePermission->isEnabled());
        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'enabled' => $routePermission->isEnabled(),
        ]);
    }

    /**
     * Per admin section: each route with the permission that opens it (null = admins only)
     *
     * @return list<array{name: string, pattern: string, adminOnly: bool, routes: list<array{name: string, permission: ?string}>, permissions: array<string, list<string>>, hasAdminOnlyRoutes: bool}>
     */
    private function buildSectionOverview(): array
    {
        $overview = [];
        foreach ($this->adminRouteAccess->getAdminRouteSections() as $name => $section) {
            $routes = [];
            $permissions = [];
            $hasAdminOnlyRoutes = false;

            foreach ($section['routes'] as $route) {
                $permission = $route === 'admin_dashboard'
                    ? AdminRouteAccess::ADMIN_ACCESS_PERMISSION
                    : $this->adminRouteAccess->requiredPermission($route);
                $routes[] = ['name' => $route, 'permission' => $permission];

                if ($permission === null) {
                    $hasAdminOnlyRoutes = true;
                } else {
                    $permissions[$permission] ??= $this->rolesWithPermission($permission);
                }
            }

            $overview[] = [
                'name' => $name,
                'pattern' => $section['pattern'],
                'adminOnly' => AdminRouteAccess::isAdminOnlyRoute($section['routes'][0]),
                'routes' => $routes,
                'permissions' => $permissions,
                'hasAdminOnlyRoutes' => $hasAdminOnlyRoutes,
            ];
        }

        return $overview;
    }

    /** @return list<string> names of the (non-admin) roles that have the permission, also through inheritance */
    private function rolesWithPermission(string $permission): array
    {
        $roles = $this->roles ??= $this->roleRepository->findAllActive();

        return array_values(array_map(
            fn(Role $role) => $role->getName(),
            array_filter($roles, fn(Role $role) => $role->getName() !== 'ROLE_ADMIN' && $role->hasPermission($permission)),
        ));
    }
}
