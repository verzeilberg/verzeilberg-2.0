<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Twig\Environment;

/**
 * A logged-in user who may not open a page gets a friendly "no access" page (also in dev, instead of the
 * exception page). Visitors who aren't logged in are still sent to the login page by the firewall.
 */
class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(private Environment $twig)
    {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        if ($request->isXmlHttpRequest() || $request->getPreferredFormat() === 'json') {
            return new JsonResponse(['success' => false, 'message' => 'Je hebt hier geen toegang toe.'], Response::HTTP_FORBIDDEN);
        }

        return new Response($this->twig->render('security/access_denied.html.twig'), Response::HTTP_FORBIDDEN);
    }
}
