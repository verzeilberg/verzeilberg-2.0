<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\CheckList;
use App\Entity\CheckListItem;
use App\Tests\Checklist\ChecklistTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * The admin item form: saving answers, conditions, default values, locked fields, validation and uploads.
 */
class CheckListItemFormTest extends WebTestCase
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

    public function testClearedAnswerStaysCleared(): void
    {
        $this->createField($this->checkList, 'Opmerking', 'text');
        $item = $this->createItem($this->checkList, ['field_opmerking' => 'oud']);
        $this->resync($item);

        $form = $this->editForm($item);
        $form['check_list_item[field_opmerking]'] = '';
        $this->client->submit($form);

        $this->assertResponseRedirects();
        $this->assertArrayNotHasKey('field_opmerking', $this->storedContent($item));
        $this->assertSame(0, $this->answersGivenCount($item), 'the old AnswerGiven must be gone too');
        $this->assertSame('', $this->editForm($item)['check_list_item[field_opmerking]']->getValue());
    }

    public function testCheckboxGetsOneLinkedAnswerPerSelectedValue(): void
    {
        $this->createField($this->checkList, 'Hobbies', 'checkbox', answers: ['sport' => 'Sport', 'muziek' => 'Muziek', 'lezen' => 'Lezen']);
        $item = $this->createItem($this->checkList, []);

        $form = $this->editForm($item);
        $form['check_list_item[field_hobbies]'][0]->tick();
        $form['check_list_item[field_hobbies]'][1]->tick();
        $this->client->submit($form);

        $rows = $this->connection->fetchAllAssociative(
            'SELECT ag.answer, a.label FROM answers_given ag LEFT JOIN answers a ON a.id = ag.answer_id WHERE ag.checklist_item_id = ? ORDER BY ag.answer',
            [$item->getId()]
        );
        $this->assertSame([['answer' => 'muziek', 'label' => 'Muziek'], ['answer' => 'sport', 'label' => 'Sport']], $rows);
    }

    public function testFieldHiddenByItsConditionIsNotRequiredAndNotSaved(): void
    {
        $this->createField($this->checkList, 'Type', 'radio', answers: ['particulier' => 'Particulier', 'zakelijk' => 'Zakelijk']);
        $this->createField($this->checkList, 'Bedrijf', 'text', ['condition' => ['field' => 'type', 'values' => ['zakelijk']]], required: true);

        $form = $this->newForm();
        $this->choose($form, 'field_type', 'particulier');
        $form['check_list_item[field_bedrijf]'] = 'wordt genegeerd';
        $this->client->submit($form);

        $this->assertResponseRedirects();
        $this->assertArrayNotHasKey('field_bedrijf', $this->storedContent($this->lastItem()));
    }

    public function testStoredFileOfAFieldThatBecameHiddenIsRemoved(): void
    {
        $this->createField($this->checkList, 'Type', 'radio', answers: ['particulier' => 'Particulier', 'zakelijk' => 'Zakelijk']);
        $this->createField($this->checkList, 'Kvk', 'file', ['condition' => ['field' => 'type', 'values' => ['zakelijk']]]);
        $stored = 'uploads/checklist/' . $this->checkList->getId() . '/kvk.pdf';
        $file = $this->storagePath($stored);
        @mkdir(dirname($file), 0777, true);
        file_put_contents($file, '%PDF');
        $item = $this->createItem($this->checkList, ['field_type' => 'zakelijk', 'field_kvk' => $stored]);

        try {
            $form = $this->editForm($item);
            $this->choose($form, 'field_type', 'particulier');
            $this->client->submit($form);

            $this->assertResponseRedirects();
            $this->assertSame(['field_type' => 'particulier'], $this->storedContent($item));
            $this->assertFileDoesNotExist($file, 'the file of the now hidden field is deleted');
        } finally {
            $this->removeStoredFile($file);
        }
    }

    public function testFieldShownByItsConditionIsRequired(): void
    {
        $this->createField($this->checkList, 'Type', 'radio', answers: ['particulier' => 'Particulier', 'zakelijk' => 'Zakelijk']);
        $this->createField($this->checkList, 'Bedrijf', 'text', ['condition' => ['field' => 'type', 'values' => ['zakelijk']]], required: true);

        $form = $this->newForm();
        $this->choose($form, 'field_type', 'zakelijk');
        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('.invalid-feedback', 'This field is required.');
    }

    public function testConditionIsRenderedForTheBrowser(): void
    {
        $this->createField($this->checkList, 'Type', 'radio', answers: ['zakelijk' => 'Zakelijk']);
        $this->createField($this->checkList, 'Bedrijf', 'text', ['condition' => ['field' => 'type', 'values' => ['zakelijk']]]);

        $crawler = $this->client->request('GET', $this->newUrl());
        $wrapper = $crawler->filter('#check_list_item_field_bedrijf')->ancestors()->filter('[data-field-wrapper]')->first();

        $this->assertSame('type', $wrapper->attr('data-condition-field'));
        $this->assertSame(['zakelijk'], json_decode((string) $wrapper->attr('data-condition-values'), true));
    }

    public function testDefaultValueIsOnlyUsedForANewItem(): void
    {
        $this->createField($this->checkList, 'Land', 'text', ['defaultValue' => 'Nederland']);
        $item = $this->createItem($this->checkList, []);

        $this->assertSame('Nederland', $this->newForm()['check_list_item[field_land]']->getValue());
        $this->assertSame('', $this->editForm($item)['check_list_item[field_land]']->getValue());
    }

    public function testOwnPlaceholderReplacesTheDefaultOne(): void
    {
        $this->createField($this->checkList, 'Naam', 'text', ['placeholder' => 'Voor- en achternaam']);
        $this->createField($this->checkList, 'Mail', 'email');
        $this->createField($this->checkList, 'Toelichting', 'textarea', ['placeholder' => 'Waarom?']);
        $this->createField($this->checkList, 'Datum', 'date', ['placeholder' => 'Kies een dag']);
        $this->createField($this->checkList, 'Maat', 'select', ['placeholder' => '— Kies een maat —'], answers: ['s' => 'Small']);
        $this->createField($this->checkList, 'Akkoord', 'checkbox', ['placeholder' => 'genegeerd'], answers: ['ja' => 'Ja']);

        $crawler = $this->client->request('GET', $this->newUrl());

        $this->assertSame('Voor- en achternaam', $crawler->filter('#check_list_item_field_naam')->attr('placeholder'));
        $this->assertSame('name@example.com', $crawler->filter('#check_list_item_field_mail')->attr('placeholder'), 'default when none is set');
        $this->assertSame('Waarom?', $crawler->filter('#check_list_item_field_toelichting')->attr('placeholder'));
        $this->assertSame('Kies een dag', $crawler->filter('#check_list_item_field_datum')->attr('placeholder'));
        $this->assertSame('— Kies een maat —', $crawler->filter('#check_list_item_field_maat option')->first()->text());
        $this->assertStringNotContainsString('genegeerd', $crawler->html(), 'a checkbox has no placeholder');
    }

    public function testReadonlyFieldKeepsItsValueWhenSomethingElseIsPosted(): void
    {
        $this->createField($this->checkList, 'Code', 'text', ['readonly' => true]);
        $item = $this->createItem($this->checkList, ['field_code' => 'ABC']);

        $values = $this->editForm($item)->getPhpValues();
        $values['check_list_item']['field_code'] = 'GEHACKT';
        $this->client->request('POST', $this->editUrl($item), $values);

        $this->assertResponseRedirects();
        $this->assertSame('ABC', $this->storedContent($item)['field_code']);
    }

    public function testReadonlyFieldOnANewItemGetsItsDefaultValue(): void
    {
        $this->createField($this->checkList, 'Bron', 'text', ['readonly' => true, 'defaultValue' => 'website']);

        $values = $this->newForm()->getPhpValues();
        $values['check_list_item']['field_bron'] = 'GEHACKT';
        $this->client->request('POST', $this->newUrl(), $values);

        $this->assertSame('website', $this->storedContent($this->lastItem())['field_bron']);
    }

    public function testDisabledFieldIgnoresPostedValue(): void
    {
        $this->createField($this->checkList, 'Notitie', 'textarea', ['disabled' => true]);
        $item = $this->createItem($this->checkList, ['field_notitie' => 'origineel']);

        $values = $this->editForm($item)->getPhpValues();
        $values['check_list_item']['field_notitie'] = 'GEHACKT';
        $this->client->request('POST', $this->editUrl($item), $values);

        $this->assertSame('origineel', $this->storedContent($item)['field_notitie']);
    }

    public function testEmailMustBeValidAndFromAnAllowedDomain(): void
    {
        $this->createField($this->checkList, 'Mail', 'email', ['allowedEmailDomains' => '.nl, voorbeeld.com']);

        foreach (['geen-email' => 'This is not a valid email address.', 'jan@gmail.com' => 'This email domain is not allowed.'] as $email => $message) {
            $form = $this->newForm();
            $form['check_list_item[field_mail]'] = $email;
            $this->client->submit($form);
            $this->assertResponseStatusCodeSame(422, $email);
            $this->assertSelectorTextContains('.invalid-feedback', $message);
        }

        foreach (['jan@bedrijf.nl', 'jan@voorbeeld.com'] as $email) {
            $form = $this->newForm();
            $form['check_list_item[field_mail]'] = $email;
            $this->client->submit($form);
            $this->assertResponseRedirects(message: $email);
        }
    }

    public function testMaxLengthIsEnforced(): void
    {
        $this->createField($this->checkList, 'Kort', 'text', ['maxlength' => true, 'maxLengthValue' => 5]);

        $form = $this->newForm();
        $form['check_list_item[field_kort]'] = '123456';
        $this->client->submit($form);
        $this->assertResponseStatusCodeSame(422);

        $form = $this->newForm();
        $form['check_list_item[field_kort]'] = '12345';
        $this->client->submit($form);
        $this->assertResponseRedirects();
    }

    public function testInfoIconExplainsTheRules(): void
    {
        $this->createField($this->checkList, 'Bijlage', 'file', ['maxUploadSize' => 2, 'allowedFileTypes' => 'pdf, jpg']);

        $crawler = $this->client->request('GET', $this->newUrl());
        $content = html_entity_decode(strip_tags((string) $crawler->filter('.js-field-info')->attr('data-bs-content')));

        $this->assertStringContainsString('Maximum 2 MB', $content);
        $this->assertStringContainsString('Allowed file types: pdf, jpg', $content);
    }

    public function testUploadIsStoredAndKeptWhenSavingWithoutANewFile(): void
    {
        $this->createField($this->checkList, 'Bijlage', 'file', ['allowedFileTypes' => 'txt']);
        $source = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($source, 'hallo');

        $form = $this->newForm();
        $this->client->request('POST', $this->newUrl(), $form->getPhpValues(), [
            'check_list_item' => ['field_bijlage' => new UploadedFile($source, 'notities.txt', 'text/plain', null, true)],
        ]);

        $item = $this->lastItem();
        $path = $this->storedContent($item)['field_bijlage'] ?? null;
        $absolute = $this->storagePath((string) $path);
        try {
            $this->assertNotNull($path);
            $this->assertStringStartsWith('uploads/checklist/' . $this->checkList->getId() . '/notities-', $path);
            $this->assertFileExists($absolute);
            $this->assertFileDoesNotExist(static::getContainer()->getParameter('kernel.project_dir') . '/public/' . $path, 'uploads are not public');

            // Only reachable through the protected route; a text file is downloaded, not shown
            $this->client->request('GET', '/checklists/item/' . $item->getId() . '/file/bijlage');
            $this->assertResponseIsSuccessful();
            $this->assertStringStartsWith('attachment;', (string) $this->client->getResponse()->headers->get('Content-Disposition'));

            $this->client->submit($this->editForm($item));
            $this->assertSame($path, $this->storedContent($item)['field_bijlage'], 'saving without a new file keeps the stored file');
            $this->assertFileExists($absolute);
        } finally {
            $this->removeStoredFile($absolute);
        }
    }

    /** Where a stored upload value ("uploads/checklist/...") lives on disk: outside public/ */
    private function storagePath(string $stored): string
    {
        return static::getContainer()->getParameter('kernel.project_dir') . '/var/storage/' . $stored;
    }

    /** Removes a test file and its (then empty) checklist directories */
    private function removeStoredFile(string $file): void
    {
        @unlink($file);
        @rmdir(dirname($file));          // uploads/checklist/{id}
        @rmdir(dirname($file, 2));       // uploads/checklist
        @rmdir(dirname($file, 3));       // uploads
    }

    private function choose(Form $form, string $field, string $value): void
    {
        $choice = $form['check_list_item[' . $field . ']'];
        $this->assertInstanceOf(ChoiceFormField::class, $choice);
        $choice->select($value);
    }

    private function newUrl(): string
    {
        return '/admin/checklist/' . $this->checkList->getId() . '/items/new';
    }

    private function editUrl(CheckListItem $item): string
    {
        return '/admin/checklist/item/' . $item->getId() . '/edit';
    }

    private function newForm(): Form
    {
        return $this->client->request('GET', $this->newUrl())->filter('form[name="check_list_item"]')->form();
    }

    private function editForm(CheckListItem $item): Form
    {
        return $this->client->request('GET', $this->editUrl($item))->filter('form[name="check_list_item"]')->form();
    }

    private function lastItem(): CheckListItem
    {
        $id = $this->connection->fetchOne('SELECT MAX(id) FROM checklist_items WHERE check_list_id = ?', [$this->checkList->getId()]);
        $this->em->clear();

        return $this->em->find(CheckListItem::class, $id);
    }

    private function resync(CheckListItem $item): void
    {
        static::getContainer()->get(\App\Service\CheckListService::class)->syncItemAnswers($item, $this->checkList);
    }

    private function answersGivenCount(CheckListItem $item): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM answers_given WHERE checklist_item_id = ?', [$item->getId()]);
    }
}
