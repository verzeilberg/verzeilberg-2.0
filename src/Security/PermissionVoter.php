<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * Votes on "PERM_<permission name>", e.g. is_granted('PERM_blog.edit').
 * ROLE_ADMIN has every permission, so an admin can never lock themselves out.
 */
class PermissionVoter extends Voter
{
    public function __construct(private RoleHierarchyInterface $roleHierarchy)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return str_starts_with($attribute, 'PERM_');
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        // Halfway a two-factor login (password done, code not yet) the user has no permissions:
        // the token already carries the user, but hasPermission() below would read their roles from the database
        if ($token instanceof TwoFactorTokenInterface) {
            return false;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if (in_array('ROLE_ADMIN', $this->roleHierarchy->getReachableRoleNames($token->getRoleNames()), true)) {
            return true;
        }

        return $user->hasPermission(substr($attribute, 5));
    }
}
