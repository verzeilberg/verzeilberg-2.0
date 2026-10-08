<?php

declare(strict_types=1);

namespace App\DataFixtures\Handbal;

use App\Entity\Handbal\HandbalSpeler;
use App\Entity\Handbal\HandbalKeeper;
use App\Entity\Handbal\HandbalRonde;
use App\Entity\Handbal\HandbalSeizoen;
use App\Entity\Handbal\HandbalScore;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class HandbalFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Check if seasons already exist to avoid duplicates
        $existingSeizoenen = $manager->getRepository(HandbalSeizoen::class)->findBy([], ['startDatum' => 'DESC']);
        if (!empty($existingSeizoenen)) {
            return;
        }

        // Create season 2024-2025
        $seizoen = new HandbalSeizoen();
        $seizoen->setNaam('Seizoen 2024-2025');
        $seizoen->setStartDatum(new \DateTime('2024-09-01'));
        $seizoen->setEindDatum(new \DateTime('2025-06-30'));
        $seizoen->setActief(true);
        $manager->persist($seizoen);

        // Create season 2025-2026 (current)
        $seizoen2 = new HandbalSeizoen();
        $seizoen2->setNaam('Seizoen 2025-2026');
        $seizoen2->setStartDatum(new \DateTime('2025-09-01'));
        $seizoen2->setEindDatum(new \DateTime('2026-06-30'));
        $seizoen2->setActief(true);
        $manager->persist($seizoen2);

        // Create 11 field players
        $spelers = [];
        for ($i = 1; $i <= 11; $i++) {
            $speler = new HandbalSpeler();
            $speler->setNaam('Speler ' . $i);
            $speler->setRugnummer((string)$i);
            $speler->setActief(true);
            $manager->persist($speler);
            $spelers[] = $speler;
        }

        // Create 1 keeper
        $keepers = [];
        for ($i = 1; $i <= 1; $i++) {
            $keeper = new HandbalKeeper();
            $keeper->setNaam('Keeper ' . $i);
            $keeper->setRugnummer((string)(11 + $i));
            $keeper->setActief(true);
            $manager->persist($keeper);
            $keepers[] = $keeper;
        }

        // Create 5 rounds for season 2024-2025
        $rondes = [];
        for ($i = 1; $i <= 5; $i++) {
            $ronde = new HandbalRonde();
            $ronde->setSeizoen($seizoen);
            $ronde->setDatum(new \DateTime('2024-09-' . ($i * 5 + 5)));
            $ronde->setOmschrijving('Speeldag ' . $i);
            $ronde->setActief(true);
            $manager->persist($ronde);
            $rondes[] = $ronde;
        }

        // Create 5 rounds for season 2025-2026
        $rondes2 = [];
        for ($i = 1; $i <= 5; $i++) {
            $ronde = new HandbalRonde();
            $ronde->setSeizoen($seizoen2);
            $ronde->setDatum(new \DateTime('2025-09-' . ($i * 5 + 5)));
            $ronde->setOmschrijving('Speeldag ' . $i);
            $ronde->setActief(true);
            $manager->persist($ronde);
            $rondes2[] = $ronde;
        }

        $manager->flush();

        // Generate random scores for both seasons
        $this->generateScores($manager, $spelers, $keepers, $rondes);
        $this->generateScores($manager, $spelers, $keepers, $rondes2);
    }

    private function generateScores(ObjectManager $manager, array $spelers, array $keepers, array $rondes): void
    {
        // One keeper defends the whole season
        $keeper = $keepers[array_rand($keepers)];

        foreach ($rondes as $ronde) {
            foreach ($spelers as $speler) {
                // ~70% chance of scoring
                $gescored = (random_int(1, 10) <= 7);
                
                $score = new HandbalScore();
                $score->setSpeler($speler);
                $score->setKeeper($keeper);
                $score->setRonde($ronde);
                $score->setGescored($gescored);
                $manager->persist($score);
            }
        }
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [];
    }
}