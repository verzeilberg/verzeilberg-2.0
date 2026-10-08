<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Agenda;
use App\Entity\AgendaItem;
use App\Entity\User;
use App\Form\AgendaItemType;
use App\Form\AgendaType;
use App\Repository\AgendaRepository;
use App\Repository\GoogleAccountRepository;
use App\Service\Google\GoogleCalendarClient;
use App\Service\AgendaCalendar;
use App\Service\AgendaIcal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/agenda')]
class AgendaController extends AbstractController
{
    public function __construct(
        private AgendaRepository $agendaRepository,
        private EntityManagerInterface $em,
    ) {
    }

    /** The calendar of all (or the selected) agendas */
    #[Route('/', name: 'admin_agenda_index', methods: ['GET'])]
    public function index(Request $request, AgendaCalendar $calendar): Response
    {
        $agendas = $this->agendaRepository->findActive();
        $selectedIds = array_map('intval', $request->query->all('agendas'));
        $selected = $selectedIds === [] ? $agendas : array_values(array_filter($agendas, fn (Agenda $agenda) => in_array($agenda->getId(), $selectedIds, true)));

        return $this->render('admin/agenda/index.html.twig', [
            'agendas' => $agendas,
            'selectedIds' => array_map(fn (Agenda $agenda) => $agenda->getId(), $selected),
            'calendar' => $calendar->build($selected, (string) $request->query->get('view', 'month'), $this->getDate($request)),
        ]);
    }

    /** Managing the agendas themselves */
    #[Route('/agendas', name: 'admin_agenda_agendas', methods: ['GET'])]
    public function agendas(
        GoogleCalendarClient $googleClient,
        GoogleAccountRepository $googleAccountRepository,
        #[Autowire('%kernel.project_dir%')] string $projectDir,
    ): Response
    {
        return $this->render('admin/agenda/agendas.html.twig', [
            'agendas' => $this->agendaRepository->findActive(),
            'archived' => $this->agendaRepository->findArchived(),
            'googleConfigured' => $googleClient->isConfigured(),
            'googleAccounts' => $googleAccountRepository->findBy([], ['email' => 'ASC']),
            'projectDir' => $projectDir,
        ]);
    }

    #[Route('/new', name: 'admin_agenda_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $agenda = new Agenda();
        $agenda->setCreatedBy($this->getAppUser());
        $form = $this->createForm(AgendaType::class, $agenda);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($agenda);
            $this->em->flush();
            $this->addFlash('success', sprintf('Agenda "%s" created.', $agenda->getTitle()));

            return $this->redirectToRoute('admin_agenda_agendas');
        }

        return $this->render('admin/agenda/agenda_form.html.twig', ['form' => $form, 'agenda' => $agenda]);
    }

    #[Route('/{id<\d+>}/edit', name: 'admin_agenda_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Agenda $agenda): Response
    {
        $form = $this->createForm(AgendaType::class, $agenda);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $agenda->setDateChanged(new \DateTimeImmutable());
            $agenda->setChangedBy($this->getAppUser());
            $this->em->flush();
            $this->addFlash('success', sprintf('Agenda "%s" saved.', $agenda->getTitle()));

            return $this->redirectToRoute('admin_agenda_agendas');
        }

        return $this->render('admin/agenda/agenda_form.html.twig', ['form' => $form, 'agenda' => $agenda]);
    }

    #[Route('/{id<\d+>}/archive', name: 'admin_agenda_archive', methods: ['POST'])]
    public function archive(Request $request, Agenda $agenda): JsonResponse
    {
        return $this->setArchived($request, $agenda, true);
    }

    #[Route('/{id<\d+>}/unarchive', name: 'admin_agenda_unarchive', methods: ['POST'])]
    public function unarchive(Request $request, Agenda $agenda): JsonResponse
    {
        return $this->setArchived($request, $agenda, false);
    }

    #[Route('/{id<\d+>}/delete', name: 'admin_agenda_delete', methods: ['POST'])]
    public function delete(Request $request, Agenda $agenda): JsonResponse
    {
        if (!$this->isValidToken($request, 'delete_agenda' . $agenda->getId())) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
        }

        $this->em->remove($agenda);
        $this->em->flush();
        $this->addFlash('success', sprintf('Agenda "%s" and its items have been deleted.', $agenda->getTitle()));

        return new JsonResponse(['success' => true]);
    }

    /** A new feed link: the old (shared) link stops working */
    #[Route('/{id<\d+>}/feed-token', name: 'admin_agenda_feed_token', methods: ['POST'])]
    public function regenerateFeedToken(Request $request, Agenda $agenda): JsonResponse
    {
        if (!$this->isValidToken($request, 'feed_token' . $agenda->getId())) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
        }

        $agenda->regenerateFeedToken();
        $this->em->flush();
        $this->addFlash('success', sprintf('The feed link of "%s" has been renewed; the old link no longer works.', $agenda->getTitle()));

        return new JsonResponse(['success' => true]);
    }

    /** Import events from an iCal (.ics) link or file */
    #[Route('/{id<\d+>}/import', name: 'admin_agenda_import', methods: ['GET', 'POST'])]
    public function import(Request $request, Agenda $agenda, AgendaIcal $ical): Response
    {
        if ($agenda->isGoogleLinked()) {
            $this->addFlash('warning', 'This agenda follows a Google calendar; importing into it is not possible.');

            return $this->redirectToRoute('admin_agenda_agendas');
        }
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('import_agenda' . $agenda->getId(), (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token');
            }

            try {
                $file = $request->files->get('file');
                $url = trim((string) $request->request->get('url', ''));
                if ($file instanceof UploadedFile) {
                    $ics = (string) file_get_contents($file->getPathname());
                } elseif ($url !== '') {
                    $ics = $ical->download($url);
                } else {
                    throw new \InvalidArgumentException('Enter a link or choose an .ics file.');
                }

                $result = $ical->import($agenda, $ics);
                $this->addFlash('success', sprintf(
                    'Import finished: %d new, %d updated%s.',
                    $result['created'],
                    $result['updated'],
                    $result['skipped'] ? sprintf(', %d skipped', $result['skipped']) : ''
                ));

                return $this->redirectToRoute('admin_agenda_index', ['agendas' => [$agenda->getId()]]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/agenda/import.html.twig', ['agenda' => $agenda]);
    }

    #[Route('/item/new', name: 'admin_agenda_item_new', methods: ['GET', 'POST'])]
    public function newItem(Request $request): Response
    {
        $item = new AgendaItem();
        $item->setCreatedBy($this->getAppUser());
        if ($date = $this->getDate($request, false)) {
            $item->setStartAt($date->setTime(9, 0))->setEndAt($date->setTime(10, 0));
        }
        if (($agenda = $this->agendaRepository->find($request->query->getInt('agenda'))) && !$agenda->isGoogleLinked()) {
            $item->setAgenda($agenda);
        }

        $form = $this->createForm(AgendaItemType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($item);
            $this->em->flush();
            $this->addFlash('success', sprintf('"%s" added to the agenda.', $item->getTitle()));

            return $this->redirectToCalendar($item);
        }

        return $this->render('admin/agenda/item_form.html.twig', ['form' => $form, 'item' => $item]);
    }

    #[Route('/item/{id<\d+>}/edit', name: 'admin_agenda_item_edit', methods: ['GET', 'POST'])]
    public function editItem(Request $request, AgendaItem $item): Response
    {
        if ($item->getAgenda()?->isGoogleLinked()) {
            $this->addFlash('warning', 'This item comes from Google Calendar; change it there. It is updated here automatically.');

            return $this->redirectToCalendar($item);
        }

        $form = $this->createForm(AgendaItemType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $item->setDateChanged(new \DateTimeImmutable());
            $item->setChangedBy($this->getAppUser());
            $this->em->flush();
            $this->addFlash('success', sprintf('"%s" saved.', $item->getTitle()));

            return $this->redirectToCalendar($item);
        }

        return $this->render('admin/agenda/item_form.html.twig', ['form' => $form, 'item' => $item]);
    }

    #[Route('/item/{id<\d+>}/delete', name: 'admin_agenda_item_delete', methods: ['POST'])]
    public function deleteItem(Request $request, AgendaItem $item): JsonResponse
    {
        if (!$this->isValidToken($request, 'delete_agenda_item' . $item->getId())) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
        }
        if ($item->getAgenda()?->isGoogleLinked()) {
            return new JsonResponse(['success' => false, 'message' => 'This item comes from Google Calendar; delete it there.'], 409);
        }

        $this->em->remove($item);
        $this->em->flush();

        return new JsonResponse(['success' => true]);
    }

    private function setArchived(Request $request, Agenda $agenda, bool $archived): JsonResponse
    {
        if (!$this->isValidToken($request, ($archived ? 'archive_agenda' : 'unarchive_agenda') . $agenda->getId())) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
        }

        $agenda->setDeleted($archived);
        $agenda->setDateDeleted($archived ? new \DateTimeImmutable() : null);
        $agenda->setDeletedBy($archived ? $this->getAppUser() : null);
        $this->em->flush();

        return new JsonResponse(['success' => true]);
    }

    /** The shared delete/confirm modal sends the token as X-CSRF-Token header */
    private function isValidToken(Request $request, string $id): bool
    {
        return $this->isCsrfTokenValid($id, $request->headers->get('X-CSRF-Token') ?? (string) $request->request->get('_token'));
    }

    private function redirectToCalendar(AgendaItem $item): Response
    {
        return $this->redirectToRoute('admin_agenda_index', ['view' => 'month', 'date' => $item->getStartAt()->format('Y-m-d')]);
    }

    /** The ?date=YYYY-MM-DD of the request; today (or null) when missing or invalid */
    private function getDate(Request $request, bool $todayAsDefault = true): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $request->query->get('date', ''));

        return $date ?: ($todayAsDefault ? new \DateTimeImmutable('today') : null);
    }

    private function getAppUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }
}
