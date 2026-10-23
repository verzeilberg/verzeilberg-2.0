<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Friendship;
use App\Entity\User;
use App\Repository\FriendshipRepository;
use App\Repository\UserRepository;
use App\Service\ActivityNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Friends: a user sends a request from someone's page, the other accepts or declines it on /friends.
 * Friends can see each other's page, also when it is private. The actions are POST forms with a CSRF
 * token per user ("friend{id}") and return to the page they came from (field "back").
 */
class FriendController extends AbstractController
{
    public function __construct(
        private FriendshipRepository $friendshipRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
        private ActivityNotifier $activityNotifier
    ) {}

    #[Route('/friends', name: 'app_friends', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->currentUser();

        return $this->render('friend/index.html.twig', [
            'friends' => $this->friendshipRepository->findFriendsOf($user),
            'incoming' => $this->friendshipRepository->findIncomingRequests($user),
            'outgoing' => $this->friendshipRepository->findOutgoingRequests($user),
        ]);
    }

    /** Sends a request; when the other user already asked, this accepts theirs */
    #[Route('/friends/{id<\d+>}/add', name: 'app_friend_add', methods: ['POST'])]
    public function add(int $id, Request $request): Response
    {
        $user = $this->currentUser();
        $other = $this->otherUser($id, $request);
        $friendship = $this->friendshipRepository->findBetween($user, $other);

        if ($friendship === null) {
            // Only from a page you can see: a private page can't be found to send a request to
            if (!$other->isProfilePublic()) {
                throw $this->createNotFoundException('Gebruiker niet gevonden');
            }
            $this->em->persist($friendship = new Friendship($user, $other));
            $this->em->flush();
            $this->activityNotifier->friendRequested($friendship);
            $this->addFlash('success', sprintf('Vriendschapsverzoek verstuurd naar %s.', $other->getFullName()));
        } elseif ($friendship->isPending() && $friendship->getAddressee() === $user) {
            $friendship->accept();
            $this->em->flush();
            $this->activityNotifier->friendAccepted($friendship);
            $this->addFlash('success', sprintf('Jij en %s zijn nu vrienden.', $other->getFullName()));
        }

        return $this->back($request, $other);
    }

    #[Route('/friends/{id<\d+>}/accept', name: 'app_friend_accept', methods: ['POST'])]
    public function accept(int $id, Request $request): Response
    {
        $user = $this->currentUser();
        $other = $this->otherUser($id, $request);
        $friendship = $this->friendshipRepository->findBetween($user, $other);

        if ($friendship?->isPending() && $friendship->getAddressee() === $user) {
            $friendship->accept();
            $this->em->flush();
            $this->activityNotifier->friendAccepted($friendship);
            $this->addFlash('success', sprintf('Jij en %s zijn nu vrienden.', $other->getFullName()));
        }

        return $this->back($request, $other);
    }

    /** Declines a request, cancels your own request or ends a friendship: all remove the pair */
    #[Route('/friends/{id<\d+>}/remove', name: 'app_friend_remove', methods: ['POST'])]
    public function remove(int $id, Request $request): Response
    {
        $user = $this->currentUser();
        $other = $this->otherUser($id, $request);
        $friendship = $this->friendshipRepository->findBetween($user, $other);

        if ($friendship !== null) {
            $message = match (true) {
                $friendship->isAccepted() => sprintf('%s is niet langer je vriend.', $other->getFullName()),
                $friendship->getRequester() === $user => sprintf('Je vriendschapsverzoek aan %s is ingetrokken.', $other->getFullName()),
                default => sprintf('Je hebt het vriendschapsverzoek van %s afgewezen.', $other->getFullName()),
            };
            $this->em->remove($friendship);
            $this->em->flush();
            $this->addFlash('success', $message);
        }

        // A private page is closed now that you're no longer friends
        if (!$other->isProfilePublic()) {
            return $this->redirectToRoute('app_friends');
        }

        return $this->back($request, $other);
    }

    private function currentUser(): User
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }

    /** The other user of an action, after checking the CSRF token */
    private function otherUser(int $id, Request $request): User
    {
        $other = $this->userRepository->find($id);

        if ($other === null || $other->isDeleted() || !$other->isActive() || $other === $this->getUser()) {
            throw $this->createNotFoundException('Gebruiker niet gevonden');
        }
        if (!$this->isCsrfTokenValid('friend' . $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Ongeldig beveiligingstoken');
        }

        return $other;
    }

    private function back(Request $request, User $other): RedirectResponse
    {
        // Only a path on this site, never an outside address
        $back = $request->request->getString('back');
        if (str_starts_with($back, '/') && !str_starts_with($back, '//') && !str_contains($back, '\\')) {
            return $this->redirect($back);
        }

        return $this->redirectToRoute('app_user_show', ['id' => $other->getId()]);
    }
}
