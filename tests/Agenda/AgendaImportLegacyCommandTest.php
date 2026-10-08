<?php

declare(strict_types=1);

namespace App\Tests\Agenda;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Copies data from a temporary database with the tables of the old (Laminas) agenda module.
 */
class AgendaImportLegacyCommandTest extends KernelTestCase
{
    use AgendaTestTrait;

    private const SOURCE_DB = 'verzeilberg_legacy_test';

    private CommandTester $command;

    public static function setUpBeforeClass(): void
    {
        self::bootKernel();
        $connection = static::getContainer()->get('doctrine')->getConnection();
        try {
            $connection->executeStatement('CREATE DATABASE IF NOT EXISTS ' . self::SOURCE_DB);
        } catch (\Throwable $e) {
            self::markTestSkipped('No permission to create a temporary database: ' . $e->getMessage());
        }
        $db = self::SOURCE_DB;
        $connection->executeStatement("DROP TABLE IF EXISTS $db.agenda_item, $db.agenda");
        $connection->executeStatement("CREATE TABLE $db.agenda (id INT PRIMARY KEY, created_by INT NULL, changed_by INT NULL, deleted_by INT NULL, title VARCHAR(255), date_created DATETIME NULL, date_changed DATETIME NULL, date_deleted DATETIME NULL, deleted INT NULL, color VARCHAR(6))");
        $connection->executeStatement("CREATE TABLE $db.agenda_item (id INT PRIMARY KEY, created_by INT NULL, changed_by INT NULL, deleted_by INT NULL, name VARCHAR(255), description VARCHAR(255) NULL, start_date DATE, end_date DATE, start_time TIME NULL, end_time TIME, whole_day INT NULL, date_created DATETIME NULL, date_changed DATETIME NULL, date_deleted DATETIME NULL, deleted INT NULL)");
        $connection->executeStatement("INSERT INTO $db.agenda (id, title, date_created, deleted, color) VALUES (1, 'Legacy club', '2024-09-20 14:35:36', 0, '00ff1e'), (2, 'Legacy old', '2024-09-20 15:13:09', 1, 'e20303')");
        $connection->executeStatement("INSERT INTO $db.agenda_item (id, name, description, start_date, end_date, start_time, end_time, whole_day, deleted) VALUES
            (1, 'Kamp', 'Drie dagen', '2020-01-19', '2020-01-22', '16:32:20', '16:32:20', 1, NULL),
            (2, 'Dokter', NULL, '2023-09-19', '2023-09-19', '08:15:00', '09:00:00', 0, NULL),
            (3, 'Wedstrijd', 'Poule', '2024-09-21', '2024-09-21', '11:00:00', '01:00:00', NULL, NULL),
            (4, 'Verwijderd', NULL, '2024-01-01', '2024-01-01', '10:00:00', '11:00:00', 0, 1)");
        self::ensureKernelShutdown();
    }

    public static function tearDownAfterClass(): void
    {
        self::bootKernel();
        static::getContainer()->get('doctrine')->getConnection()->executeStatement('DROP DATABASE IF EXISTS ' . self::SOURCE_DB);
        self::ensureKernelShutdown();
    }

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->beginAgendaTransaction();
        $this->command = new CommandTester((new Application($kernel))->find('app:agenda:import-legacy'));
    }

    protected function tearDown(): void
    {
        $this->rollBackAgendaTransaction();
        parent::tearDown();
    }

    public function testCopiesAgendasAndItems(): void
    {
        $target = 'Legacy items ' . uniqid();
        $this->command->execute(['--source-db' => self::SOURCE_DB, '--agenda' => $target]);

        $this->command->assertCommandIsSuccessful();
        $display = preg_replace('/\s+/', ' ', $this->command->getDisplay());
        $this->assertStringContainsString('2 agenda(s) and 3 item(s) copied', $display);
        $this->assertStringContainsString('#3 "Wedstrijd": 21-09-2024 11:00 – 01:00', $display, 'the 12-hour clock problem is reported');

        $agendas = $this->connection->fetchAllKeyValue("SELECT title, CONCAT(color, '/', deleted) FROM agenda WHERE title LIKE 'Legacy %'");
        $this->assertSame('#00ff1e/0', $agendas['Legacy club']);
        $this->assertSame('#e20303/1', $agendas['Legacy old'], 'archived agenda stays archived');

        $items = $this->connection->fetchAllAssociativeIndexed('SELECT i.title, i.start_at, i.end_at, i.all_day, i.uid FROM agenda_item i JOIN agenda a ON a.id = i.agenda_id WHERE a.title = ? ORDER BY i.start_at', [$target]);
        $this->assertSame(['Kamp', 'Dokter', 'Wedstrijd'], array_keys($items), 'the deleted item is not copied');
        $this->assertSame(['2020-01-19 00:00:00', '2020-01-22 23:59:59', 1], [$items['Kamp']['start_at'], $items['Kamp']['end_at'], (int) $items['Kamp']['all_day']]);
        $this->assertSame(['2023-09-19 08:15:00', '2023-09-19 09:00:00'], [$items['Dokter']['start_at'], $items['Dokter']['end_at']]);
        $this->assertSame($items['Wedstrijd']['start_at'], $items['Wedstrijd']['end_at'], 'an end before the start is set to the start');
        $this->assertSame('legacy-1', $items['Kamp']['uid']);
    }

    public function testRunningTwiceDoesNotDuplicate(): void
    {
        $target = 'Legacy items ' . uniqid();
        $this->command->execute(['--source-db' => self::SOURCE_DB, '--agenda' => $target]);
        $this->command->execute(['--source-db' => self::SOURCE_DB, '--agenda' => $target]);

        $this->assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM agenda_item i JOIN agenda a ON a.id = i.agenda_id WHERE a.title = ?', [$target]));
        $this->assertSame(1, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM agenda WHERE title = 'Legacy club'"));
    }

    public function testDryRunSavesNothing(): void
    {
        $before = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM agenda');

        $this->command->execute(['--source-db' => self::SOURCE_DB, '--agenda' => 'Legacy dry ' . uniqid(), '--dry-run' => true]);

        $this->command->assertCommandIsSuccessful();
        $this->assertStringContainsString('would be copied', $this->command->getDisplay());
        $this->assertSame($before, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM agenda'));
    }
}
