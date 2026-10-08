<?php

declare(strict_types=1);

namespace App\Service\Google;

use App\Entity\Agenda;
use App\Entity\AgendaItem;
use App\Repository\AgendaItemRepository;
use App\Repository\AgendaRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Copies the events of linked Google calendars into their agendas (Google → site).
 * Within the sync window the agenda follows Google exactly: new, changed and removed events.
 */
class GoogleCalendarSync
{
    public const UID_PREFIX = 'google:';
    private const WINDOW_PAST = '-1 year';
    private const WINDOW_FUTURE = '+2 years';

    public function __construct(
        private GoogleCalendarClient $client,
        private AgendaRepository $agendaRepository,
        private AgendaItemRepository $itemRepository,
        private EntityManagerInterface $em,
        private string $appTimezone,
    ) {
    }

    /**
     * Syncs all linked (not archived) agendas. An error in one agenda doesn't stop the others.
     *
     * @return array<string, array{created: int, updated: int, deleted: int}|string> per agenda title: counts or the error
     */
    public function syncAll(): array
    {
        $results = [];
        foreach ($this->agendaRepository->findBy(['deleted' => false]) as $agenda) {
            if (!$agenda->isGoogleLinked()) {
                continue;
            }
            try {
                $results[$agenda->getTitle()] = $this->sync($agenda);
            } catch (GoogleApiException $e) {
                $results[$agenda->getTitle()] = $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * @return array{created: int, updated: int, deleted: int}
     *
     * @throws GoogleApiException (also stored on the agenda, to show in the admin)
     */
    public function sync(Agenda $agenda): array
    {
        $account = $agenda->getGoogleAccount();
        $calendarId = $agenda->getGoogleCalendarId();
        if ($account === null || $calendarId === null) {
            throw new GoogleApiException(sprintf('Agenda "%s" is not linked to a Google calendar.', $agenda->getTitle()));
        }

        $timezone = new \DateTimeZone($this->appTimezone);
        $from = new \DateTimeImmutable('today ' . self::WINDOW_PAST, $timezone);
        $to = new \DateTimeImmutable('today ' . self::WINDOW_FUTURE, $timezone);

        try {
            $events = $this->client->listEvents($account, $calendarId, $from, $to);
        } catch (GoogleApiException $e) {
            $agenda->markSynced($e->getMessage());
            $this->em->flush();
            throw $e;
        }

        $result = ['created' => 0, 'updated' => 0, 'deleted' => 0];
        $seen = [];
        foreach ($events as $event) {
            if (($event['status'] ?? '') === 'cancelled' || !isset($event['id'], $event['start'])) {
                continue;
            }
            $uid = self::UID_PREFIX . $event['id'];
            $seen[$uid] = true;

            $item = $this->itemRepository->findOneByUid($agenda, $uid);
            $isNew = $item === null;
            $item ??= (new AgendaItem())->setAgenda($agenda)->setUid($uid);
            if (!$this->applyEvent($item, $event, $timezone)) {
                continue;
            }
            if ($isNew) {
                $this->em->persist($item);
                ++$result['created'];
            } else {
                $item->setDateChanged(new \DateTimeImmutable());
                ++$result['updated'];
            }
        }

        // Removed in Google (or cancelled): remove here too, but only within the window, so older history stays
        foreach ($this->itemRepository->findInPeriod([$agenda], $this->wallTime($from), $this->wallTime($to)) as $item) {
            $uid = (string) $item->getUid();
            if (str_starts_with($uid, self::UID_PREFIX) && !isset($seen[$uid])) {
                $this->em->remove($item);
                ++$result['deleted'];
            }
        }

        $agenda->markSynced();
        $this->em->flush();

        return $result;
    }

    /**
     * Copies a Google event onto an item; false when the event has no usable start.
     *
     * @param array<string, mixed> $event
     */
    private function applyEvent(AgendaItem $item, array $event, \DateTimeZone $timezone): bool
    {
        $start = $event['start'];
        $end = $event['end'] ?? $start;

        $item->setTitle(mb_substr(trim((string) ($event['summary'] ?? '')), 0, 255) ?: '(no title)');
        $item->setLocation(mb_substr(trim((string) ($event['location'] ?? '')), 0, 255) ?: null);
        $item->setDescription($this->plainText((string) ($event['description'] ?? '')));

        if (isset($start['date'])) {
            // Whole day(s); Google's end date is exclusive (the day after the last day)
            $firstDay = new \DateTimeImmutable((string) $start['date']);
            $lastDay = isset($end['date']) ? (new \DateTimeImmutable((string) $end['date']))->modify('-1 day') : $firstDay;
            $item->setAllDayPeriod($firstDay, max($firstDay, $lastDay));

            return true;
        }
        if (!isset($start['dateTime'])) {
            return false;
        }

        $startAt = (new \DateTimeImmutable((string) $start['dateTime']))->setTimezone($timezone);
        $endAt = isset($end['dateTime']) ? (new \DateTimeImmutable((string) $end['dateTime']))->setTimezone($timezone) : $startAt;
        $item->setAllDay(false);
        $item->setStartAt($this->wallTime($startAt));
        $item->setEndAt($this->wallTime(max($startAt, $endAt)));

        return true;
    }

    /** Google descriptions can contain HTML; the agenda shows plain text */
    private function plainText(string $html): ?string
    {
        $text = preg_replace('/<br\s*\/?>|<\/p>|<\/div>|<\/li>/i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);

        return $text !== '' ? $text : null;
    }

    /** Same wall time in PHP's default time zone, like the dates Doctrine loads (see AgendaIcal) */
    private function wallTime(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date->format('Y-m-d H:i:s'));
    }
}
