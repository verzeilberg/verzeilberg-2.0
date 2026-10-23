<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\RoutePermission;
use App\Repository\RoutePermissionRepository;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Decides who may open an admin route ("admin_*"):
 *  - ROLE_ADMIN may open everything;
 *  - other users need the "admin.access" permission to enter the admin at all (the dashboard),
 *  - and for any other admin page a route permission rule whose permission they have.
 * Admin routes without a rule, and the user/role/permission management, stay ROLE_ADMIN only.
 */
class AdminRouteAccess
{
    public const ADMIN_ACCESS_PERMISSION = 'admin.access';

    /** Routes that only ROLE_ADMIN may open, whatever the rules say (so nobody can grant themselves more) */
    public const ADMIN_ONLY_PREFIXES = ['admin_user_', 'admin_role_', 'admin_permission_', 'admin_route_permission_'];

    /** @var list<RoutePermission>|null enabled rules, loaded once per request */
    private ?array $rules = null;

    public function __construct(
        private RoutePermissionRepository $routePermissionRepository,
        private AuthorizationCheckerInterface $authorizationChecker,
        private RouterInterface $router,
    ) {
    }

    public static function isAdminRoute(?string $route): bool
    {
        return $route !== null && str_starts_with($route, 'admin_');
    }

    public static function isAdminOnlyRoute(string $route): bool
    {
        foreach (self::ADMIN_ONLY_PREFIXES as $prefix) {
            if (str_starts_with($route, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** Whether the current user may open the route; for non-admin routes this always says yes */
    public function canAccess(string $route, string $method = 'GET'): bool
    {
        if (!self::isAdminRoute($route) || $this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return true;
        }

        if (!$this->authorizationChecker->isGranted('PERM_' . self::ADMIN_ACCESS_PERMISSION)) {
            return false;
        }

        if ($route === 'admin_dashboard') {
            return true;
        }

        $permission = $this->requiredPermission($route, $method);

        return $permission !== null && $this->authorizationChecker->isGranted('PERM_' . $permission);
    }

    /** The permission that opens the route for non-admins, or null when it's for ROLE_ADMIN only */
    public function requiredPermission(string $route, string $method = 'GET'): ?string
    {
        if (self::isAdminOnlyRoute($route)) {
            return null;
        }

        return $this->findRule($route, $method)?->getPermission()?->getName();
    }

    public function findRule(string $route, ?string $method = null): ?RoutePermission
    {
        $this->rules ??= $this->routePermissionRepository->findAllEnabled();

        foreach ($this->rules as $rule) {
            if ($rule->matches($route, $method)) {
                return $rule;
            }
        }

        return null;
    }

    /** @return array<string, list<string>> admin route name => its HTTP methods (empty = any), sorted by name */
    public function getAdminRoutes(): array
    {
        $routes = [];
        foreach ($this->router->getRouteCollection() as $name => $route) {
            if (self::isAdminRoute($name)) {
                $routes[$name] = $route->getMethods();
            }
        }
        ksort($routes);

        return $routes;
    }

    /**
     * Admin routes grouped per controller, with the wildcard pattern that covers the group
     *
     * @return array<string, array{pattern: string, routes: list<string>}> keyed by section name ("Blog"), sorted
     */
    public function getAdminRouteSections(): array
    {
        $sections = [];
        foreach ($this->router->getRouteCollection() as $name => $route) {
            if (!self::isAdminRoute($name)) {
                continue;
            }
            $controller = explode('::', (string) $route->getDefault('_controller'))[0];
            $section = preg_replace('/Controller$/', '', substr((string) strrchr('\\' . $controller, '\\'), 1)) ?: $name;
            $sections[$section]['routes'][] = $name;
        }
        ksort($sections);

        foreach ($sections as $section => $data) {
            sort($data['routes']);
            $sections[$section] = ['pattern' => self::commonPattern($data['routes']), 'routes' => $data['routes']];
        }

        return $sections;
    }

    /** "admin_blog_index" + "admin_blog_edit" => "admin_blog_*"; a single route => its name */
    private static function commonPattern(array $routes): string
    {
        if (count($routes) === 1) {
            return $routes[0];
        }

        $prefix = $routes[0];
        foreach ($routes as $route) {
            while (!str_starts_with($route, $prefix)) {
                $prefix = substr($prefix, 0, -1);
            }
        }

        // Cut back to a whole word, so "admin_blog_" and not "admin_blog_e"
        $prefix = substr($prefix, 0, (int) strrpos($prefix, '_') + 1);

        return $prefix . '*';
    }
}
