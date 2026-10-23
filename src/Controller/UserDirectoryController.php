<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\BlogCommentRepository;
use App\Repository\FriendshipRepository;
use App\Repository\UserRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The public user directory. Only users who switched on "public page" on their profile are listed
 * and can be opened; a private page is a 404 for everyone but its owner, their friends and administrators.
 */
class UserDirectoryController extends AbstractController
{
    private const PER_PAGE = 24;

    public function __construct(
        private UserRepository $userRepository,
        private FriendshipRepository $friendshipRepository,
        private BlogCommentRepository $blogCommentRepository,
        private PaginatorInterface $paginator
    ) {}

    #[Route('/users', name: 'app_users', methods: ['GET'])]
    #[Route('/users/page/{page<\d+>}', name: 'app_users_paginated', methods: ['GET'])]
    public function index(Request $request, int $page = 1): Response
    {
        $searchTerm = trim((string) $request->query->get('q', ''));

        $pagination = $this->paginator->paginate(
            $this->userRepository->createPublicDirectoryQuery($searchTerm),
            $page,
            self::PER_PAGE
        );

        return $this->render('user/index.html.twig', [
            'pagination' => $pagination,
            'searchTerm' => $searchTerm,
        ]);
    }

    #[Route('/users/{id<\d+>}', name: 'app_user_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $user = $this->userRepository->find($id);
        $viewer = $this->getUser();

        if ($user === null || $user->isDeleted()) {
            throw $this->createNotFoundException('Gebruiker niet gevonden');
        }

        $isOwner = $viewer === $user;
        $friendship = $viewer instanceof User && !$isOwner ? $this->friendshipRepository->findBetween($viewer, $user) : null;
        $visible = $isOwner
            || $this->isGranted('ROLE_ADMIN')
            || ($user->isActive() && ($user->isProfilePublic() || $friendship?->isAccepted()));

        if (!$visible) {
            throw $this->createNotFoundException('Gebruiker niet gevonden');
        }

        return $this->render('user/show.html.twig', [
            'profileUser' => $user,
            'isOwner' => $isOwner,
            'friendship' => $friendship,
            // Others only see the friends whose own page is public
            'friends' => $this->friendshipRepository->findFriendsOf($user, onlyPublic: !$isOwner),
            'recentComments' => $this->blogCommentRepository->findRecentPublishedBy($user),
        ]);
    }
}
