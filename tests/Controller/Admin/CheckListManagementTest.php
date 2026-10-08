<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\CheckList;
use App\Entity\CheckListField;
use App\Service\CheckListService;
use App\Tests\Checklist\ChecklistTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Managing checklists and fields in the admin: field names, field order, deleting, duplicating,
 * the answers list (search/filter/sort) and the export.
 */
class CheckListManagementTest extends WebTestCase
{
    use ChecklistTestTrait;

    private CheckList $checkList;

    protected function setUp(): void
    {
        $this->setUpChecklistTest();
        $this->loginAs($this->createUser('ROLE_ADMIN'));
        $this->checkList = $this->createCheckList();
    }

    protected function tearDown(): void
    {
        $this->tearDownChecklistTest();
        parent::tearDown();
    }

    public function testFieldsWithTheSameNameGetAUniqueFormFieldName(): void
    {
        $service = static::getContainer()->get(CheckListService::class);
        $names = [];
        foreach ([1, 2, 3] as $i) {
            $field = new CheckListField();
            $field->setName('Naam');
            $field->setFieldType($this->createField($this->checkList, 'Hulp ' . $i, 'text')->getFieldType());
            $field->setOrder(10 + $i);
            $service->saveCheckListField($field, $this->checkList);
            $names[] = $field->getFormFieldName();
        }

        $this->assertSame(['naam', 'naam_2', 'naam_3'], $names);
    }

    public function testRenamingAFieldKeepsItsKeyAndAnswers(): void
    {
        $field = $this->createField($this->checkList, 'E-mail', 'email');
        $this->createItem($this->checkList, ['field_e-mail' => 'jan@test.nl']);

        $form = $this->client->request('GET', '/admin/checklist/field/' . $field->getId() . '/edit')->filter('form[name="check_list_field_form"]')->form();
        $form['check_list_field_form[name]'] = 'Mailadres';
        $this->client->submit($form);

        $this->assertResponseRedirects();
        $this->assertSame('e-mail', $this->connection->fetchOne('SELECT form_field_name FROM checklist_fields WHERE id = ?', [$field->getId()]));
    }

    public function testPlaceholderIsStoredInTheFieldOptions(): void
    {
        $field = $this->createField($this->checkList, 'Naam', 'text');
        $url = '/admin/checklist/field/' . $field->getId() . '/edit';

        $form = $this->client->request('GET', $url)->filter('form[name="check_list_field_form"]')->form();
        $form['check_list_field_form[placeholder]'] = '  Voor- en achternaam ';
        $this->client->submit($form);
        $this->assertResponseRedirects();
        $this->assertSame('Voor- en achternaam', $this->storedOptions($field)['placeholder'] ?? null);

        // Shown again when editing, and removed when emptied
        $form = $this->client->request('GET', $url)->filter('form[name="check_list_field_form"]')->form();
        $this->assertSame('Voor- en achternaam', $form['check_list_field_form[placeholder]']->getValue());
        $form['check_list_field_form[placeholder]'] = '';
        $this->client->submit($form);
        $this->assertArrayNotHasKey('placeholder', $this->storedOptions($field));
    }

    public function testPlaceholderIsNotStoredForATypeWithoutOne(): void
    {
        $field = $this->createField($this->checkList, 'Bijlage', 'file');

        $form = $this->client->request('GET', '/admin/checklist/field/' . $field->getId() . '/edit')->filter('form[name="check_list_field_form"]')->form();
        $form['check_list_field_form[placeholder]'] = 'Kies een bestand';
        $this->client->submit($form);

        $this->assertResponseRedirects();
        $this->assertArrayNotHasKey('placeholder', $this->storedOptions($field));
    }

    /** @return array<string, mixed> */
    private function storedOptions(\App\Entity\CheckListField $field): array
    {
        return json_decode((string) $this->connection->fetchOne('SELECT options FROM checklist_fields WHERE id = ?', [$field->getId()]), true) ?: [];
    }

    public function testDeletingAFieldRemovesItsAnswersAndThoseOfItsSubFields(): void
    {
        $fieldset = $this->createField($this->checkList, 'Adres', 'fieldset');
        $this->createField($this->checkList, 'Straat', 'text', parent: $fieldset);
        $this->createField($this->checkList, 'Naam', 'text');
        $item = $this->createItem($this->checkList, ['field_straat' => 'Dorpsstraat', 'field_naam' => 'Jan']);

        static::getContainer()->get(CheckListService::class)->removeCheckListField($fieldset);

        $this->assertSame(['field_naam' => 'Jan'], $this->storedContent($item));
    }

    public function testFieldCountIncludesFieldsInsideFieldsets(): void
    {
        $fieldset = $this->createField($this->checkList, 'Adres', 'fieldset');
        $this->createField($this->checkList, 'Straat', 'text', parent: $fieldset);
        $this->createField($this->checkList, 'Plaats', 'text', parent: $fieldset);
        $this->createField($this->checkList, 'Naam', 'text');
        // Let the page load the checklist from the database, like in a real request
        $this->em->clear();

        $row = $this->client->request('GET', '/admin/checklist/')->filter('tbody tr')->reduce(
            fn ($row) => str_contains($row->text(), $this->checkList->getName())
        );

        // Straat, Plaats and Naam; the fieldset itself is not a field with an answer
        $this->assertSame('3', trim($row->filter('td')->eq(2)->text()));
    }

    public function testFieldOrderNeedsAValidTokenAndAFieldsetAsParent(): void
    {
        $text = $this->createField($this->checkList, 'Tekst', 'text');
        $other = $this->createField($this->checkList, 'Ander', 'text');
        $fieldset = $this->createField($this->checkList, 'Groep', 'fieldset');
        $url = '/admin/checklist/' . $this->checkList->getId() . '/edit/reorder';
        $post = function (array $order, ?string $token) use ($url): void {
            $this->client->request('POST', $url, [], [], array_filter(['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $token]), json_encode(['order' => $order]));
        };

        $post([['id' => $text->getId(), 'parent_id' => $fieldset->getId(), 'order' => 1]], null);
        $this->assertResponseStatusCodeSame(403);

        $crawler = $this->client->request('GET', '/admin/checklist/' . $this->checkList->getId() . '/edit');
        $token = $crawler->filter('script[data-reorder-token]')->attr('data-reorder-token');

        $post([['id' => $text->getId(), 'parent_id' => $other->getId(), 'order' => 1]], $token);
        $this->assertResponseStatusCodeSame(422);
        $this->assertNull($this->connection->fetchOne('SELECT parent_id FROM checklist_fields WHERE id = ?', [$text->getId()]));

        $post([['id' => $text->getId(), 'parent_id' => $fieldset->getId(), 'order' => 1]], $token);
        $this->assertResponseIsSuccessful();
        $this->assertEquals($fieldset->getId(), $this->connection->fetchOne('SELECT parent_id FROM checklist_fields WHERE id = ?', [$text->getId()]));
    }

    public function testDuplicateCopiesFieldsButNotItems(): void
    {
        $fieldset = $this->createField($this->checkList, 'Groep', 'fieldset');
        $this->createField($this->checkList, 'Keuze', 'radio', ['condition' => ['field' => 'x', 'values' => ['1']]], parent: $fieldset, answers: ['1' => 'Ja', '0' => 'Nee']);
        $this->createItem($this->checkList, ['field_keuze' => '1']);

        $copy = static::getContainer()->get(CheckListService::class)->duplicateCheckList($this->checkList);

        $this->assertSame($this->checkList->getName() . ' (copy)', $copy->getName());
        $fields = $this->connection->fetchAllAssociative('SELECT name, parent_id IS NOT NULL nested, options FROM checklist_fields WHERE check_list_id = ? ORDER BY name', [$copy->getId()]);
        $this->assertSame(['Groep', 'Keuze'], array_column($fields, 'name'));
        $this->assertSame(1, (int) $fields[1]['nested']);
        $this->assertStringContainsString('"condition"', $fields[1]['options']);
        $this->assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM checklist_field_answers fa JOIN checklist_fields f ON f.id = fa.checklist_field_id WHERE f.check_list_id = ?', [$copy->getId()]));
        $this->assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM checklist_items WHERE check_list_id = ?', [$copy->getId()]));
    }

    public function testAnswersListCanBeSearchedFilteredAndSorted(): void
    {
        $this->createField($this->checkList, 'Kleur', 'radio', answers: ['r' => 'Rood', 'b' => 'Blauw']);
        $this->createField($this->checkList, 'Naam', 'text');
        $anna = $this->createItem($this->checkList, ['field_kleur' => 'r', 'field_naam' => 'Anna']);
        $bert = $this->createItem($this->checkList, ['field_kleur' => 'b', 'field_naam' => 'Bert']);
        $cor = $this->createItem($this->checkList, ['field_kleur' => 'r', 'field_naam' => 'Cor']);
        $ids = fn (string $query) => $this->client->request('GET', '/admin/checklist/' . $this->checkList->getId() . $query)
            ->filter('.js-row-link td:first-child')->each(fn ($cell) => (int) ltrim(trim($cell->text()), '#'));

        $this->assertSame([$anna->getId(), $bert->getId(), $cor->getId()], $ids(''));
        $this->assertSame([$cor->getId(), $bert->getId(), $anna->getId()], $ids('?order_by=id&order_dir=desc'));
        $this->assertSame([$bert->getId()], $ids('?q=bert'));
        $this->assertSame([$bert->getId()], $ids('?q=blauw'), 'search matches answer labels');
        $this->assertSame([$anna->getId(), $cor->getId()], $ids('?filter%5Bkleur%5D=r'));
    }

    public function testCsvExportUsesSemicolonsAndNeutralisesFormulas(): void
    {
        $this->createField($this->checkList, 'Tekst', 'text');
        $this->createItem($this->checkList, ['field_tekst' => '=HYPERLINK("http://evil","klik")']);
        $this->createItem($this->checkList, ['field_tekst' => 'Appels; peren']);

        $this->client->request('GET', '/admin/checklist/' . $this->checkList->getId() . '/export/csv');
        $csv = $this->client->getInternalResponse()->getContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'UTF-8 BOM for Excel');
        $rows = array_map(fn (string $line) => str_getcsv($line, ';', '"', ''), array_values(array_filter(explode("\n", substr($csv, 3)))));
        $this->assertSame(['ID', 'Created', 'Changed', 'Tekst'], $rows[0]);
        $this->assertSame('\'=HYPERLINK("http://evil","klik")', $rows[1][3]);
        $this->assertSame('Appels; peren', $rows[2][3]);
    }

    public function testExportFollowsTheFiltersOfTheList(): void
    {
        $this->createField($this->checkList, 'Kleur', 'radio', answers: ['r' => 'Rood', 'b' => 'Blauw']);
        $this->createItem($this->checkList, ['field_kleur' => 'r']);
        $this->createItem($this->checkList, ['field_kleur' => 'b']);

        $this->client->request('GET', '/admin/checklist/' . $this->checkList->getId() . '/export/csv?filter%5Bkleur%5D=b');
        $lines = array_values(array_filter(explode("\n", substr($this->client->getInternalResponse()->getContent(), 3))));

        $this->assertCount(2, $lines, 'header + 1 row');
        $this->assertStringEndsWith(';Blauw', $lines[1]);
    }
}
