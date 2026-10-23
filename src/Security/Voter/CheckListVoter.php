<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\CheckList;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Who may change a checklist (its settings, fields and entries) in the admin:
 * users with "checklist.manage" (and admins) every checklist, users with "checklist.own" the ones they own.
 *
 * @extends Voter<string, CheckList>
 */
class CheckListVoter extends Voter
{
    public const MANAGE = 'CHECKLIST_MANAGE';

    /** Every checklist, the field types and the shared answers */
    public const PERM_MANAGE_ALL = 'PERM_checklist.manage';
    /** Make checklists and manage your own */
    public const PERM_OWN = 'PERM_checklist.own';

    public function __construct(private AccessDecisionManagerInterface $accessDecisionManager)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::MANAGE && $subject instanceof CheckList;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($this->accessDecisionManager->decide($token, [self::PERM_MANAGE_ALL])) {
            return true;
        }

        return $subject->isOwnedBy($user) && $this->accessDecisionManager->decide($token, [self::PERM_OWN]);
    }
}
