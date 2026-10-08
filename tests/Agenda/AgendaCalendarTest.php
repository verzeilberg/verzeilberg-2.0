<?php

declare(strict_types=1);

namespace App\Tests\Agenda;

use App\Service\AgendaCalendar;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AgendaCalendarTest extends KernelTestCase
{
    use AgendaTestTrait;

    private AgendaCalendar $calendar;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->beginAgendaTransaction();
        $this->calendar = static::getContainer()->get(AgendaCalendar::class);
    }

    protected function tearDown(): void
    {
        $this->rollBackAgendaTransaction();
        parent::tearDown();
    }

    public function testMonthShowsWholeWeeksFromMondayWithIsoWeekNumbers(): void
    {
        // October 2026 starts on a Thursday and ends on a Saturday
        $view = $this->calendar->build([], 'month', new \DateTimeImmutable('2026-10-15'));

        $this->assertSame('2026-09-28', $view['from']->format('Y-m-d'));
        $this->assertSame('2026-11-02', $view['to']->format('Y-m-d'));
        $this->assertSame([40, 41, 42, 43, 44], array_column($view['weeks'], 'number'));
        $this->assertFalse($view['weeks'][0]['days'][0]['inMonth'], '28 September is outside the month');
        $this->assertTrue($view['weeks'][0]['days'][3]['inMonth'], '1 October');
    }

    public function testNavigationDoesNotSkipShortMonths(): void
    {
        $view = $this->calendar->build([], 'month', new \DateTimeImmutable('2026-01-31'));

        $this->assertSame('2026-02', $view['next']->format('Y-m'));
        $this->assertSame('2025-12', $view['previous']->format('Y-m'));
    }

    public function testWeekAndDayPeriods(): void
    {
        $week = $this->calendar->build([], 'week', new \DateTimeImmutable('2026-10-08')); // a Thursday
        $this->assertSame(['2026-10-05', '2026-10-12'], [$week['from']->format('Y-m-d'), $week['to']->format('Y-m-d')]);
        $this->assertCount(7, $week['days']);

        $day = $this->calendar->build([], 'day', new \DateTimeImmutable('2026-10-08 15:30'));
        $this->assertSame(['2026-10-08 00:00', '2026-10-09 00:00'], [$day['from']->format('Y-m-d H:i'), $day['to']->format('Y-m-d H:i')]);
    }

    public function testMultiDayItemIsShownOnEveryDayWithoutChangingIt(): void
    {
        $agenda = $this->createAgenda();
        $camp = $this->createItem($agenda, 'Kamp', '2026-10-09', '2026-10-12', allDay: true);

        $view = $this->calendar->build([$agenda], 'month', new \DateTimeImmutable('2026-10-01'));
        $daysWithItem = [];
        foreach ($view['days'] as $day) {
            if ($day['items'] !== []) {
                $daysWithItem[] = $day['date']->format('d');
            }
        }

        $this->assertSame(['09', '10', '11', '12'], $daysWithItem);
        $this->assertSame('2026-10-09 00:00', $camp->getStartAt()->format('Y-m-d H:i'), 'the item itself is not modified');
    }

    public function testItemsFromBeforeAndAfterThePeriodAndOtherAgendas(): void
    {
        $agenda = $this->createAgenda();
        $other = $this->createAgenda('Other');
        $this->createItem($agenda, 'Over the month boundary', '2026-09-30 22:00', '2026-10-01 02:00');
        $this->createItem($agenda, 'Next month', '2026-11-20 10:00', '2026-11-20 11:00');
        $this->createItem($other, 'Other agenda', '2026-10-05 10:00', '2026-10-05 11:00');

        $day = $this->calendar->build([$agenda], 'day', new \DateTimeImmutable('2026-10-01'));
        $this->assertSame(['Over the month boundary'], array_map(fn ($item) => $item->getTitle(), $day['items']));

        $month = $this->calendar->build([$agenda, $other], 'month', new \DateTimeImmutable('2026-10-01'));
        $titles = array_map(fn ($item) => $item->getTitle(), $month['items']);
        $this->assertContains('Other agenda', $titles);
        $this->assertNotContains('Next month', $titles);
    }

    public function testItemEndingExactlyAtMidnightIsNotOnTheNextDay(): void
    {
        $agenda = $this->createAgenda();
        $this->createItem($agenda, 'Evening', '2026-10-08 20:00', '2026-10-08 23:59:59');

        $nextDay = $this->calendar->build([$agenda], 'day', new \DateTimeImmutable('2026-10-09'));

        $this->assertSame([], $nextDay['items']);
    }
}
