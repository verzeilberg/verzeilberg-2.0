<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Role;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use App\Service\RegistrationNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/user')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private RoleRepository $roleRepository,
        private EntityManagerInterface $em,
        private PaginatorInterface $paginator,
        private UserPasswordHasherInterface $passwordHasher,
        private RegistrationNotifier $registrationNotifier
    ) {}

    #[Route('/', name: 'admin_user_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $status = $request->query->get('status', 'all');
        $search = $request->query->get('search', '');
        $roleId = $request->query->get('role');

        $qb = $this->userRepository->createQueryBuilder('u')
            ->leftJoin('u.roles', 'r')
            ->addSelect('r')
            ->orderBy('u.dateCreated', 'DESC');

        if ($status === 'active') {
            $qb->andWhere('u.status = :status')
                ->setParameter('status', User::STATUS_ACTIVE);
        } elseif ($status === 'inactive') {
            $qb->andWhere('u.status = :status')
                ->setParameter('status', User::STATUS_RETIRED);
        } elseif ($status === 'pending') {
            // Registrations whose address isn't confirmed yet are listed too, after the ones to approve
            $qb->andWhere('u.status = :status')
                ->setParameter('status', User::STATUS_PENDING)
                ->orderBy('u.emailVerifiedAt', 'DESC')
                ->addOrderBy('u.dateCreated', 'DESC');
        }

        if ($search) {
            $qb->andWhere('u.email LIKE :search OR u.fullName LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($roleId) {
            $qb->andWhere(':roleId MEMBER OF u.roles')
                ->setParameter('roleId', $roleId);
        }

        $activeCount = (int) $this->userRepository->createQueryBuilder('u')
            ->select('count(u.id)')
            ->andWhere('u.status = :status')
            ->andWhere('u.deleted = false')
            ->setParameter('status', User::STATUS_ACTIVE)
            ->getQuery()->getSingleScalarResult();

        $inactiveCount = (int) $this->userRepository->createQueryBuilder('u')
            ->select('count(u.id)')
            ->andWhere('u.status = :status')
            ->andWhere('u.deleted = false')
            ->setParameter('status', User::STATUS_RETIRED)
            ->getQuery()->getSingleScalarResult();

        $pendingCount = (int) $this->userRepository->createQueryBuilder('u')
            ->select('count(u.id)')
            ->andWhere('u.status = :status')
            ->andWhere('u.deleted = false')
            ->setParameter('status', User::STATUS_PENDING)
            ->getQuery()->getSingleScalarResult();

        $pagination = $this->paginator->paginate(
            $qb->getQuery(),
            $request->query->getInt('page', 1),
            20
        );

        $roles = $this->roleRepository->findAllActive();

        return $this->render('admin/user/index.html.twig', [
            'pagination' => $pagination,
            'currentStatus' => $status,
            'currentRole' => $roleId,
            'search' => $search,
            'roles' => $roles,
            'activeCount' => $activeCount,
            'inactiveCount' => $inactiveCount,
            'pendingCount' => $pendingCount,
        ]);
    }

    #[Route('/new', name: 'admin_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = new User();

        $form = $this->createForm(UserType::class, $user, ['is_edit' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

            $this->em->persist($user);
            $this->em->flush();

            $this->addFlash('success', 'Gebruiker aangemaakt.');

            return $this->redirectToRoute('admin_user_edit', ['id' => $user->getId()]);
        }

        return $this->render('admin/user/new.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user): Response
    {
        $wasPending = $user->isPending();
        $form = $this->createForm(UserType::class, $user, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $user === $this->getUser()) {
            // Don't let an admin lock themselves out
            if (!$user->hasRole('ROLE_ADMIN')) {
                $form->get('doctrineRoles')->addError(new FormError('Je kunt ROLE_ADMIN niet van je eigen account weghalen.'));
            }
            if (!$user->isActive()) {
                $form->get('status')->addError(new FormError('Je kunt je eigen account niet deactiveren.'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
            }

            $this->em->flush();

            if ($wasPending && $user->isActive()) {
                $this->registrationNotifier->userApproved($user);
            }

            $this->addFlash('success', 'Gebruiker opgeslagen.');

            return $this->redirectToRoute('admin_user_edit', ['id' => $user->getId()]);
        }

        return $this->render('admin/user/edit.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user): Response
    {
        if ($user === $this->getUser()) {
            $this->addFlash('danger', 'Je kunt je eigen account niet verwijderen.');
        } elseif ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $this->em->remove($user);
            $this->em->flush();
            $this->addFlash('success', 'Gebruiker verwijderd.');
        }

        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true]);
        }

        return $this->redirectToRoute('admin_user_index');
    }

    /** Approves an account that registered through the site; the user gets an email that they can log in */
    #[Route('/{id}/approve', name: 'admin_user_approve', methods: ['POST'])]
    public function approve(Request $request, User $user): Response
    {
        if (!$this->isCsrfTokenValid('approve' . $user->getId(), $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Je sessie is verlopen, probeer het opnieuw.');
        } elseif ($user->isPending()) {
            $user->setStatus(User::STATUS_ACTIVE);
            $this->em->flush();
            $this->registrationNotifier->userApproved($user);
            $this->addFlash('success', sprintf('%s is goedgekeurd en kan nu inloggen.', $user->getFullName()));
        }

        return $this->redirectToRoute('admin_user_index', $request->query->all());
    }

    /** Rejects a registration: the user gets an email, and the account and its details are deleted */
    #[Route('/{id}/reject', name: 'admin_user_reject', methods: ['POST'])]
    public function reject(Request $request, User $user): Response
    {
        if (!$this->isCsrfTokenValid('reject' . $user->getId(), $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Je sessie is verlopen, probeer het opnieuw.');
        } elseif ($user->isPending()) {
            $this->registrationNotifier->userRejected($user);
            $this->em->remove($user);
            $this->em->flush();
            $this->addFlash('success', sprintf('De registratie van %s is afgewezen en verwijderd.', $user->getFullName()));
        }

        return $this->redirectToRoute('admin_user_index', $request->query->all());
    }

    /**
     * For a user who lost their phone: back to login codes by email, without the authenticator app,
     * its backup codes and the devices they trusted
     */
    #[Route('/{id}/reset-two-factor', name: 'admin_user_reset_two_factor', methods: ['POST'])]
    public function resetTwoFactor(Request $request, User $user): Response
    {
        if (!$this->isCsrfTokenValid('reset_two_factor' . $user->getId(), $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Je sessie is verlopen, probeer het opnieuw.');
        } else {
            $user->useEmailCodes();
            $this->em->flush();
            $this->addFlash('success', sprintf('%s logt nu weer in met een code per e-mail; vertrouwde apparaten zijn vergeten.', $user->getFullName()));
        }

        return $this->redirectToRoute('admin_user_edit', ['id' => $user->getId()]);
    }

    #[Route('/{id}/toggle-status', name: 'admin_user_toggle_status', methods: ['POST'])]
    public function toggleStatus(Request $request, User $user): JsonResponse
    {
        if ($user === $this->getUser()) {
            return new JsonResponse(['success' => false, 'error' => 'Je kunt je eigen account niet deactiveren.'], Response::HTTP_BAD_REQUEST);
        }

        $newStatus = $user->getStatus() === User::STATUS_ACTIVE
            ? User::STATUS_RETIRED
            : User::STATUS_ACTIVE;

        $user->setStatus($newStatus);
        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'status' => $user->getStatus(),
            'isActive' => $user->isActive(),
        ]);
    }
}