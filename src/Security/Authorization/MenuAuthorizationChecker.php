<?php

declare(strict_types=1);

namespace App\Security\Authorization;

use App\Entity\MenuItem;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class MenuAuthorizationChecker
{
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker
    ) {}

    public function isGranted(MenuItem $item): bool
    {
        if (!$item->isEnabled()) {
            return false;
        }

        $authorizedFor = $item->getAuthorizedFor();
        if (empty($authorizedFor)) {
            return true;
        }

        $roles = array_map('trim', explode(',', $authorizedFor));

        foreach ($roles as $role) {
            if ($this->authorizationChecker->isGranted($role)) {
                return true;
            }
        }

        return false;
    }
}