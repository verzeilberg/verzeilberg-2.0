<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Category;
use App\Repository\EventRepository;
use App\Repository\CategoryRepository;
use App\Repository\Strava\ActivityRepository;
use App\Service\Strava\StravaActivityService;
use App\Service\EventIcal;
use Doctrine\ORM\QueryBuilder;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EventController extends AbstractController
{
    private const STATUSES = ['upcoming', 'ongoing', 'past'];

    public function __construct(
        private EventRepository $eventRepository,
        private CategoryRepository $categoryRepository,
        private PaginatorInterface $paginator
    ) {}

    /** All events as an iCal feed, to subscribe to in Google/Apple/Outlook */
    #[Route('/events/feed.ics', name: 'app_events_feed', methods: ['GET'])]
    public function feed(Request $request, EventIcal $ical): Response
    {
        return $this->ical($ical->export($this->eventRepository->findAllActive(), 'Verzeilberg.nl events', $request->getHost()), 'verzeilberg-events.ics', HeaderUtils::DISPOSITION_INLINE);
    }

    /** One event as an .ics file, to add it to a calendar */
    #[Route('/event/{slug}.ics', name: 'app_event_ics', requirements: ['slug' => '[^/.]+'], methods: ['GET'], priority: 1)]
    public function ics(string $slug, Request $request, EventIcal $ical): Response
    {
        $event = $this->eventRepository->findOneBy(['slug' => $slug, 'enabled' => true]) ?? throw $this->createNotFoundException('Event niet gevonden');

        return $this->ical($ical->export([$event], $event->getTitle(), $request->getHost()), $slug . '.ics', HeaderUtils::DISPOSITION_ATTACHMENT);
    }

    private function ical(string $content, string $filename, string $disposition): Response
    {
        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition($disposition, $filename));
        // Calendar apps poll the feed; a short cache keeps it fresh without hammering the site
        $response->setPublic();
        $response->setMaxAge(900);

        return $response;
    }

    #[Route('/events', name: 'app_events', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $categoryId = $request->query->getInt('category') ?: null;
        $status = $request->query->get('status', 'upcoming');
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'upcoming';
        }

        $qb = $this->createFilteredQuery($status, $categoryId)
            ->leftJoin('e.category', 'c')
            ->addSelect('c')
            ->leftJoin('e.eventImage', 'i')
            ->addSelect('i')
            ->orderBy('e.eventStartDate', 'ASC');

        $pagination = $this->paginator->paginate(
            $qb->getQuery(),
            $request->query->getInt('page', 1),
            12
        );

        // Category counts for the sidebar, so each number matches the list behind its link (like the blog categories)
        $categoryCounts = [];
        $rows = $this->createFilteredQuery($status, null)
            ->select('IDENTITY(e.category) AS categoryId, COUNT(e.id) AS total')
            ->andWhere('e.category IS NOT NULL')
            ->groupBy('e.category')
            ->getQuery()
            ->getArrayResult();
        foreach ($rows as $row) {
            $categoryCounts[(int) $row['categoryId']] = (int) $row['total'];
        }

        // Only categories with events in this time frame, but never hide the one that is selected
        $categories = array_values(array_filter(
            $this->categoryRepository->findAllActive(),
            fn (Category $category) => ($categoryCounts[$category->getId()] ?? 0) > 0 || $category->getId() === $categoryId
        ));

        return $this->render('event/index.html.twig', [
            'pagination' => $pagination,
            'categories' => $categories,
            'categoryCounts' => $categoryCounts,
            'totalCount' => array_sum($categoryCounts) + $this->countWithoutCategory($status),
            'currentCategory' => $categoryId,
            'currentStatus' => $status,
        ]);
    }

    /**
     * Visible events in a time frame, optionally in one category.
     */
    private function createFilteredQuery(string $status, ?int $categoryId): QueryBuilder
    {
        $today = new \DateTimeImmutable('today');
        $qb = $this->eventRepository->createQueryBuilder('e')
            ->andWhere('e.enabled = true')
            ->andWhere('e.deleted = false')
            ->setParameter('today', $today);

        match ($status) {
            'past' => $qb->andWhere('e.eventEndDate < :today'),
            'ongoing' => $qb->andWhere('e.eventStartDate <= :today')->andWhere('e.eventEndDate >= :today'),
            default => $qb->andWhere('e.eventStartDate >= :today'),
        };

        if ($categoryId) {
            $qb->andWhere('e.category = :categoryId')
                ->setParameter('categoryId', $categoryId);
        }

        return $qb;
    }

    private function countWithoutCategory(string $status): int
    {
        return (int) $this->createFilteredQuery($status, null)
            ->select('COUNT(e.id)')
            ->andWhere('e.category IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    #[Route('/event/{slug}', name: 'app_event_show', methods: ['GET'])]
    public function show(string $slug, ActivityRepository $activityRepository, StravaActivityService $stravaService): Response
    {
        $event = $this->eventRepository->findOneBy(['slug' => $slug]) ?? throw $this->createNotFoundException('Event niet gevonden');

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

        // My result from Strava (an event that hasn't happened yet has none)
        $race = $activityRepository->findRaceForEvent($event);

        return $this->render('event/detail.html.twig', [
            'event' => $event,
            'relatedEvents' => $relatedEvents,
            'race' => $race,
            'racePoints' => $race ? $stravaService->decodePolyline($race->getSummaryPolyline() ?? '') : [],
        ]);
    }
}