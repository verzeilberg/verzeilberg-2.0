<?php

declare(strict_types=1);

namespace App\Tests\Checklist;

use App\Entity\CheckList;
use App\Entity\CheckListItem;
use App\Service\CheckListImporter;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Importing items from the Excel template (or a CSV file) on the checklist's import page */
class ChecklistImportTest extends WebTestCase
{
    use ChecklistTestTrait;

    private CheckList $checkList;
    private CheckListImporter $importer;

    protected function setUp(): void
    {
        $this->setUpChecklistTest();
        $this->loginAs($this->createUser('ROLE_ADMIN'));
        $this->checkList = $this->createCheckList('Import');
        $this->importer = static::getContainer()->get(CheckListImporter::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownChecklistTest();
        parent::tearDown();
    }

    public function testTemplateHasAColumnPerFillableFieldAndListsTheChoices(): void
    {
        $this->createField($this->checkList, 'Naam', 'text', required: true);
        $this->createField($this->checkList, 'Kleur', 'select', answers: ['r' => 'Rood', 'b' => 'Blauw, donker']);
        $set = $this->createField($this->checkList, 'Adres', 'fieldset');
        $this->createField($this->checkList, 'Plaats', 'text', parent: $set);
        $this->createField($this->checkList, 'Foto', 'file');
        $this->createField($this->checkList, 'Code', 'text', ['readonly' => true]);
        $this->createField($this->checkList, 'Gezien', 'checkbox', answers: ['1' => 'Ja']);
        $this->createField($this->checkList, 'Fruit', 'checkbox', answers: ['a' => 'Appel', 'p' => 'Peer']);

        ob_start();
        $this->client->request('GET', '/admin/checklist/' . $this->checkList->getId() . '/import/template');
        $content = ob_get_clean() ?: $this->client->getInternalResponse()->getContent();
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = tempnam(sys_get_temp_dir(), 'tpl') . '.xlsx';
        file_put_contents($path, $content);
        $sheets = $this->readSheets($path);
        $zip = new \ZipArchive();
        $zip->open($path);
        $sheetXml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);

        $this->assertSame(['Invullen', 'Uitleg', 'Keuzes'], array_keys($sheets));
        $this->assertSame([['Naam', 'Kleur', 'Adres › Plaats', 'Gezien', 'Fruit']], $sheets['Invullen'], 'no file or readonly columns');
        $this->assertSame([['Kleur', 'Gezien', 'Fruit'], ['Rood', 'Ja', 'Appel'], ['Blauw, donker', '', 'Peer']], $sheets['Keuzes']);

        // Every choice column gets a dropdown from the Keuzes sheet; a checkbox with several answers also allows typed text
        $this->assertStringContainsString('errorStyle="stop"', $this->validationFor($sheetXml, 'B2:B501', 'Keuzes!$A$2:$A$3'));
        $this->assertStringContainsString('errorStyle="stop"', $this->validationFor($sheetXml, 'D2:D501', 'Keuzes!$B$2:$B$2'));
        $this->assertStringContainsString('errorStyle="information"', $this->validationFor($sheetXml, 'E2:E501', 'Keuzes!$C$2:$C$3'));
        $this->assertStringContainsString('Foto', json_encode($sheets['Uitleg'], JSON_UNESCAPED_UNICODE));
    }

    public function testRowsBecomeItemsWithChoiceValuesDatesAndDefaults(): void
    {
        $this->createField($this->checkList, 'Naam', 'text', required: true);
        $this->createField($this->checkList, 'Kleur', 'select', answers: ['r' => 'Rood', 'b' => 'Blauw']);
        $this->createField($this->checkList, 'Fruit', 'checkbox', answers: ['a' => 'Appel', 'p' => 'Peer', 'k' => 'Kiwi']);
        $this->createField($this->checkList, 'Datum', 'date', ['dateFormat' => 'dd-MM-yyyy']);
        $this->createField($this->checkList, 'Tijd', 'time');
        $this->createField($this->checkList, 'Aantal', 'number', ['defaultValue' => '3']);

        $result = $this->importer->importRows($this->checkList, [
            1 => ['Naam', 'Kleur', 'Fruit', 'Datum', 'Tijd', 'Aantal'],
            2 => ['Jan', 'blauw', 'Appel; kiwi', new \DateTimeImmutable('2026-11-04'), new \DateTimeImmutable('1899-12-30 14:30'), 5.0],
            4 => ['Piet', 'r', null, '2026-12-31', '9.05', null],
        ], null);

        $this->assertSame([], $result->errors);
        $this->assertSame(2, $result->imported);

        $items = $this->em->getRepository(CheckListItem::class)->findBy(['checkList' => $this->checkList], ['id' => 'ASC']);
        $this->assertCount(2, $items);
        $this->assertSame([
            'field_naam' => 'Jan', 'field_kleur' => 'b', 'field_fruit' => ['a', 'k'],
            'field_datum' => '04-11-2026', 'field_tijd' => '14:30', 'field_aantal' => 5,
        ], $this->storedContent($items[0]));
        $this->assertSame([
            // An empty checkbox field is stored as an empty list, like the form does
            'field_naam' => 'Piet', 'field_kleur' => 'r', 'field_fruit' => [], 'field_datum' => '31-12-2026', 'field_tijd' => '09:05', 'field_aantal' => 3,
        ], $this->storedContent($items[1]), 'empty cell gets the default value');
        $this->assertCount(2, $items[0]->getAnswersGiven()->filter(fn ($given) => $given->getCheckListField()->getName() === 'Fruit'), 'one answer per chosen value');
    }

    public function testNothingIsImportedWhenOneRowIsWrong(): void
    {
        $this->createField($this->checkList, 'Naam', 'text', required: true);
        $this->createField($this->checkList, 'Kleur', 'radio', answers: ['r' => 'Rood']);
        $this->createField($this->checkList, 'Mail', 'email');

        $result = $this->importer->importRows($this->checkList, [
            1 => ['Naam', 'Kleur', 'Mail'],
            2 => ['Goed', 'Rood', 'a@example.com'],
            3 => [null, 'Paars', 'geen-mail'],
        ], null);

        $this->assertSame(0, $result->imported);
        $errors = implode("\n", $result->errors);
        $this->assertStringContainsString('Rij 3: kolom "Kleur": "Paars" is geen geldige keuze. Kies uit: Rood.', $errors);
        $this->assertStringContainsString('Rij 3, kolom "Naam": Dit veld is verplicht.', $errors);
        $this->assertStringContainsString('Rij 3, kolom "Mail": Dit is geen geldig e-mailadres.', $errors);
        $this->assertStringNotContainsString('Rij 2', $errors);
        $this->assertCount(0, $this->em->getRepository(CheckListItem::class)->findBy(['checkList' => $this->checkList]));
    }

    public function testUnknownColumnsAreRejected(): void
    {
        $this->createField($this->checkList, 'Naam', 'text');

        $result = $this->importer->importRows($this->checkList, [1 => ['naam ', 'Leeftijd'], 2 => ['Jan', '40']], null);

        $this->assertSame(['Onbekende kolom "Leeftijd". Gebruik de kolommen uit het importsjabloon.'], $result->errors);
    }

    public function testUploadingACsvFileOnTheImportPage(): void
    {
        $this->createField($this->checkList, 'Naam', 'text', required: true);
        $this->createField($this->checkList, 'Kleur', 'select', answers: ['r' => 'Rood', 'b' => 'Blauw']);

        $crawler = $this->client->request('GET', '/admin/checklist/' . $this->checkList->getId() . '/import');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href$="/import/template"]');

        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, "\xEF\xBB\xBFNaam;Kleur\nAnna;Blauw\n\nBert;Rood\n");
        $this->upload($crawler, $path);
        unlink($path);

        self::assertResponseRedirects('/admin/checklist/' . $this->checkList->getId());
        $items = $this->em->getRepository(CheckListItem::class)->findBy(['checkList' => $this->checkList], ['id' => 'ASC']);
        $this->assertSame(['Anna', 'Bert'], array_map(fn (CheckListItem $item) => $this->storedContent($item)['field_naam'], $items));
        $this->assertSame('b', $this->storedContent($items[0])['field_kleur']);
    }

    public function testImportPageShowsTheErrors(): void
    {
        $this->createField($this->checkList, 'Naam', 'text', required: true);

        $crawler = $this->client->request('GET', '/admin/checklist/' . $this->checkList->getId() . '/import');
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, "Naam;Extra\nAnna;x\n");
        $this->upload($crawler, $path);
        unlink($path);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.alert-danger', 'Onbekende kolom "Extra"');
    }

    public function testOnlyExcelAndCsvFilesAreAccepted(): void
    {
        $this->createField($this->checkList, 'Naam', 'text');
        $path = tempnam(sys_get_temp_dir(), 'txt');
        file_put_contents($path, "Naam\nJan\n");

        $result = $this->importer->import($this->checkList, new UploadedFile($path, 'items.txt', 'text/plain', null, true), null);
        unlink($path);

        $this->assertSame(['Upload een Excel-bestand (.xlsx) of een CSV-bestand (.csv).'], $result->errors);
    }

    public function testCsvWithCommasAndAnXlsxFileAreBothRead(): void
    {
        $this->createField($this->checkList, 'Naam', 'text');
        $this->createField($this->checkList, 'Plaats', 'text');

        $csv = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($csv, "Naam,Plaats\n\"Jansen, Jan\",Almere\n");
        $result = $this->importer->import($this->checkList, new UploadedFile($csv, 'items.csv', 'text/csv', null, true), null);
        unlink($csv);
        $this->assertSame(1, $result->imported, implode("\n", $result->errors));

        // The downloaded template, filled in
        $xlsx = tempnam(sys_get_temp_dir(), 'xlsx');
        $writer = new \OpenSpout\Writer\XLSX\Writer();
        $writer->openToFile($xlsx);
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['Naam', 'Plaats']));
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['Piet', 'Utrecht']));
        $writer->close();
        $result = $this->importer->import($this->checkList, new UploadedFile($xlsx, 'items.xlsx', null, null, true), null);
        unlink($xlsx);
        $this->assertSame(1, $result->imported, implode("\n", $result->errors));

        $items = $this->em->getRepository(CheckListItem::class)->findBy(['checkList' => $this->checkList], ['id' => 'ASC']);
        $this->assertSame(
            [['field_naam' => 'Jansen, Jan', 'field_plaats' => 'Almere'], ['field_naam' => 'Piet', 'field_plaats' => 'Utrecht']],
            array_map(fn (CheckListItem $item) => $this->storedContent($item), $items)
        );
    }

    public function testChoiceLabelWithACommaIsOneChoice(): void
    {
        $this->createField($this->checkList, 'Kleur', 'checkbox', answers: ['bd' => 'Blauw, donker', 'r' => 'Rood']);

        $result = $this->importer->importRows($this->checkList, [1 => ['Kleur'], 2 => ['Blauw, donker'], 3 => ['rood; Blauw, donker'], 4 => ['Rood, Blauw, donker']], null);

        $this->assertSame([], $result->errors);
        $items = $this->em->getRepository(CheckListItem::class)->findBy(['checkList' => $this->checkList], ['id' => 'ASC']);
        $this->assertSame(['bd'], $this->storedContent($items[0])['field_kleur']);
        // Stored in the order of the field's answers, like the form does
        $this->assertSame(['bd', 'r'], $this->storedContent($items[1])['field_kleur']);
        $this->assertSame(['bd', 'r'], $this->storedContent($items[2])['field_kleur'], 'commas as in the export');
    }

    public function testTooManyRowsAndAnEmptyFileAreRefused(): void
    {
        $this->createField($this->checkList, 'Naam', 'text');

        $rows = [1 => ['Naam']];
        for ($i = 2; $i <= CheckListImporter::MAX_ROWS + 2; ++$i) {
            $rows[$i] = ['Item ' . $i];
        }
        $this->assertSame(
            [sprintf('Het bestand heeft %d rijen; je kunt maximaal %d items tegelijk importeren.', CheckListImporter::MAX_ROWS + 1, CheckListImporter::MAX_ROWS)],
            $this->importer->importRows($this->checkList, $rows, null)->errors
        );
        $this->assertSame(['Het bestand is leeg.'], $this->importer->importRows($this->checkList, [], null)->errors);
        $this->assertSame(
            ['Er staan geen items in het bestand: vul vanaf de tweede rij één item per rij in.'],
            $this->importer->importRows($this->checkList, [1 => ['Naam']], null)->errors
        );
    }

    public function testImportedItemsAreCreatedByTheUploader(): void
    {
        $this->createField($this->checkList, 'Naam', 'text');
        $user = $this->createUser('ROLE_ADMIN');

        $this->importer->importRows($this->checkList, [1 => ['Naam'], 2 => ['Jan']], $user);

        $item = $this->em->getRepository(CheckListItem::class)->findOneBy(['checkList' => $this->checkList]);
        $this->assertSame($user->getId(), $item?->getCreatedBy()?->getId());
    }

    private function upload(\Symfony\Component\DomCrawler\Crawler $crawler, string $path): void
    {
        $form = $crawler->selectButton('Importeren')->form();
        $this->client->request('POST', $form->getUri(), ['_token' => $form['_token']->getValue()], [
            'file' => new UploadedFile($path, 'items.csv', 'text/csv', null, true),
        ]);
    }

    private function validationFor(string $sheetXml, string $range, string $source): string
    {
        $this->assertMatchesRegularExpression('#<dataValidation [^>]*sqref="' . preg_quote($range, '#') . '"[^>]*><formula1>' . preg_quote($source, '#') . '</formula1>#', $sheetXml);
        preg_match('#<dataValidation [^>]*sqref="' . preg_quote($range, '#') . '"[^>]*>#', $sheetXml, $match);

        return $match[0];
    }

    /** @return array<string, list<list<mixed>>> sheet name => rows */
    private function readSheets(string $path): array
    {
        $reader = new XlsxReader();
        $reader->open($path);
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[$sheet->getName()][] = $row->toArray();
            }
        }
        $reader->close();

        return $sheets;
    }
}
