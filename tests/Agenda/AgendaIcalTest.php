<?php

declare(strict_types=1);

namespace App\Tests\Agenda;

use App\Entity\AgendaItem;
use App\Service\AgendaIcal;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AgendaIcalTest extends KernelTestCase
{
    use AgendaTestTrait;

    private AgendaIcal $ical;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->beginAgendaTransaction();
        $this->ical = static::getContainer()->get(AgendaIcal::class);
    }

    protected function tearDown(): void
    {
        $this->rollBackAgendaTransaction();
        parent::tearDown();
    }

    public function testImportWholeDayTimedAndRecurringEvents(): void
    {
        $agenda = $this->createAgenda();
        $year = (int) date('Y');

        $result = $this->ical->import($agenda, $this->calendar([
            // Whole days 10-12 (DTEND is exclusive)
            "BEGIN:VEVENT\r\nUID:camp@test\r\nSUMMARY:Kamp\r\nDTSTART;VALUE=DATE:{$year}1010\r\nDTEND;VALUE=DATE:{$year}1013\r\nEND:VEVENT",
            // 15:00 UTC = 17:00 in Amsterdam in summer, 16:00 in winter
            "BEGIN:VEVENT\r\nUID:match@test\r\nSUMMARY:Wedstrijd\r\nLOCATION:Sporthal\r\nDTSTART:{$year}0115T150000Z\r\nDTEND:{$year}0115T163000Z\r\nEND:VEVENT",
            // Weekly, 3 times
            "BEGIN:VEVENT\r\nUID:training@test\r\nSUMMARY:Training\r\nDTSTART;TZID=Europe/Amsterdam:{$year}0302T190000\r\nDTEND;TZID=Europe/Amsterdam:{$year}0302T203000\r\nRRULE:FREQ=WEEKLY;COUNT=3\r\nEND:VEVENT",
        ]));

        $this->assertSame(['created' => 5, 'updated' => 0, 'skipped' => 0], $result);
        $items = $this->items($agenda->getId());

        $camp = $items['Kamp'][0];
        $this->assertTrue($camp->isAllDay());
        $this->assertSame("{$year}-10-10 00:00 / {$year}-10-12 23:59", $camp->getStartAt()->format('Y-m-d H:i') . ' / ' . $camp->getEndAt()->format('Y-m-d H:i'));

        $match = $items['Wedstrijd'][0];
        $this->assertSame('Sporthal', $match->getLocation());
        $this->assertSame('16:00-17:30', $match->getStartAt()->format('H:i') . '-' . $match->getEndAt()->format('H:i'), 'converted to Europe/Amsterdam (winter time)');

        $this->assertCount(3, $items['Training']);
        $this->assertSame(["{$year}-03-02 19:00", "{$year}-03-09 19:00", "{$year}-03-16 19:00"], array_map(fn (AgendaItem $item) => $item->getStartAt()->format('Y-m-d H:i'), $items['Training']));
    }

    public function testImportingAgainUpdatesInsteadOfDuplicating(): void
    {
        $agenda = $this->createAgenda();
        $event = fn (string $title) => $this->calendar(["BEGIN:VEVENT\r\nUID:same@test\r\nSUMMARY:$title\r\nDTSTART:20300101T100000Z\r\nDTEND:20300101T110000Z\r\nEND:VEVENT"]);

        $this->ical->import($agenda, $event('Old title'));
        $result = $this->ical->import($agenda, $event('New title'));

        $this->assertSame(['created' => 0, 'updated' => 1, 'skipped' => 0], $result);
        $this->assertSame(['New title'], array_keys($this->items($agenda->getId())));
    }

    public function testInvalidCalendarIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->ical->import($this->createAgenda(), 'this is not a calendar');
    }

    public function testOnlyHttpAndWebcalLinksCanBeDownloaded(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only http(s) and webcal links');
        $this->ical->download('file:///etc/passwd');
    }

    public function testExportUsesDateValuesForWholeDaysAndUtcForTimes(): void
    {
        $agenda = $this->createAgenda('Export');
        $this->createItem($agenda, 'Kamp', '2030-10-10', '2030-10-12', allDay: true);
        $this->createItem($agenda, 'Wedstrijd', '2030-01-15 16:00', '2030-01-15 17:30');
        $this->em->clear();
        $agenda = $this->em->find(\App\Entity\Agenda::class, $agenda->getId());

        $ics = $this->ical->export($agenda, 'example.com');

        $this->assertStringContainsString('X-WR-CALNAME:' . $agenda->getTitle(), $ics);
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20301010', $ics);
        $this->assertStringContainsString('DTEND;VALUE=DATE:20301013', $ics, 'iCal end date is exclusive');
        $this->assertStringContainsString('DTSTART:20300115T150000Z', $ics, '16:00 Amsterdam (winter) = 15:00 UTC');
        $this->assertSame(2, substr_count($ics, 'BEGIN:VEVENT'));

        // And it can be imported again
        $copy = $this->createAgenda('Copy');
        $this->assertSame(['created' => 2, 'updated' => 0, 'skipped' => 0], $this->ical->import($copy, $ics));
    }

    /** @param list<string> $events */
    private function calendar(array $events): string
    {
        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Test//EN\r\n" . implode("\r\n", $events) . "\r\nEND:VCALENDAR\r\n";
    }

    /** @return array<string, list<AgendaItem>> by title */
    private function items(?int $agendaId): array
    {
        $items = [];
        foreach ($this->em->getRepository(AgendaItem::class)->findBy(['agenda' => $agendaId], ['startAt' => 'ASC']) as $item) {
            $items[$item->getTitle()][] = $item;
        }

        return $items;
    }
}
