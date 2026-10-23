<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/category')]
class CategoryController extends AbstractController
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private EntityManagerInterface $em,
        private PaginatorInterface $paginator,
        private SluggerInterface $slugger
    ) {}

    #[Route('/', name: 'admin_category_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $status = $request->query->get('status', 'all');
        
        $qb = $this->categoryRepository->createQueryBuilder('c')
            ->orderBy('c.name', 'ASC');

        if ($status === 'enabled') {
            $qb->andWhere('c.enabled = true');
        } elseif ($status === 'disabled') {
            $qb->andWhere('c.enabled = false');
        }

        $query = $qb->getQuery();

        $enabledCount = (int) $this->categoryRepository->createQueryBuilder('c')
            ->select('count(c.id)')
            ->andWhere('c.enabled = true')
            ->getQuery()->getSingleScalarResult();
        
        $disabledCount = (int) $this->categoryRepository->createQueryBuilder('c')
            ->select('count(c.id)')
            ->andWhere('c.enabled = false')
            ->getQuery()->getSingleScalarResult();

        $pagination = $this->paginator->paginate($query, $request->query->getInt('page', 1), 20);

        return $this->render('admin/category/index.html.twig', [
            'pagination' => $pagination,
            'currentStatus' => $status,
            'enabledCount' => $enabledCount,
            'disabledCount' => $disabledCount,
        ]);
    }

    #[Route('/{id}/toggle', name: 'admin_category_toggle', methods: ['POST'])]
    public function toggle(Request $request, Category $category): Response
    {
        if ($this->isCsrfTokenValid('toggle' . $category->getId(), $request->request->get('_token'))) {
            $category->setEnabled(!$category->isEnabled());
            $this->em->flush();
            $this->addFlash('success', 'Categorie ' . ($category->isEnabled() ? 'ingeschakeld' : 'uitgeschakeld') . '.');
        }

        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true, 'enabled' => $category->isEnabled()]);
        }

        return $this->redirectToRoute('admin_category_index');
    }

    #[Route('/new', name: 'admin_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $category->setSlug((string) $this->slugger->slug($category->getName())->lower());

            $this->em->persist($category);
            $this->em->flush();

            $this->addFlash('success', 'Categorie aangemaakt.');

            return $this->redirectToRoute('admin_category_index');
        }

        return $this->render('admin/category/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_category_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Category $category): Response
    {
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $category->setSlug((string) $this->slugger->slug($category->getName())->lower());

            $this->em->flush();

            $this->addFlash('success', 'Categorie opgeslagen.');

            return $this->redirectToRoute('admin_category_index');
        }

        return $this->render('admin/category/edit.html.twig', [
            'form' => $form,
            'category' => $category,
        ]);
    }

    #[Route('/{id}', name: 'admin_category_delete', methods: ['POST'])]
    public function delete(Request $request, Category $category): Response
    {
        if ($this->isCsrfTokenValid('delete' . $category->getId(), $request->request->get('_token'))) {
            if ($category->getBlogPosts()->count() > 0) {
                $message = 'Een categorie met berichten kan niet worden verwijderd.';
                if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
                    return new JsonResponse(['success' => false, 'message' => $message]);
                }
                $this->addFlash('error', $message);
            } else {
                $category->setEnabled(false);
                $this->em->flush();
                $message = 'Categorie verwijderd.';
                if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
                    return new JsonResponse(['success' => true]);
                }
                $this->addFlash('success', $message);
            }
        } else {
            $message = 'Ongeldig beveiligingstoken';
            if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
                return new JsonResponse(['success' => false, 'message' => $message]);
            }
        }

        return $this->redirectToRoute('admin_category_index');
    }
}