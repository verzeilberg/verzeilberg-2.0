<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Agenda;
use App\Repository\AgendaRepository;
use App\Service\AgendaCalendar;
use App\Service\AgendaIcal;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * The public agendas on the site (read-only) and the iCal feeds.
 */
class AgendaController extends AbstractController
{
    public function __construct(
        private AgendaRepository $agendaRepository,
    ) {
    }

    #[Route('/agenda', name: 'app_agenda', methods: ['GET'])]
    public function index(Request $request, AgendaCalendar $calendar): Response
    {
        $agendas = $this->agendaRepository->findPublic();
        $selectedIds = array_map('intval', $request->query->all('agendas'));
        $selected = $selectedIds === [] ? $agendas : array_values(array_filter($agendas, fn (Agenda $agenda) => in_array($agenda->getId(), $selectedIds, true)));
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $request->query->get('date', '')) ?: new \DateTimeImmutable('today');

        return $this->render('agenda/index.html.twig', [
            'agendas' => $agendas,
            'selectedIds' => array_map(fn (Agenda $agenda) => $agenda->getId(), $selected),
            'calendar' => $calendar->build($selected, (string) $request->query->get('view', 'month'), $date),
        ]);
    }

    /**
     * iCal feed of an agenda to subscribe to in Google/Apple/Outlook. The secret token is the access check,
     * so this also works for an agenda that is not public (for whoever has the link). Archived agendas have no feed.
     */
    #[Route('/agenda/feed/{token<[a-f0-9]{48}>}.ics', name: 'app_agenda_feed', methods: ['GET'])]
    public function feed(string $token, Request $request, AgendaIcal $ical, SluggerInterface $slugger): Response
    {
        $agenda = $this->agendaRepository->findOneBy(['feedToken' => $token, 'deleted' => false]);
        if (!$agenda) {
            throw $this->createNotFoundException('Feed not found');
        }

        $response = new Response($ical->export($agenda, $request->getHost()));
        $response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_INLINE,
            strtolower((string) $slugger->slug($agenda->getTitle())) . '.ics'
        ));
        // Calendar apps poll the feed; a short cache keeps it fresh without hammering the site
        $response->setPublic();
        $response->setMaxAge(900);

        return $response;
    }
}
