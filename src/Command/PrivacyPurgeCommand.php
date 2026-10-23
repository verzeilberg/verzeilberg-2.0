<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\DataRetention;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The site runs this once a day by itself (DataRetentionListener); a cronjob isn't needed, but can be used:
 *   0 3 * * * php /path/to/bin/console app:privacy:purge --quiet
 */
#[AsCommand(
    name: 'app:privacy:purge',
    description: 'Delete contact messages and local copies of emails older than six months, and registrations nobody approved within 30 days',
)]
class PrivacyPurgeCommand extends Command
{
    public function __construct(
        private DataRetention $retention,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $deleted = $this->retention->purge();

        (new SymfonyStyle($input, $output))->success(sprintf(
            'Deleted %d contact message(s), %d email(s) and %d attachment file(s) older than six months, and %d registration(s) nobody approved.',
            $deleted['contacts'],
            $deleted['emails'],
            $deleted['attachments'],
            $deleted['registrations'],
        ));

        return Command::SUCCESS;
    }
}
