<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\RoutePermission;
use App\Entity\User;
use App\Repository\RoutePermissionRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class RoutePermissionListener
{
    public function __construct(
        private RoutePermissionRepository $routePermissionRepository,
        private AuthorizationCheckerInterface $authorizationChecker,
        private RouterInterface $router,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');
        $method = $request->getMethod();

        // Skip non-admin routes and static assets
        if (!$this->shouldCheck($request, $route)) {
            return;
        }

        $routePermission = $this->routePermissionRepository->findByRoute($route, $method);

        if (!$routePermission) {
            return; // No permission required for this route
        }

        $permissionName = $routePermission->getPermissionName();
        $attribute = 'PERM_' . $permissionName;

        if (!$this->authorizationChecker->isGranted($attribute)) {
            // The authorization checker has no token; the logged-in user comes from the token storage
            $user = $this->tokenStorage->getToken()?->getUser();
            
            if (!$user instanceof User) {
                // Redirect to login
                $loginUrl = $this->router->generate('app_login');
                $event->setResponse(new RedirectResponse($loginUrl));
                return;
            }

            // User is authenticated but lacks permission
            if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
                $event->setResponse(new JsonResponse([
                    'error' => 'Access denied',
                    'message' => sprintf('Permission "%s" required', $permissionName),
                    'required_permission' => $permissionName,
                ], Response::HTTP_FORBIDDEN));
            } else {
                throw new AccessDeniedHttpException(
                    sprintf('Permission "%s" required for this action', $permissionName)
                );
            }
        }
    }

    private function shouldCheck(\Symfony\Component\HttpFoundation\Request $request, ?string $route): bool
    {
        // Skip if no route matched
        if (!$route) {
            return false;
        }

        // Skip non-admin routes
        if (!str_starts_with($route, 'admin_')) {
            return false;
        }

        // Skip login, logout, and error routes
        $skipRoutes = [
            'app_login',
            'app_logout',
            'app_register',
            'admin_dashboard', // Allow dashboard access
        ];

        if (in_array($route, $skipRoutes, true)) {
            return false;
        }

        // Skip profiler and wdt in dev
        if (str_starts_with($route, '_profiler') || str_starts_with($route, '_wdt')) {
            return false;
        }

        return true;
    }
}