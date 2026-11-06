<?php

declare(strict_types=1);

namespace App\Tests\Handbal;

use App\Entity\Handbal\HandbalKeeper;
use App\Entity\Handbal\HandbalRonde;
use App\Entity\Handbal\HandbalScore;
use App\Entity\Handbal\HandbalSeizoen;
use App\Entity\Handbal\HandbalSpeler;
use App\Entity\Role;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** The password-protected public handbal page, its standings, the seasons and who may open the management pages */
class HandbalTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Connection $connection;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->connection = $this->em->getConnection();
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        parent::tearDown();
    }

    public function testPublicPageNeedsThePassword(): void
    {
        $this->client->request('GET', '/handbal/');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="password"]');

        $this->client->request('POST', '/handbal/', ['password' => 'wrong']);
        self::assertSelectorExists('input[name="password"]');

        // HANDBAL_PUBLIC_PASSWORD in .env.test
        $this->client->request('POST', '/handbal/', ['password' => 'test-handbal']);
        self::assertSelectorNotExists('input[name="password"]');

        $this->client->request('GET', '/handbal/logout');
        $this->client->followRedirect();
        self::assertSelectorExists('input[name="password"]');
    }

    public function testStandingsCountGoalsPerRoundAndSavesPerPossibleShot(): void
    {
        $seizoen = (new HandbalSeizoen())->setNaam('Test seizoen')
            ->setStartDatum(new \DateTime('-30 days'))
            ->setEindDatum(new \DateTime('+30 days'));
        $this->em->persist($seizoen);
        $anna = $this->speler('Anna');
        $bob = $this->speler('Bob');
        $kees = (new HandbalKeeper())->setNaam('Kees');
        $this->em->persist($kees);

        $r1 = $this->ronde($seizoen, '-14 days');
        $r2 = $this->ronde($seizoen, '-7 days');
        $this->score($anna, $kees, $r1, true);
        $this->score($bob, $kees, $r1, false); // a save for Kees
        $this->score($anna, $kees, $r2, true);
        $this->score($bob, $kees, $r2, true);
        $this->em->flush();

        $crawler = $this->client->request('POST', '/handbal/', ['password' => 'test-handbal']);
        self::assertResponseIsSuccessful();

        $rows = $crawler->filter('table')->first()->filter('tbody tr')->each(
            fn ($row) => array_map(fn (string $text) => trim((string) preg_replace('/\s+/', ' ', $text)), $row->filter('td')->each(fn ($cell) => $cell->text()))
        );
        $this->assertSame([
            ['1', 'Anna', '2', '100%'],  // 2 goals in 2 rounds
            ['2', 'Bob', '1', '50%'],
            ['3', 'Kees Keeper', '1', '25%'], // 1 save out of 2 rounds x 2 players
        ], $rows);
    }

    public function testOverlappingSeasonsAreRejected(): void
    {
        $existing = (new HandbalSeizoen())->setNaam('2090 voorjaar')->setStartDatum('2090-01-01')->setEindDatum('2090-06-30');
        $this->em->persist($existing);
        $this->em->flush();
        $validator = static::getContainer()->get(ValidatorInterface::class);

        $overlapping = (new HandbalSeizoen())->setNaam('Overlap')->setStartDatum('2090-06-01')->setEindDatum('2090-12-31');
        $violations = $validator->validate($overlapping);
        $this->assertCount(1, $violations);
        $this->assertSame('startDatum', $violations[0]->getPropertyPath());
        $this->assertSame('Er bestaat al een seizoen dat overlapt met deze data.', $violations[0]->getMessage());

        $next = (new HandbalSeizoen())->setNaam('2090 najaar')->setStartDatum('2090-07-01')->setEindDatum('2090-12-31');
        $this->assertCount(0, $validator->validate($next), 'a season starting the day after is fine');

        $existing->setEindDatum('2090-07-15');
        $this->assertCount(0, $validator->validate($existing), 'a season does not overlap with itself');
    }

    public function testManagementIsOnlyForAdmins(): void
    {
        $this->client->request('GET', '/beheer/handbal/');
        self::assertResponseRedirects('/login');

        $this->client->loginUser($this->user('ROLE_USER'), 'primary_auth');
        $this->client->request('GET', '/beheer/handbal/');
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser($this->user('ROLE_ADMIN'), 'primary_auth');
        $this->client->request('GET', '/beheer/handbal/');
        self::assertResponseIsSuccessful();
    }

    private function speler(string $naam): HandbalSpeler
    {
        $speler = (new HandbalSpeler())->setNaam($naam);
        $this->em->persist($speler);

        return $speler;
    }

    private function ronde(HandbalSeizoen $seizoen, string $datum): HandbalRonde
    {
        $ronde = (new HandbalRonde())->setSeizoen($seizoen)->setDatum(new \DateTime($datum));
        $seizoen->addRonde($ronde);
        $this->em->persist($ronde);

        return $ronde;
    }

    private function score(HandbalSpeler $speler, HandbalKeeper $keeper, HandbalRonde $ronde, bool $gescored): void
    {
        $this->em->persist((new HandbalScore())->setSpeler($speler)->setKeeper($keeper)->setRonde($ronde)->setGescored($gescored));
    }

    private function user(string $roleName): User
    {
        $role = $this->em->getRepository(Role::class)->findOneBy(['name' => $roleName])
            ?? (new Role())->setName($roleName)->setDescription($roleName);
        $this->em->persist($role);
        $user = (new User())->setEmail(uniqid('handbal-') . '@example.com')->setFullName('Test')->setPassword('not-used');
        $user->addRole($role);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
