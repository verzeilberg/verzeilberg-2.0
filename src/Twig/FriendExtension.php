<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Repository\FriendshipRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class FriendExtension extends AbstractExtension
{
    public function __construct(
        private FriendshipRepository $friendshipRepository,
        private Security $security
    ) {}

    public function getFunctions(): array
    {
        return [
            // {{ friend_request_count() }}: open friend requests for the logged-in user (the badge in the menu)
            new TwigFunction('friend_request_count', $this->friendRequestCount(...)),
        ];
    }

    public function friendRequestCount(): int
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $this->friendshipRepository->countIncomingRequests($user) : 0;
    }
}
