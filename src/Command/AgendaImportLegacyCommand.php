<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Agenda;
use App\Entity\AgendaItem;
use App\Repository\AgendaItemRepository;
use App\Repository\AgendaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Copies the agendas and agenda items of the old (Laminas) site. Safe to run again: agendas are matched
 * by title and items by their old id (uid "legacy-<id>"), so a second run updates instead of duplicating.
 */
#[AsCommand(
    name: 'app:agenda:import-legacy',
    description: 'Copy the agendas and items of the old site\'s database into the new agenda module',
)]
class AgendaImportLegacyCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgendaRepository $agendaRepository,
        private AgendaItemRepository $itemRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('source-db', null, InputOption::VALUE_REQUIRED, 'Database of the old site (on the same server)', 'verzeilberg')
            ->addOption('agenda', null, InputOption::VALUE_REQUIRED, 'Title of the (new or existing) agenda for the old items; they had no agenda', 'Imported')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be imported without saving anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sourceDb = (string) $input->getOption('source-db');
        if (!preg_match('/^[A-Za-z0-9_]+$/', $sourceDb)) {
            $io->error('Invalid database name.');

            return Command::FAILURE;
        }
        $connection = $this->em->getConnection();
        $dryRun = (bool) $input->getOption('dry-run');

        // One transaction: everything or nothing, and a dry run is simply rolled back
        $connection->beginTransaction();
        try {
            [$agendaCount, $itemCount, $corrected, $target] = $this->copy($connection, $sourceDb, (string) $input->getOption('agenda'));
            $this->em->flush();
            $dryRun ? $connection->rollBack() : $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            $io->error('Nothing has been copied: ' . $e->getMessage());

            return Command::FAILURE;
        }

        if ($dryRun) {
            $io->note('Dry run: nothing has been saved.');
        }
        $io->success(sprintf('%d agenda(s) and %d item(s) %s from "%s"; the items are in agenda "%s".', $agendaCount, $itemCount, $dryRun ? 'would be copied' : 'copied', $sourceDb, $target));
        if ($corrected !== []) {
            $io->warning(array_merge(['These items ended before they started (12-hour clock of the old iCal import); their end was set to their start. Please check them:'], $corrected));
        }

        return Command::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int, 2: list<string>, 3: string} agendas, items, corrected items, title of the item agenda
     */
    private function copy(\Doctrine\DBAL\Connection $connection, string $sourceDb, string $targetTitle): array
    {
        // Agendas: same title = same agenda; the old colour has no "#"
        $agendaCount = 0;
        foreach ($connection->fetchAllAssociative("SELECT id, title, color, deleted, date_created FROM `$sourceDb`.agenda ORDER BY id") as $row) {
            $title = trim((string) $row['title']) ?: 'Agenda ' . $row['id'];
            $agenda = $this->findOrCreateAgenda($title);
            if (preg_match('/^#?([0-9a-fA-F]{6})$/', (string) $row['color'], $match)) {
                $agenda->setColor('#' . $match[1]);
            }
            $agenda->setDeleted((bool) $row['deleted']);
            if ($row['date_created']) {
                $agenda->setDateCreated(new \DateTimeImmutable($row['date_created']));
            }
            ++$agendaCount;
        }

        // Items: the old module didn't link items to an agenda, so they all go into one agenda
        $target = $this->findOrCreateAgenda($targetTitle);
        $itemCount = 0;
        $corrected = [];
        foreach ($connection->fetchAllAssociative("SELECT * FROM `$sourceDb`.agenda_item WHERE deleted IS NULL OR deleted = 0 ORDER BY id") as $row) {
            $uid = 'legacy-' . $row['id'];
            $item = $this->itemRepository->findOneByUid($target, $uid) ?? (new AgendaItem())->setAgenda($target)->setUid($uid);
            $item->setTitle(mb_substr(trim((string) $row['name']), 0, 255) ?: '(no title)');
            $item->setDescription(trim((string) $row['description']) ?: null);

            $startDate = new \DateTimeImmutable((string) $row['start_date']);
            $endDate = $row['end_date'] ? new \DateTimeImmutable((string) $row['end_date']) : $startDate;
            if ((int) $row['whole_day'] === 1) {
                $item->setAllDayPeriod($startDate, max($startDate, $endDate));
            } else {
                $start = new \DateTimeImmutable($row['start_date'] . ' ' . ($row['start_time'] ?? '00:00:00'));
                $end = new \DateTimeImmutable(($row['end_date'] ?? $row['start_date']) . ' ' . ($row['end_time'] ?? $row['start_time'] ?? '00:00:00'));
                if ($end < $start) {
                    // The old iCal import used a 12-hour clock, so some items end before they start
                    $corrected[] = sprintf('#%d "%s": %s – %s', $row['id'], $item->getTitle(), $start->format('d-m-Y H:i'), $end->format('H:i'));
                    $end = $start;
                }
                $item->setAllDay(false);
                $item->setStartAt($start);
                $item->setEndAt($end);
            }
            if ($row['date_created']) {
                $item->setDateCreated(new \DateTimeImmutable($row['date_created']));
            }
            $this->em->persist($item);
            ++$itemCount;
        }

        return [$agendaCount, $itemCount, $corrected, $target->getTitle()];
    }

    private function findOrCreateAgenda(string $title): Agenda
    {
        $agenda = $this->agendaRepository->findOneBy(['title' => $title]);
        if (!$agenda) {
            $agenda = (new Agenda())->setTitle($title);
            $this->em->persist($agenda);
            // Make it findable for the next lookup in this run (inside the transaction)
            $this->em->flush();
        }

        return $agenda;
    }
}
