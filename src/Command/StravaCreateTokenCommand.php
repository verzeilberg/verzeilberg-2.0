<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Strava\StravaOAuthService;
use App\Service\Strava\StravaAccessTokenService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:strava:create-token',
    description: 'Create Strava access token from authorization code',
)]
class StravaCreateTokenCommand extends Command
{
    public function __construct(
        private StravaOAuthService $oauthService,
        private StravaAccessTokenService $accessTokenService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('code', InputArgument::REQUIRED, 'Authorization code from Strava');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $code = $input->getArgument('code');
        
        try {
            $tokenData = $this->oauthService->exchangeToken($code);
            $accessToken = $this->accessTokenService->createAccessToken($tokenData);
            
            $output->writeln('<info>Access token created successfully!</info>');
            $output->writeln('Token ID: ' . $accessToken->getId());
            $output->writeln('Expires at: ' . $accessToken->getExpiresAt()->format('Y-m-d H:i:s'));
            $output->writeln('Access token: ' . substr($accessToken->getAccessToken(), 0, 20) . '...');
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}