<?php

declare(strict_types=1);

namespace App\Tests\Checklist;

use App\DataFixtures\AppFixtures;
use App\DataFixtures\ChecklistFixtures;
use App\Entity\CheckList;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** The "checklists" fixture group loads on its own and gives every kind of checklist access */
class ChecklistFixturesTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollBack();
        }
        parent::tearDown();
    }

    public function testGroupContainsOnlyTheChecklistFixturesAndRunsAfterAppFixtures(): void
    {
        $loader = static::getContainer()->get('doctrine.fixtures.loader');
        self::assertInstanceOf(SymfonyFixturesLoader::class, $loader);

        $this->assertSame([ChecklistFixtures::class], array_map(fn ($fixture) => $fixture::class, array_values($loader->getFixtures(['checklists']))));

        $all = array_map(fn ($fixture) => $fixture::class, array_values($loader->getFixtures()));
        $this->assertGreaterThan(array_search(AppFixtures::class, $all, true), array_search(ChecklistFixtures::class, $all, true));
    }

    public function testLoadsUsersAndChecklistsWithEveryAccess(): void
    {
        static::getContainer()->get(ChecklistFixtures::class)->load($this->em);
        $this->em->clear();

        $users = $this->em->getRepository(User::class);
        $anna = $users->findOneBy(['email' => 'anna@example.com']);
        self::assertNotNull($anna);
        $this->assertTrue($anna->hasPermission('checklist.own'));
        $this->assertSame(User::STATUS_PENDING, $users->findOneBy(['email' => 'eva@example.com'])?->getStatus());

        $lists = $this->em->getRepository(CheckList::class)->findBy(['name' => [
            'Enquête: favoriete seizoen', 'Aanmelden clubdag', 'Anna: films om te kijken', 'Anna: weekendje weg (vrienden)',
            'Anna: privé cadeaulijst', 'Anna: vakantie 2025 (gearchiveerd)', 'Bram: leesclub', 'Bram: klusdag (vrienden)',
        ]]);
        $this->assertCount(8, $lists);
        $access = array_unique(array_map(fn (CheckList $list) => $list->getAccess(), $lists));
        sort($access);
        $this->assertSame([CheckList::ACCESS_ADMIN, CheckList::ACCESS_FRIENDS, CheckList::ACCESS_PUBLIC, CheckList::ACCESS_USER], $access);
        $this->assertCount(1, array_filter($lists, fn (CheckList $list) => $list->isDeleted()), 'one archived list');

        $movies = $this->em->getRepository(CheckList::class)->findOneBy(['name' => 'Anna: films om te kijken']);
        $this->assertCount(3, $movies->getCheckListItems());
        $this->assertGreaterThan(0, $movies->getAnswersGiven()->count(), 'answers derived from the item content');
    }
}
