<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Steam\SteamApiException;
use App\Service\Steam\SteamLibrarySync;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * For a cronjob, e.g. every hour:
 *   0 * * * * php /path/to/bin/console app:steam:sync --quiet
 * The first run of a large library reads the store details in parts; later runs only do what changed.
 */
#[AsCommand(
    name: 'app:steam:sync',
    description: 'Update the Steam game library: playtime, store details and achievements',
)]
class SteamSyncCommand extends Command
{
    public function __construct(
        private SteamLibrarySync $sync,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('details', null, InputOption::VALUE_REQUIRED, 'Maximum number of games to read store details for (the store limits requests)', '40')
            ->addOption('achievements', null, InputOption::VALUE_REQUIRED, 'Maximum number of games to read achievements for', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$this->sync->isConfigured()) {
            $io->warning('Steam is not configured: set STEAM_API_KEY and STEAM_ID.');

            return Command::SUCCESS;
        }

        try {
            $result = $this->sync->sync((int) $input->getOption('details'), (int) $input->getOption('achievements'));
        } catch (SteamApiException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success($result->summary());

        return Command::SUCCESS;
    }
}
