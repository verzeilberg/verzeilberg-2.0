<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Event;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Events as iCal: a feed of all events to subscribe to, or one event to add to a calendar.
 * An event without times lasts whole days; with only a start time it lasts until its end time, or an hour.
 */
class EventIcal
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private string $appTimezone,
    ) {
    }

    /** @param iterable<Event> $events */
    public function export(iterable $events, string $name, string $host): string
    {
        $calendar = new VCalendar([
            'PRODID' => '-//Verzeilberg//Events//EN',
            'X-WR-CALNAME' => $name,
        ]);

        foreach ($events as $event) {
            $this->addEvent($calendar, $event, $host);
        }

        return $calendar->serialize();
    }

    private function addEvent(VCalendar $calendar, Event $event, string $host): void
    {
        $url = $this->urlGenerator->generate('app_event_show', ['slug' => $event->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
        $description = trim(html_entity_decode(strip_tags(implode("\n\n", array_filter([$event->getLabelText(), $event->getText()]))), ENT_QUOTES | ENT_HTML5));

        $vevent = $calendar->add('VEVENT', [
            'UID' => sprintf('event-%d@%s', $event->getId(), $host),
            'SUMMARY' => $event->getTitle(),
            'DTSTAMP' => ($event->getDateChanged() ?? $event->getDateCreated())->setTimezone(new \DateTimeZone('UTC')),
            'URL' => $url,
            'DESCRIPTION' => trim($description . "\n\n" . $url),
        ]);
        if (!$vevent instanceof VEvent) {
            return;
        }
        if ($event->getLatitude() && $event->getLongitude()) {
            $vevent->add('GEO', [(float) $event->getLatitude(), (float) $event->getLongitude()]);
        }

        $start = $event->getEventStartTime();
        if ($start === null) {
            // Whole days: DATE values, and DTEND is the day after the last day
            $vevent->add('DTSTART', $event->getEventStartDate(), ['VALUE' => 'DATE']);
            $vevent->add('DTEND', $event->getEventEndDate()->modify('+1 day'), ['VALUE' => 'DATE']);

            return;
        }

        $startAt = $this->toUtc($event->getEventStartDate(), $start);
        $end = $event->getEventEndTime();
        $endAt = $end !== null ? $this->toUtc($event->getEventEndDate(), $end) : $startAt->modify('+1 hour');
        $vevent->add('DTSTART', $startAt);
        $vevent->add('DTEND', $endAt > $startAt ? $endAt : $startAt->modify('+1 hour'));
    }

    /** A date and a wall time in the site's time zone, as UTC */
    private function toUtc(\DateTimeImmutable $date, \DateTimeImmutable $time): \DateTimeImmutable
    {
        return (new \DateTimeImmutable($date->format('Y-m-d') . ' ' . $time->format('H:i:s'), new \DateTimeZone($this->appTimezone)))
            ->setTimezone(new \DateTimeZone('UTC'));
    }
}
