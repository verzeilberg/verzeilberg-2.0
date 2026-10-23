<?php

declare(strict_types=1);

namespace App\Tests\Checklist;

use App\Entity\CheckList;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * A checklist's item lists follow its own default order (e.g. the order the items were added, as on the old site,
 * or name and then year), until a column is clicked.
 */
class ChecklistDefaultOrderTest extends WebTestCase
{
    use ChecklistTestTrait;

    protected function setUp(): void
    {
        $this->setUpChecklistTest();
    }

    protected function tearDown(): void
    {
        $this->tearDownChecklistTest();
        parent::tearDown();
    }

    public function testListsFollowTheDefaultOrderUntilAColumnIsClicked(): void
    {
        $checkList = $this->createCheckList('Films');
        $name = $this->createField($checkList, 'Naam', 'text');
        $name->setShowInOverview(true);
        // Added in this order, but with creation dates the other way round (as after the import from the old site)
        $first = $this->createItem($checkList, ['field_naam' => 'Batman']);
        $second = $this->createItem($checkList, ['field_naam' => 'Alien']);
        $first->setDateCreated(new \DateTimeImmutable('2024-04-30 12:00:05'));
        $second->setDateCreated(new \DateTimeImmutable('2024-04-30 12:00:01'));
        $this->em->flush();
        $this->loginAs($this->createUser('ROLE_ADMIN'));

        $this->assertSame([$second->getId(), $first->getId()], $this->listedIds($checkList), 'without a setting: on creation date');

        $checkList->setDefaultOrder([['by' => 'id', 'dir' => 'asc']]);
        $this->em->flush();
        $this->assertSame([$first->getId(), $second->getId()], $this->listedIds($checkList), 'in the order they were added');

        $checkList->setDefaultOrder([['by' => (string) $name->getId(), 'dir' => 'desc']]);
        $this->em->flush();
        $this->assertSame([$first->getId(), $second->getId()], $this->listedIds($checkList), 'on a field, Z to A');

        $this->assertSame([$second->getId(), $first->getId()], $this->listedIds($checkList, ['order_by' => (string) $name->getId(), 'order_dir' => 'asc']), 'a clicked column wins');
    }

    public function testSecondLevelDecidesWhenTheFirstIsEqual(): void
    {
        $checkList = $this->createCheckList('Films');
        $name = $this->createField($checkList, 'Naam', 'text');
        $year = $this->createField($checkList, 'Jaar', 'number');
        $remake = $this->createItem($checkList, ['field_naam' => 'Planet of the Apes', 'field_jaar' => '2001']);
        $alien = $this->createItem($checkList, ['field_naam' => 'Alien', 'field_jaar' => '1979']);
        $original = $this->createItem($checkList, ['field_naam' => 'Planet of the Apes', 'field_jaar' => '1968']);
        $checkList->setDefaultOrder([['by' => (string) $name->getId(), 'dir' => 'asc'], ['by' => (string) $year->getId(), 'dir' => 'asc']]);
        $this->em->flush();
        $this->loginAs($this->createUser('ROLE_ADMIN'));

        $this->assertSame([$alien->getId(), $original->getId(), $remake->getId()], $this->listedIds($checkList), 'name, then year');

        // Clicking the name column (Z to A) keeps the year as the next level
        $this->assertSame([$original->getId(), $remake->getId(), $alien->getId()], $this->listedIds($checkList, ['order_by' => (string) $name->getId(), 'order_dir' => 'desc']));
    }

    public function testSetDefaultOrderDropsEmptyAndRepeatedLevels(): void
    {
        $checkList = (new CheckList())->setDefaultOrder([['by' => '12', 'dir' => 'desc'], ['by' => '', 'dir' => 'asc'], ['by' => '12', 'dir' => 'asc'], ['by' => 'id', 'dir' => 'x']]);

        $this->assertSame([['by' => '12', 'dir' => 'desc'], ['by' => 'id', 'dir' => 'asc']], $checkList->getDefaultOrder());
        $this->assertSame([['by' => 'created', 'dir' => 'asc']], (new CheckList())->getDefaultOrder(), 'nothing set: creation date');
    }

    public function testDefaultOrderIsSetInTheChecklistForm(): void
    {
        $checkList = $this->createCheckList('Films');
        $name = $this->createField($checkList, 'Naam', 'text');
        $year = $this->createField($checkList, 'Jaar', 'number');
        // As the site loads it: with its fields
        $this->em->refresh($checkList);
        $this->loginAs($this->createUser('ROLE_ADMIN'));

        $crawler = $this->client->request('GET', '/admin/checklist/' . $checkList->getId() . '/edit');
        $this->assertContains('Veld: Jaar', $crawler->filter('#check_list_orderBy2 option')->each(fn ($option) => $option->text()));
        $this->client->submit($crawler->selectButton('Wijzigingen opslaan')->form(), [
            'check_list[orderBy1]' => (string) $name->getId(),
            'check_list[orderDir1]' => 'asc',
            'check_list[orderBy2]' => (string) $year->getId(),
            'check_list[orderDir2]' => 'desc',
            'check_list[orderBy3]' => '',
        ]);

        $this->assertResponseRedirects();
        $stored = json_decode((string) $this->connection->fetchOne('SELECT default_order FROM checklists WHERE id = ?', [$checkList->getId()]), true);
        $this->assertSame([['by' => (string) $name->getId(), 'dir' => 'asc'], ['by' => (string) $year->getId(), 'dir' => 'desc']], $stored);

        // The form shows what is stored
        $crawler = $this->client->request('GET', '/admin/checklist/' . $checkList->getId() . '/edit');
        $this->assertSame((string) $year->getId(), $crawler->filter('#check_list_orderBy2 option[selected]')->attr('value'));
    }

    public function testFirstDropdownStartsEmptyAndEmptyMeansCreationDate(): void
    {
        $checkList = $this->createCheckList('Films');
        $this->createField($checkList, 'Naam', 'text');
        $this->em->refresh($checkList);
        $this->loginAs($this->createUser('ROLE_ADMIN'));

        $crawler = $this->client->request('GET', '/admin/checklist/' . $checkList->getId() . '/edit');
        $this->assertSame('', $crawler->filter('#check_list_orderBy1 option[selected]')->count() ? $crawler->filter('#check_list_orderBy1 option[selected]')->attr('value') : '', 'nothing chosen yet');

        $this->client->submit($crawler->selectButton('Wijzigingen opslaan')->form(), ['check_list[orderBy1]' => '']);

        $this->assertNull($this->connection->fetchOne('SELECT default_order FROM checklists WHERE id = ?', [$checkList->getId()]));
        $this->assertSame([['by' => 'created', 'dir' => 'asc']], $this->em->find(CheckList::class, $checkList->getId())->getDefaultOrder());
    }

    public function testThereIsALevelForEveryChoiceAndAllCanBeStored(): void
    {
        $checkList = $this->createCheckList('Films');
        $fields = [];
        foreach (['Naam', 'Jaar', 'Cijfer', 'Regisseur'] as $label) {
            $fields[] = $this->createField($checkList, $label, 'text');
        }
        $this->em->refresh($checkList);
        $this->loginAs($this->createUser('ROLE_ADMIN'));

        $crawler = $this->client->request('GET', '/admin/checklist/' . $checkList->getId() . '/edit');
        // 3 fixed choices (creation date, order added, created by) + 4 fields
        $this->assertCount(7, $crawler->filter('.js-order-levels .js-order-level'));

        $values = [];
        foreach ([$fields[3], $fields[1], $fields[0], $fields[2]] as $i => $field) {
            $values['check_list[orderBy' . ($i + 1) . ']'] = (string) $field->getId();
            $values['check_list[orderDir' . ($i + 1) . ']'] = $i === 1 ? 'desc' : 'asc';
        }
        $values['check_list[orderBy5]'] = 'id';
        $this->client->submit($crawler->selectButton('Wijzigingen opslaan')->form(), $values);

        $stored = json_decode((string) $this->connection->fetchOne('SELECT default_order FROM checklists WHERE id = ?', [$checkList->getId()]), true);
        $this->assertSame([
            ['by' => (string) $fields[3]->getId(), 'dir' => 'asc'],
            ['by' => (string) $fields[1]->getId(), 'dir' => 'desc'],
            ['by' => (string) $fields[0]->getId(), 'dir' => 'asc'],
            ['by' => (string) $fields[2]->getId(), 'dir' => 'asc'],
            ['by' => 'id', 'dir' => 'asc'],
        ], $stored, 'five levels, more than the old maximum of three');
    }

    /**
     * @param array<string, string> $query
     *
     * @return list<int> the item ids of the answers list, top to bottom
     */
    private function listedIds(CheckList $checkList, array $query = []): array
    {
        $crawler = $this->client->request('GET', '/admin/checklist/' . $checkList->getId() . ($query ? '?' . http_build_query($query) : ''));
        $this->assertResponseIsSuccessful();

        return $crawler->filter('tr.js-row-link td:first-child a')->each(fn ($link) => (int) ltrim(trim($link->text()), '#'));
    }
}
