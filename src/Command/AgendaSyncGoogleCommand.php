<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Google\GoogleCalendarSync;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * For a cronjob, e.g. every 15 minutes:
 *   * /15 * * * * php /path/to/bin/console app:agenda:sync-google --quiet
 */
#[AsCommand(
    name: 'app:agenda:sync-google',
    description: 'Update the agendas that are linked to a Google calendar',
)]
class AgendaSyncGoogleCommand extends Command
{
    public function __construct(
        private GoogleCalendarSync $sync,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $failed = false;

        foreach ($this->sync->syncAll() as $title => $result) {
            if (is_string($result)) {
                $failed = true;
                $io->error(sprintf('%s: %s', $title, $result));
            } else {
                $io->writeln(sprintf('%s: %d new, %d changed, %d removed', $title, $result['created'], $result['updated'], $result['deleted']));
            }
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
