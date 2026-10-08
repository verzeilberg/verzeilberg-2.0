<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Agenda;
use App\Entity\AgendaItem;
use App\Repository\AgendaItemRepository;

/**
 * Builds the month, week and day views of one or more agendas.
 * Weeks start on Monday and use ISO week numbers.
 */
class AgendaCalendar
{
    public const VIEWS = ['month', 'week', 'day'];

    public function __construct(
        private AgendaItemRepository $itemRepository,
    ) {
    }

    /**
     * Everything a calendar template needs for one view.
     *
     * @param Agenda[] $agendas
     *
     * @return array{
     *     view: string, date: \DateTimeImmutable, from: \DateTimeImmutable, to: \DateTimeImmutable,
     *     previous: \DateTimeImmutable, next: \DateTimeImmutable,
     *     weeks: list<array{number: int, days: list<array{date: \DateTimeImmutable, inMonth: bool, items: list<AgendaItem>}>}>,
     *     days: list<array{date: \DateTimeImmutable, inMonth: bool, items: list<AgendaItem>}>,
     *     items: list<AgendaItem>
     * }
     */
    public function build(array $agendas, string $view, \DateTimeImmutable $date): array
    {
        $view = in_array($view, self::VIEWS, true) ? $view : 'month';
        $date = $date->setTime(0, 0);

        [$from, $to] = $this->getPeriod($view, $date);
        $items = $this->itemRepository->findInPeriod($agendas, $from, $to);

        $days = [];
        for ($day = $from; $day < $to; $day = $day->modify('+1 day')) {
            $days[] = [
                'date' => $day,
                'inMonth' => $view !== 'month' || $day->format('n') === $date->format('n'),
                'items' => array_values(array_filter($items, fn (AgendaItem $item) => $item->isOnDay($day))),
            ];
        }

        $weeks = [];
        foreach (array_chunk($days, 7) as $week) {
            $weeks[] = ['number' => (int) $week[0]['date']->format('W'), 'days' => $week];
        }

        $step = match ($view) {
            'day' => '1 day',
            'week' => '1 week',
            default => '1 month',
        };
        // From the first of the month, so e.g. 31 January + 1 month doesn't skip February
        $anchor = $view === 'month' ? $date->modify('first day of this month') : $date;

        return [
            'view' => $view,
            'date' => $date,
            'from' => $from,
            'to' => $to,
            'previous' => $anchor->modify('-' . $step),
            'next' => $anchor->modify('+' . $step),
            'weeks' => $weeks,
            'days' => $days,
            'items' => $items,
        ];
    }

    /**
     * [from, to) of a view. A month view shows whole weeks: from the Monday on or before the 1st
     * until the Sunday on or after the last day.
     *
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    public function getPeriod(string $view, \DateTimeImmutable $date): array
    {
        $date = $date->setTime(0, 0);

        return match ($view) {
            'day' => [$date, $date->modify('+1 day')],
            'week' => [$this->startOfWeek($date), $this->startOfWeek($date)->modify('+7 days')],
            default => [
                $this->startOfWeek($date->modify('first day of this month')),
                $this->startOfWeek($date->modify('last day of this month'))->modify('+7 days'),
            ],
        };
    }

    private function startOfWeek(\DateTimeImmutable $date): \DateTimeImmutable
    {
        // ISO-8601: Monday = 1 ... Sunday = 7
        return $date->modify('-' . ((int) $date->format('N') - 1) . ' days');
    }
}
