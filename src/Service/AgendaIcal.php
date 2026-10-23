<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Agenda;
use App\Entity\AgendaItem;
use App\Repository\AgendaItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Property\ICalendar\DateTime as DateTimeProperty;
use Sabre\VObject\Reader;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * iCal (.ics) import into an agenda and export of an agenda as a subscribable feed.
 */
class AgendaIcal
{
    /** Recurring events are expanded into separate items within this window around today */
    private const RECURRENCE_PAST = '-1 year';
    private const RECURRENCE_FUTURE = '+2 years';
    private const MAX_DOWNLOAD_BYTES = 5 * 1024 * 1024;

    public function __construct(
        private EntityManagerInterface $em,
        private AgendaItemRepository $itemRepository,
        private HttpClientInterface $httpClient,
        private string $appTimezone,
    ) {
    }

    /**
     * Downloads an iCal file. webcal:// links (as used by calendar apps) are fetched over https.
     *
     * @throws \InvalidArgumentException for an unsupported URL or a download that fails
     */
    public function download(string $url): string
    {
        $url = trim($url);
        if (str_starts_with(strtolower($url), 'webcal://')) {
            $url = 'https://' . substr($url, 9);
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true) || !parse_url($url, PHP_URL_HOST)) {
            throw new \InvalidArgumentException('Alleen http(s)- en webcal-links kunnen worden geïmporteerd.');
        }

        try {
            $response = $this->httpClient->request('GET', $url, ['timeout' => 20, 'max_duration' => 30]);
            $content = '';
            foreach ($this->httpClient->stream($response) as $chunk) {
                $content .= $chunk->getContent();
                if (strlen($content) > self::MAX_DOWNLOAD_BYTES) {
                    $response->cancel();
                    throw new \InvalidArgumentException('De agenda is te groot (meer dan 5 MB).');
                }
            }
            if ($response->getStatusCode() >= 400) {
                throw new \InvalidArgumentException(sprintf('De agenda kon niet worden gedownload (HTTP %d).', $response->getStatusCode()));
            }
        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException('De agenda kon niet worden gedownload: ' . $e->getMessage(), 0, $e);
        }

        return $content;
    }

    /**
     * Imports the events of an iCal calendar into an agenda. An event that was imported before (same UID)
     * is updated, so importing the same calendar again doesn't create duplicates.
     *
     * @return array{created: int, updated: int, skipped: int}
     *
     * @throws \InvalidArgumentException when the content is not a valid iCal calendar
     */
    public function import(Agenda $agenda, string $ics): array
    {
        try {
            $calendar = Reader::read($ics, Reader::OPTION_FORGIVING);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException('Dit is geen geldige iCal-agenda (.ics).', 0, $e);
        }
        if (!$calendar instanceof VCalendar) {
            throw new \InvalidArgumentException('Dit is geen geldige iCal-agenda (.ics).');
        }

        $timezone = new \DateTimeZone($this->appTimezone);

        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        foreach ($this->getEvents($calendar, $timezone) as $event) {
            if (!isset($event->DTSTART)) {
                ++$result['skipped'];
                continue;
            }

            $uid = $this->getEventUid($event);
            $item = $uid !== null ? $this->itemRepository->findOneByUid($agenda, $uid) : null;
            $isNew = $item === null;
            $item ??= (new AgendaItem())->setAgenda($agenda)->setUid($uid);

            try {
                $this->applyEvent($item, $event, $timezone);
            } catch (\Throwable) {
                // One broken event doesn't stop the import
                ++$result['skipped'];
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

        $this->em->flush();

        return $result;
    }

    /** The agenda as an iCal calendar (for subscribing in Google/Apple/Outlook) */
    public function export(Agenda $agenda, string $host): string
    {
        $calendar = new VCalendar([
            'PRODID' => '-//Verzeilberg//Agenda//EN',
            'X-WR-CALNAME' => $agenda->getTitle(),
        ]);

        foreach ($agenda->getItems() as $item) {
            $properties = [
                'UID' => $item->getUid() ?? sprintf('agenda-item-%d@%s', $item->getId(), $host),
                'SUMMARY' => $item->getTitle(),
                'DTSTAMP' => ($item->getDateChanged() ?? $item->getDateCreated())->setTimezone(new \DateTimeZone('UTC')),
            ];
            if ($item->getDescription()) {
                $properties['DESCRIPTION'] = $item->getDescription();
            }
            if ($item->getLocation()) {
                $properties['LOCATION'] = $item->getLocation();
            }
            $event = $calendar->add('VEVENT', $properties);
            if (!$event instanceof VEvent) {
                continue;
            }

            if ($item->isAllDay()) {
                // Whole days: DATE values, and DTEND is the day after the last day
                $event->add('DTSTART', $item->getStartAt(), ['VALUE' => 'DATE']);
                $event->add('DTEND', $item->getEndAt()->setTime(0, 0)->modify('+1 day'), ['VALUE' => 'DATE']);
            } else {
                $event->add('DTSTART', $this->toUtc($item->getStartAt()));
                $event->add('DTEND', $this->toUtc($item->getEndAt()));
            }
        }

        return $calendar->serialize();
    }

    /**
     * The events to import. Recurring events are expanded into their occurrences within a window around today;
     * one-off events are kept whatever their date (expand() would drop those outside the window).
     *
     * @return list<VEvent>
     */
    private function getEvents(VCalendar $calendar, \DateTimeZone $timezone): array
    {
        $recurringUids = [];
        foreach ($calendar->select('VEVENT') as $event) {
            if (isset($event->RRULE) || isset($event->RDATE)) {
                $recurringUids[(string) ($event->UID ?? '')] = true;
            }
        }

        $events = [];
        foreach ($calendar->select('VEVENT') as $event) {
            // Occurrences (and changed occurrences) of recurring events come from the expansion below
            if ($event instanceof VEvent && !isset($recurringUids[(string) ($event->UID ?? '')])) {
                $events[] = $event;
            }
        }

        if ($recurringUids !== []) {
            $expanded = $calendar->expand(
                new \DateTimeImmutable(self::RECURRENCE_PAST),
                new \DateTimeImmutable(self::RECURRENCE_FUTURE),
                $timezone
            );
            foreach ($expanded->select('VEVENT') as $event) {
                if ($event instanceof VEvent && isset($recurringUids[(string) ($event->UID ?? '')])) {
                    $events[] = $event;
                }
            }
        }

        return $events;
    }

    /**
     * The same wall time ("2026-03-02 19:00") in PHP's default time zone, like the dates Doctrine loads,
     * so imported items compare correctly with other dates in the same request.
     */
    private function wallTime(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date->format('Y-m-d H:i:s'));
    }

    /** A stored wall time in the site's time zone, as UTC (for iCal) */
    private function toUtc(\DateTimeImmutable $wallTime): \DateTimeImmutable
    {
        return (new \DateTimeImmutable($wallTime->format('Y-m-d H:i:s'), new \DateTimeZone($this->appTimezone)))
            ->setTimezone(new \DateTimeZone('UTC'));
    }

    private function applyEvent(AgendaItem $item, VEvent $event, \DateTimeZone $timezone): void
    {
        $item->setTitle(mb_substr(trim((string) ($event->SUMMARY ?? '')), 0, 255) ?: '(geen titel)');
        $item->setDescription(isset($event->DESCRIPTION) ? trim((string) $event->DESCRIPTION) ?: null : null);
        $item->setLocation(isset($event->LOCATION) ? mb_substr(trim((string) $event->LOCATION), 0, 255) ?: null : null);

        $startProperty = $event->select('DTSTART')[0] ?? null;
        if (!$startProperty instanceof DateTimeProperty) {
            throw new \InvalidArgumentException('Afspraak zonder geldige starttijd.');
        }
        $start = \DateTimeImmutable::createFromInterface($startProperty->getDateTime($timezone));
        $end = isset($event->DTEND)
            ? \DateTimeImmutable::createFromInterface($event->DTEND->getDateTime($timezone))
            : (isset($event->DURATION) ? $start->add($event->DURATION->getDateInterval()) : null);

        if (!$startProperty->hasTime()) {
            // Whole day(s); iCal's DTEND is exclusive (the day after the last day)
            $lastDay = $end !== null ? $end->modify('-1 day') : $start;
            $item->setAllDayPeriod($this->wallTime($start), $this->wallTime(max($start, $lastDay)));
        } else {
            $item->setAllDay(false);
            $item->setStartAt($this->wallTime($start->setTimezone($timezone)));
            $item->setEndAt($this->wallTime(max($start, $end ?? $start)->setTimezone($timezone)));
        }
    }

    /** UID of an event; an occurrence of a recurring event also gets its RECURRENCE-ID, so every occurrence is unique */
    private function getEventUid(VEvent $event): ?string
    {
        if (!isset($event->UID)) {
            return null;
        }
        $uid = (string) $event->UID;
        if (isset($event->{'RECURRENCE-ID'})) {
            $uid .= '#' . $event->{'RECURRENCE-ID'}->getDateTime()->format('Ymd\THis');
        }

        return mb_substr($uid, 0, 255);
    }
}
