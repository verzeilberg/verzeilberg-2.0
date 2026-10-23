<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Security\AdminRouteAccess;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Enforces AdminRouteAccess on every admin request. Runs after the firewall (priority 8), so the
 * user is known; access_control has already sent anonymous visitors of /admin to the login page.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 4)]
class RoutePermissionListener
{
    public function __construct(private AdminRouteAccess $adminRouteAccess)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        if (!AdminRouteAccess::isAdminRoute($route) || $this->adminRouteAccess->canAccess($route, $request->getMethod())) {
            return;
        }

        // The security exception listener turns this into a login redirect or a 403 page
        throw new AccessDeniedException(sprintf('Geen toegang tot route "%s".', $route));
    }
}
