<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Role;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        private UserPasswordHasherInterface $passwordHasher
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

        $pagination = $this->paginator->paginate(
            $qb->getQuery(),
            $request->query->getInt('page', 1),
            20
        );

        $roles = $this->roleRepository->findBy([], ['name' => 'ASC']);

        return $this->render('admin/user/index.html.twig', [
            'pagination' => $pagination,
            'currentStatus' => $status,
            'currentRole' => $roleId,
            'search' => $search,
            'roles' => $roles,
            'activeCount' => $activeCount,
            'inactiveCount' => $inactiveCount,
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

            $this->addFlash('success', 'User created successfully.');

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
        $form = $this->createForm(UserType::class, $user, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
            }

            $this->em->flush();

            $this->addFlash('success', 'User updated successfully.');

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
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $this->em->remove($user);
            $this->em->flush();
            $this->addFlash('success', 'User deleted successfully.');
        }

        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true]);
        }

        return $this->redirectToRoute('admin_user_index');
    }

    #[Route('/{id}/toggle-status', name: 'admin_user_toggle_status', methods: ['POST'])]
    public function toggleStatus(Request $request, User $user): JsonResponse
    {
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