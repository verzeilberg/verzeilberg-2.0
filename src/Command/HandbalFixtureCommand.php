<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Handbal\HandbalSpeler;
use App\Entity\Handbal\HandbalKeeper;
use App\Entity\Handbal\HandbalRonde;
use App\Entity\Handbal\HandbalSeizoen;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:handbal:fixtures',
    description: 'Load handbal fixtures',
)]
class HandbalFixtureCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Check if seasons already exist to avoid duplicates
        $existingSeizoenen = $this->em->getRepository(HandbalSeizoen::class)->findBy([], ['startDatum' => 'DESC']);
        if (!empty($existingSeizoenen)) {
            $output->writeln('<info>Handbal fixtures already exist, skipping...</info>');
            return Command::SUCCESS;
        }

        $seizoen = new HandbalSeizoen();
        $seizoen->setNaam('Seizoen 2024-2025');
        $seizoen->setStartDatum(new \DateTime('2024-09-01'));
        $seizoen->setEindDatum(new \DateTime('2025-06-30'));
        $seizoen->setActief(true);
        $this->em->persist($seizoen);

        for ($i = 1; $i <= 11; $i++) {
            $speler = new HandbalSpeler();
            $speler->setNaam('Speler ' . $i);
            $speler->setRugnummer((string)$i);
            $speler->setActief(true);
            $this->em->persist($speler);
        }

        for ($i = 1; $i <= 2; $i++) {
            $keeper = new HandbalKeeper();
            $keeper->setNaam('Keeper ' . $i);
            $keeper->setRugnummer((string)(11 + $i));
            $keeper->setActief(true);
            $this->em->persist($keeper);
        }

        for ($i = 1; $i <= 5; $i++) {
            $ronde = new HandbalRonde();
            $ronde->setSeizoen($seizoen);
            $ronde->setDatum(new \DateTime('2024-09-' . ($i * 5 + 5)));
            $ronde->setOmschrijving('Speeldag ' . $i);
            $ronde->setActief(true);
            $this->em->persist($ronde);
        }

        $this->em->flush();
        $output->writeln('<info>Handbal fixtures loaded successfully!</info>');
        
        return Command::SUCCESS;
    }
}