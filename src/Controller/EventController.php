<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Category;
use App\Repository\EventRepository;
use App\Repository\CategoryRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EventController extends AbstractController
{
    public function __construct(
        private EventRepository $eventRepository,
        private CategoryRepository $categoryRepository,
        private PaginatorInterface $paginator
    ) {}

    #[Route('/events', name: 'app_events', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $categoryId = $request->query->get('category');
        $status = $request->query->get('status', 'upcoming');

        $qb = $this->eventRepository->createQueryBuilder('e')
            ->leftJoin('e.category', 'c')
            ->addSelect('c')
            ->leftJoin('e.eventImage', 'i')
            ->addSelect('i')
            ->orderBy('e.eventStartDate', 'ASC');

        if ($categoryId) {
            $qb->andWhere('e.category = :categoryId')
                ->setParameter('categoryId', $categoryId);
        }

        switch ($status) {
            case 'past':
                $qb->andWhere('e.eventEndDate < :today')
                    ->setParameter('today', new \DateTimeImmutable('today'));
                break;
            case 'ongoing':
                $today = new \DateTimeImmutable('today');
                $qb->andWhere('e.eventStartDate <= :today')
                    ->andWhere('e.eventEndDate >= :today')
                    ->setParameter('today', $today);
                break;
            case 'upcoming':
            default:
                $qb->andWhere('e.eventStartDate >= :today')
                    ->setParameter('today', new \DateTimeImmutable('today'));
                break;
        }

        $categories = $this->categoryRepository->findAllActive();

        $pagination = $this->paginator->paginate(
            $qb->getQuery(),
            $request->query->getInt('page', 1),
            12
        );

        return $this->render('event/index.html.twig', [
            'pagination' => $pagination,
            'categories' => $categories,
            'currentCategory' => $categoryId,
            'currentStatus' => $status,
        ]);
    }

    #[Route('/event/{id}', name: 'app_event_show', methods: ['GET'])]
    public function show(Event $event): Response
    {
        $relatedEvents = $this->eventRepository->createQueryBuilder('e')
            ->leftJoin('e.category', 'c')
            ->addSelect('c')
            ->leftJoin('e.eventImage', 'i')
            ->addSelect('i')
            ->andWhere('e.id != :id')
            ->andWhere('e.enabled = true')
            ->andWhere('e.eventStartDate >= :today')
            ->setParameter('id', $event->getId())
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('e.eventStartDate', 'ASC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();

        return $this->render('event/detail.html.twig', [
            'event' => $event,
            'relatedEvents' => $relatedEvents,
        ]);
    }
}