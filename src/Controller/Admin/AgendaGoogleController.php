<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Agenda;
use App\Entity\GoogleAccount;
use App\Entity\User;
use App\Repository\AgendaRepository;
use App\Service\Google\GoogleApiException;
use App\Service\Google\GoogleCalendarClient;
use App\Service\Google\GoogleCalendarSync;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Connecting Google accounts (OAuth) and choosing which of their calendars are shown as agendas.
 */
#[Route('/admin/agenda/google')]
class AgendaGoogleController extends AbstractController
{
    private const STATE_SESSION_KEY = 'agenda_google_oauth_state';

    public function __construct(
        private GoogleCalendarClient $client,
        private GoogleCalendarSync $sync,
        private AgendaRepository $agendaRepository,
        private EntityManagerInterface $em,
    ) {
    }

    /** Sends the admin to Google to give the site read access to their calendars */
    #[Route('/connect', name: 'admin_agenda_google_connect', methods: ['GET'])]
    public function connect(Request $request): Response
    {
        if (!$this->client->isConfigured()) {
            $this->addFlash('error', 'Google is not configured yet: set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET (see the instructions on this page).');

            return $this->redirectToRoute('admin_agenda_agendas');
        }

        $state = bin2hex(random_bytes(16));
        $request->getSession()->set(self::STATE_SESSION_KEY, $state);

        return $this->redirect($this->client->getAuthorizationUrl($this->callbackUrl(), $state));
    }

    #[Route('/callback', name: 'admin_agenda_google_callback', methods: ['GET'])]
    public function callback(Request $request): Response
    {
        $expected = (string) $request->getSession()->remove(self::STATE_SESSION_KEY);
        if ($expected === '' || !hash_equals($expected, (string) $request->query->get('state', ''))) {
            $this->addFlash('error', 'The Google connection could not be verified (invalid state). Please try again.');

            return $this->redirectToRoute('admin_agenda_agendas');
        }
        if ($request->query->has('error')) {
            $this->addFlash('warning', 'Google access was not granted.');

            return $this->redirectToRoute('admin_agenda_agendas');
        }

        try {
            $account = $this->client->connect((string) $request->query->get('code', ''), $this->callbackUrl());
            $account->setConnectedBy($this->getAppUser());
            $this->em->flush();
        } catch (GoogleApiException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('admin_agenda_agendas');
        }

        $this->addFlash('success', sprintf('Google account %s connected. Choose which calendars to show.', $account->getEmail()));

        return $this->redirectToRoute('admin_agenda_google_calendars', ['id' => $account->getId()]);
    }

    /** Which calendars of the account are shown as agendas */
    #[Route('/{id<\d+>}/calendars', name: 'admin_agenda_google_calendars', methods: ['GET', 'POST'])]
    public function calendars(Request $request, GoogleAccount $account): Response
    {
        try {
            $calendars = $this->client->listCalendars($account);
        } catch (GoogleApiException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('admin_agenda_agendas');
        }

        $linked = [];
        foreach ($this->agendaRepository->findBy(['googleAccount' => $account]) as $agenda) {
            $linked[(string) $agenda->getGoogleCalendarId()] = $agenda;
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('google_calendars' . $account->getId(), (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token');
            }
            $chosen = array_flip(array_map('strval', $request->request->all('calendars')));
            $messages = [];

            foreach ($calendars as $calendar) {
                $agenda = $linked[$calendar['id']] ?? null;
                if (isset($chosen[$calendar['id']]) && $agenda === null) {
                    $agenda = (new Agenda())
                        ->setTitle($calendar['summary'])
                        ->setColor(preg_match('/^#[0-9a-fA-F]{6}$/', $calendar['color']) ? $calendar['color'] : '#4285f4')
                        ->linkToGoogle($account, $calendar['id']);
                    $agenda->setCreatedBy($this->getAppUser());
                    $this->em->persist($agenda);
                    $this->em->flush();
                    $messages[] = $this->syncMessage($agenda);
                } elseif (!isset($chosen[$calendar['id']]) && $agenda !== null) {
                    // No longer from Google: the agenda and its items stay, as a normal agenda
                    $agenda->linkToGoogle(null, null);
                    $messages[] = sprintf('"%s" is no longer synced with Google.', $agenda->getTitle());
                }
            }
            $this->em->flush();
            foreach ($messages as $message) {
                $this->addFlash(str_contains($message, 'failed') ? 'error' : 'success', $message);
            }

            return $this->redirectToRoute('admin_agenda_agendas');
        }

        return $this->render('admin/agenda/google_calendars.html.twig', [
            'account' => $account,
            'calendars' => $calendars,
            'linked' => $linked,
        ]);
    }

    /** Removes the connection; linked agendas become normal agendas (their items stay) */
    #[Route('/{id<\d+>}/disconnect', name: 'admin_agenda_google_disconnect', methods: ['POST'])]
    public function disconnect(Request $request, GoogleAccount $account): JsonResponse
    {
        if (!$this->isCsrfTokenValid('google_disconnect' . $account->getId(), $request->headers->get('X-CSRF-Token') ?? (string) $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
        }

        $this->client->revoke($account);
        foreach ($this->agendaRepository->findBy(['googleAccount' => $account]) as $agenda) {
            $agenda->linkToGoogle(null, null);
        }
        $this->em->remove($account);
        $this->em->flush();
        $this->addFlash('success', sprintf('Google account %s disconnected.', $account->getEmail()));

        return new JsonResponse(['success' => true]);
    }

    /** "Update now": one agenda, or all linked agendas */
    #[Route('/sync/{id<\d+>?}', name: 'admin_agenda_google_sync', methods: ['POST'])]
    public function sync(Request $request, ?int $id = null): JsonResponse
    {
        if (!$this->isCsrfTokenValid('google_sync' . $id, $request->headers->get('X-CSRF-Token') ?? (string) $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
        }

        if ($id !== null) {
            $agenda = $this->agendaRepository->find($id) ?? throw $this->createNotFoundException();
            $message = $this->syncMessage($agenda);
            $this->addFlash(str_contains($message, 'failed') ? 'error' : 'success', $message);
        } else {
            foreach ($this->sync->syncAll() as $title => $result) {
                $this->addFlash(is_string($result) ? 'error' : 'success', is_string($result)
                    ? sprintf('Updating "%s" failed: %s', $title, $result)
                    : sprintf('"%s" updated: %d new, %d changed, %d removed.', $title, $result['created'], $result['updated'], $result['deleted']));
            }
        }

        return new JsonResponse(['success' => true]);
    }

    private function syncMessage(Agenda $agenda): string
    {
        try {
            $result = $this->sync->sync($agenda);

            return sprintf('"%s" updated from Google: %d new, %d changed, %d removed.', $agenda->getTitle(), $result['created'], $result['updated'], $result['deleted']);
        } catch (GoogleApiException $e) {
            return sprintf('Updating "%s" failed: %s', $agenda->getTitle(), $e->getMessage());
        }
    }

    private function callbackUrl(): string
    {
        return $this->generateUrl('admin_agenda_google_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function getAppUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }
}
