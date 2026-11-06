<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CheckList;
use App\Entity\CheckListField;
use App\Entity\CheckListItem;
use App\Entity\User;
use App\Form\CheckListItemType;
use Doctrine\ORM\EntityManagerInterface;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Comment\TextRun;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Reader\CSV\Options as CsvReaderOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Options as XlsxReaderOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options as XlsxWriterOptions;
use OpenSpout\Writer\XLSX\Validation\CellReference;
use OpenSpout\Writer\XLSX\Validation\ErrorStyle;
use OpenSpout\Writer\XLSX\Validation\Rules\ListValidationRule;
use OpenSpout\Writer\XLSX\Validation\ValidationDisplay;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Creates new checklist items from a spreadsheet (xlsx or csv): one row per item, one column per field.
 *
 * The template has the same column headers as the export. Every row goes through the same form as the
 * "new item" page (CheckListItemType), so required fields, conditions, choices, dates and other rules
 * are checked exactly the same way. All rows are checked first: when one row is wrong, nothing is imported.
 */
class CheckListImporter
{
    public const MAX_ROWS = 1000;
    public const MAX_FILE_SIZE = 5 * 1024 * 1024;

    /** Number of rows in the template that get a dropdown for choice fields */
    private const TEMPLATE_ROWS = 500;

    private const CHOICES_SHEET = 'Keuzes';

    /** Shown errors; the rest is summarised so a badly filled file doesn't produce a page of thousands of lines */
    private const MAX_ERRORS = 50;

    /** Field types the form lets users lock (see CheckListItemType::LOCKABLE_TYPES) */
    private const LOCKABLE_TYPES = ['text', 'textarea', 'number'];

    public function __construct(
        private CheckListService $checkListService,
        private FormFactoryInterface $formFactory,
        private EntityManagerInterface $em,
        private SluggerInterface $slugger,
    ) {
    }

    /**
     * The columns that can be filled in: the export columns without file uploads and without
     * fields that are readonly or disabled (those can't be filled in on the form either).
     *
     * @return list<array{field: CheckListField, header: string}>
     */
    public function getImportColumns(CheckList $checkList): array
    {
        return array_values(array_filter(
            $this->checkListService->getValueColumns($checkList),
            fn (array $column) => $column['field']->getFormType() !== 'file' && !$this->isLocked($column['field'])
        ));
    }

    public function createTemplateResponse(CheckList $checkList): StreamedResponse
    {
        $filename = sprintf('%s-importsjabloon.xlsx', strtolower((string) $this->slugger->slug($checkList->getName())));

        $response = new StreamedResponse(function () use ($checkList): void {
            $this->writeTemplate($checkList, 'php://output');
        });
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename));
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    public function writeTemplate(CheckList $checkList, string $path): void
    {
        $columns = $this->getImportColumns($checkList);
        $bold = (new Style())->withFontBold(true);

        // Choice lists go on their own sheet, because a dropdown with inline values can't contain commas
        $choiceColumns = [];
        foreach ($columns as $index => $column) {
            if (in_array($column['field']->getFormType(), ['select', 'radio', 'checkbox'], true) && $column['field']->getAnswers()->count() > 0) {
                $choiceColumns[$index] = $column;
            }
        }

        $options = new XlsxWriterOptions(DEFAULT_COLUMN_WIDTH: 25);
        $choiceSheetColumn = 0;
        foreach ($choiceColumns as $index => $column) {
            $field = $column['field'];
            // A dropdown holds one value. A checkbox field with several answers still gets one, but then Excel
            // only warns (information style) about other text, so several values ("A; B") can be typed in.
            $multiple = $field->getFormType() === 'checkbox' && $field->getAnswers()->count() > 1;
            $options->addValidation(
                $index, 2, $index, self::TEMPLATE_ROWS + 1,
                new ListValidationRule(new CellReference($choiceSheetColumn, 2, $choiceSheetColumn, $field->getAnswers()->count() + 1, true, self::CHOICES_SHEET)),
                new ValidationDisplay(
                    errorStyle: $multiple ? ErrorStyle::Information : ErrorStyle::Stop,
                    promptTitle: $this->truncate($column['header'], 32),
                    prompt: $multiple
                        ? 'Kies een waarde uit de lijst, of typ er meer met een puntkomma ertussen.'
                        : ($field->getFormType() === 'checkbox' ? 'Kies een waarde uit de lijst, of laat leeg.' : 'Kies een waarde uit de lijst.'),
                    errorTitle: $multiple ? 'Meerdere waarden?' : 'Ongeldige waarde',
                    error: $multiple
                        ? 'Gebruik waarden van het tabblad Keuzes, gescheiden door een puntkomma (;).'
                        : 'Kies een waarde uit de lijst (zie het tabblad Keuzes).',
                ),
            );
            ++$choiceSheetColumn;
        }

        $writer = new XlsxWriter($options);
        $writer->openToFile($path);

        // Sheet 1: the columns to fill in
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Invullen');
        $sheet->setSheetView(new SheetView(freezeRow: 2));
        $writer->addRow(new Row(array_map(fn (array $column) => new StringCell($column['header'], $bold), $columns)));

        // Sheet 2: what to fill in per column
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('Uitleg');
        $sheet->setColumnWidth(35, 1);
        $sheet->setColumnWidth(18, 2, 3);
        $sheet->setColumnWidth(60, 4);
        $writer->addRow(new Row([new StringCell('Vul op het tabblad "Invullen" één item per rij in. Laat de kopjes in de eerste rij staan.', $bold)]));
        $writer->addRow(new Row([new StringCell('Lege cellen krijgen de standaardwaarde van het veld. Klopt één rij niet, dan wordt niets geïmporteerd en zie je per rij wat er mis is.')]));
        $writer->addRow(new Row([new StringCell('')]));
        $writer->addRow(new Row(array_map(fn (string $title) => new StringCell($title, $bold), ['Kolom', 'Soort', 'Verplicht', 'Wat vul je in'])));
        foreach ($columns as $column) {
            $writer->addRow(new Row([
                new StringCell($column['header']),
                new StringCell($this->describeType($column['field'])),
                new StringCell($column['field']->isRequired() ? 'Ja' : 'Nee'),
                new StringCell($this->describeInput($column['field'])),
            ]));
        }
        $skipped = array_filter(
            $this->checkListService->getValueColumns($checkList),
            fn (array $column) => !in_array($column, $columns, true)
        );
        if ($skipped) {
            $writer->addRow(new Row([new StringCell('')]));
            $writer->addRow(new Row([new StringCell('Niet te importeren (vul je daarna in via het item zelf):', $bold)]));
            foreach ($skipped as $column) {
                $reason = $column['field']->getFormType() === 'file' ? 'Bestand uploaden' : 'Veld is vergrendeld';
                $writer->addRow(new Row([new StringCell($column['header']), new StringCell($reason)]));
            }
        }

        // Sheet 3: the allowed values of every choice field
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName(self::CHOICES_SHEET);
        $lists = array_map(
            fn (array $column) => array_values(array_map(fn ($answer) => (string) $answer->getLabel(), $column['field']->getAnswers()->toArray())),
            array_values($choiceColumns)
        );
        $writer->addRow(new Row(array_map(fn (array $column) => new StringCell($column['header'], $bold), array_values($choiceColumns))));
        $height = $lists ? max(array_map('count', $lists)) : 0;
        for ($i = 0; $i < $height; ++$i) {
            $writer->addRow(new Row(array_map(fn (array $labels) => new StringCell($labels[$i] ?? ''), $lists)));
        }

        $writer->close();
    }

    public function import(CheckList $checkList, UploadedFile $file, ?User $user): CheckListImportResult
    {
        if (!$file->isValid()) {
            return new CheckListImportResult(errors: ['Het bestand kon niet worden geüpload: ' . $file->getErrorMessage()]);
        }
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return new CheckListImportResult(errors: [sprintf('Het bestand is te groot (maximaal %d MB).', self::MAX_FILE_SIZE / 1024 / 1024)]);
        }
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['xlsx', 'csv'], true)) {
            return new CheckListImportResult(errors: ['Upload een Excel-bestand (.xlsx) of een CSV-bestand (.csv).']);
        }

        try {
            $rows = $this->readRows($file->getPathname(), $extension);
        } catch (IOException|\RuntimeException) {
            return new CheckListImportResult(errors: ['Het bestand kon niet worden gelezen. Is het een geldig ' . ($extension === 'xlsx' ? 'Excel' : 'CSV') . '-bestand?']);
        }

        return $this->importRows($checkList, $rows, $user);
    }

    /**
     * @param array<int, list<mixed>> $rows spreadsheet row number => cell values; the first row holds the headers
     */
    public function importRows(CheckList $checkList, array $rows, ?User $user): CheckListImportResult
    {
        $headerRowNumber = array_key_first($rows);
        if ($headerRowNumber === null) {
            return new CheckListImportResult(errors: ['Het bestand is leeg.']);
        }

        [$columnMap, $errors] = $this->mapHeaders($checkList, $rows[$headerRowNumber]);
        if ($errors) {
            return new CheckListImportResult(errors: $errors);
        }

        unset($rows[$headerRowNumber]);
        if ($rows === []) {
            return new CheckListImportResult(errors: ['Er staan geen items in het bestand: vul vanaf de tweede rij één item per rij in.']);
        }
        if (count($rows) > self::MAX_ROWS) {
            return new CheckListImportResult(errors: [sprintf('Het bestand heeft %d rijen; je kunt maximaal %d items tegelijk importeren.', count($rows), self::MAX_ROWS)]);
        }

        $fields = $this->checkListService->getCheckListFieldsByCheckListWithChildren($checkList);
        $headers = [];
        foreach ($fields as $field) {
            $headers['field_' . $field->getFormFieldName()] = $field->getName();
        }
        foreach ($columnMap as $column) {
            $headers['field_' . $column['field']->getFormFieldName()] = $column['header'];
        }

        $items = [];
        foreach ($rows as $rowNumber => $cells) {
            $item = new CheckListItem();
            $form = $this->formFactory->create(CheckListItemType::class, $item, [
                'check_list' => $checkList,
                'fields' => $fields,
                'csrf_protection' => false,
            ]);

            $rowErrors = [];
            $data = $this->buildFormData($form, $columnMap, $cells, $rowErrors);
            $form->submit($data);

            foreach ($rowErrors as $error) {
                $errors[] = sprintf('Rij %d: %s', $rowNumber, $error);
            }
            if (!$form->isValid()) {
                /** @var FormError $error */
                foreach ($form->getErrors(true) as $error) {
                    $name = $error->getOrigin()?->getName();
                    $column = $name !== null && isset($headers[$name]) ? sprintf(', kolom "%s"', $headers[$name]) : '';
                    $errors[] = sprintf('Rij %d%s: %s', $rowNumber, $column, $error->getMessage());
                }
            }
            $items[] = $item;
        }

        if ($errors) {
            $errors = array_values(array_unique($errors));
            if (count($errors) > self::MAX_ERRORS) {
                $more = count($errors) - self::MAX_ERRORS;
                $errors = array_slice($errors, 0, self::MAX_ERRORS);
                $errors[] = sprintf('… en nog %d %s.', $more, $more === 1 ? 'fout' : 'fouten');
            }

            return new CheckListImportResult(errors: $errors);
        }

        $this->em->wrapInTransaction(function () use ($items, $checkList, $user): void {
            foreach ($items as $item) {
                $this->checkListService->saveCheckListItem($item, $checkList, $user);
            }
        });

        return new CheckListImportResult(imported: count($items));
    }

    /**
     * @return array<int, list<mixed>> spreadsheet row number => cell values, without empty rows
     */
    private function readRows(string $path, string $extension): array
    {
        $reader = $extension === 'xlsx'
            ? new XlsxReader(new XlsxReaderOptions(SHOULD_PRESERVE_EMPTY_ROWS: true))
            : new CsvReader(new CsvReaderOptions(SHOULD_PRESERVE_EMPTY_ROWS: true, FIELD_DELIMITER: $this->detectCsvDelimiter($path)));

        $rows = [];
        $reader->open($path);
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $rowNumber = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    ++$rowNumber;
                    $values = $row->toArray();
                    if (array_filter($values, fn ($value) => $value !== null && trim($this->toString($value)) !== '') === []) {
                        continue;
                    }
                    $rows[$rowNumber] = $values;
                    if (count($rows) > self::MAX_ROWS + 1) {
                        break;
                    }
                }
                break; // only the first sheet ("Invullen")
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    /** Dutch Excel saves CSV with semicolons, other programs use commas */
    private function detectCsvDelimiter(string $path): string
    {
        $handle = fopen($path, 'r') ?: throw new \RuntimeException('Cannot open file');
        $firstLine = (string) fgets($handle);
        fclose($handle);

        return substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
    }

    /**
     * @param list<mixed> $headerCells
     *
     * @return array{0: array<int, array{field: CheckListField, header: string}>, 1: list<string>} column index => column, errors
     */
    private function mapHeaders(CheckList $checkList, array $headerCells): array
    {
        $available = [];
        foreach ($this->getImportColumns($checkList) as $column) {
            $available[$this->normalizeHeader($column['header'])] = $column;
        }

        $map = [];
        $errors = [];
        foreach ($headerCells as $index => $header) {
            $header = trim($this->toString($header));
            if ($header === '') {
                continue;
            }
            $column = $available[$this->normalizeHeader($header)] ?? null;
            if ($column === null) {
                $errors[] = sprintf('Onbekende kolom "%s". Gebruik de kolommen uit het importsjabloon.', $header);
                continue;
            }
            if (in_array($column, $map, true)) {
                $errors[] = sprintf('De kolom "%s" staat er meer dan één keer in.', $header);
                continue;
            }
            $map[$index] = $column;
        }

        if (!$errors && $map === []) {
            $errors[] = 'De eerste rij bevat geen kolomnamen uit het importsjabloon.';
        }

        return [$map, $errors];
    }

    private function normalizeHeader(string $header): string
    {
        // A "›" may have become ">" when the file was edited elsewhere
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim(str_replace('>', '›', $header))));
    }

    /**
     * The data the browser would post for this row: the cell values in the form's input format, and for
     * empty cells the value the form starts with (the field's default value), like a pre-filled new item form.
     *
     * @param array<int, array{field: CheckListField, header: string}> $columnMap
     * @param list<mixed>                                              $cells
     * @param list<string>                                             $rowErrors
     *
     * @return array<string, mixed>
     */
    private function buildFormData(FormInterface $form, array $columnMap, array $cells, array &$rowErrors): array
    {
        $data = [];
        foreach ($form->all() as $name => $child) {
            $value = $child->getViewData();
            if ($value !== null && $value !== '' && $value !== []) {
                $data[$name] = $value;
            }
        }

        foreach ($columnMap as $index => $column) {
            $cell = $cells[$index] ?? null;
            if ($cell === null || (is_string($cell) && trim($cell) === '')) {
                continue;
            }
            $field = $column['field'];
            $value = $this->convertCell($field, $cell, $error);
            if ($error !== null) {
                $rowErrors[] = sprintf('kolom "%s": %s', $column['header'], $error);
                continue;
            }
            $data['field_' . $field->getFormFieldName()] = $value;
        }

        return $data;
    }

    /**
     * Converts a cell value to what the field's input expects (choice values, a date in the field's format, …).
     */
    private function convertCell(CheckListField $field, mixed $cell, ?string &$error): string|array
    {
        $error = null;

        switch ($field->getFormType()) {
            case 'select':
            case 'radio':
                $value = $this->findChoiceValue($field, $this->toString($cell));
                if ($value === null) {
                    $error = sprintf('"%s" is geen geldige keuze. Kies uit: %s.', $this->toString($cell), $this->choiceLabels($field));

                    return '';
                }

                return $value;

            case 'checkbox':
                $values = [];
                foreach ($this->splitChoices($field, $this->toString($cell)) as $part) {
                    $value = $this->findChoiceValue($field, $part);
                    if ($value === null) {
                        $error = sprintf('"%s" is geen geldige keuze. Kies uit: %s.', $part, $this->choiceLabels($field));

                        return [];
                    }
                    $values[] = $value;
                }

                return array_values(array_unique($values));

            case 'date':
                $format = strtr($this->getIcuDateFormat($field), ['yyyy' => 'Y', 'MM' => 'm', 'dd' => 'd']);
                if ($cell instanceof \DateTimeInterface) {
                    return $cell->format($format);
                }
                $text = trim($this->toString($cell));
                foreach (array_unique([$format, 'Y-m-d', 'd-m-Y', 'd/m/Y', 'd.m.Y', 'j-n-Y', 'j/n/Y']) as $candidate) {
                    $date = \DateTimeImmutable::createFromFormat('!' . $candidate, $text);
                    if ($date !== false && $date->format($candidate) === $text) {
                        return $date->format($format);
                    }
                }

                return $text; // the form reports it as invalid

            case 'time':
                if ($cell instanceof \DateTimeInterface) {
                    return $cell->format('H:i');
                }
                if ($cell instanceof \DateInterval) {
                    return sprintf('%02d:%02d', $cell->h + $cell->d * 24, $cell->i);
                }
                $text = trim($this->toString($cell));

                return preg_match('/^(\d{1,2})[:.](\d{2})(?::\d{2})?$/', $text, $m) ? sprintf('%02d:%s', $m[1], $m[2]) : $text;

            default:
                $text = $this->toString($cell);
                // The CSV export puts ' before values that would otherwise be read as a formula
                if (str_starts_with($text, "'") && preg_match('/^\'[=+\-@]/', $text)) {
                    $text = substr($text, 1);
                }

                return $field->getFormType() === 'textarea' ? $text : trim($text);
        }
    }

    /**
     * Splits the text of a checkbox cell into its choices. Several choices are separated by ";". Without a ";"
     * they may be separated by "," (as in the export), but a label can contain a comma itself ("Blauw, donker"),
     * so the longest run of parts that forms a label is taken as one choice.
     *
     * @return list<string>
     */
    private function splitChoices(CheckListField $field, string $text): array
    {
        $clean = fn (array $parts) => array_values(array_filter(array_map('trim', $parts), fn (string $part) => $part !== ''));
        if (str_contains($text, ';')) {
            return $clean(explode(';', $text));
        }

        $parts = $clean(explode(',', $text));
        $choices = [];
        for ($start = 0; $start < count($parts); $start = $end) {
            for ($end = count($parts); $end > $start + 1; --$end) {
                if ($this->findChoiceValue($field, implode(', ', array_slice($parts, $start, $end - $start))) !== null) {
                    break;
                }
            }
            $choices[] = implode(', ', array_slice($parts, $start, $end - $start));
        }

        return $choices;
    }

    /** The answer value for a label or value (not case sensitive), or null when the field has no such choice */
    private function findChoiceValue(CheckListField $field, string $text): ?string
    {
        $needle = mb_strtolower(trim($text));
        foreach ($field->getAnswers() as $answer) {
            if (mb_strtolower(trim((string) $answer->getLabel())) === $needle) {
                return (string) $answer->getValue();
            }
        }
        foreach ($field->getAnswers() as $answer) {
            if (mb_strtolower(trim((string) $answer->getValue())) === $needle) {
                return (string) $answer->getValue();
            }
        }

        return null;
    }

    private function choiceLabels(CheckListField $field): string
    {
        return implode(', ', array_map(fn ($answer) => (string) $answer->getLabel(), $field->getAnswers()->toArray()));
    }

    private function toString(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? '1' : '0',
            is_float($value) && floor($value) === $value && abs($value) < PHP_INT_MAX => (string) (int) $value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
            $value instanceof \DateInterval => $value->format('%H:%I'),
            // Rich text (parts with their own formatting)
            is_array($value) => implode('', array_map(fn ($part) => $part instanceof TextRun ? $part->text : '', $value)),
            is_scalar($value) || $value instanceof \Stringable => (string) $value,
            default => '',
        };
    }

    private function describeType(CheckListField $field): string
    {
        return match ($field->getFormType()) {
            'text' => 'Tekst',
            'textarea' => 'Tekst (meerdere regels)',
            'email' => 'E-mailadres',
            'number' => 'Getal',
            'url' => 'Link',
            'date' => 'Datum',
            'time' => 'Tijd',
            'select', 'radio' => 'Keuze',
            'checkbox' => $field->getAnswers()->count() === 1 ? 'Aanvinken' : 'Meerdere keuzes',
            default => ucfirst($field->getFormType()),
        };
    }

    private function describeInput(CheckListField $field): string
    {
        $parts = match ($field->getFormType()) {
            'select', 'radio' => ['Eén van: ' . $this->choiceLabels($field)],
            'checkbox' => $field->getAnswers()->count() === 1
                ? [sprintf('"%s" of leeg laten.', $this->choiceLabels($field))]
                : ['Eén of meer van: ' . $this->choiceLabels($field) . '. Scheid meerdere keuzes met een puntkomma (;).'],
            'date' => ['Een datum, bijvoorbeeld ' . (new \DateTimeImmutable('2026-12-31'))->format(strtr($this->getIcuDateFormat($field), ['yyyy' => 'Y', 'MM' => 'm', 'dd' => 'd'])) . '.'],
            'time' => ['Een tijd, bijvoorbeeld 14:30.'],
            'number' => ['Een heel getal van 0 of hoger.'],
            'email' => ['Een e-mailadres, bijvoorbeeld naam@voorbeeld.nl.'],
            'url' => ['Een webadres, bijvoorbeeld https://www.voorbeeld.nl.'],
            default => [],
        };

        $options = $field->getOptionsAsArray();
        if (!empty($options['maxlength']) && (int) ($options['maxLengthValue'] ?? 0) > 0) {
            $parts[] = sprintf('Maximaal %d tekens.', (int) $options['maxLengthValue']);
        }
        if (trim((string) ($options['defaultValue'] ?? '')) !== '') {
            $parts[] = sprintf('Leeg = "%s".', trim((string) $options['defaultValue']));
        }
        if ($field->getIntroText()) {
            $parts[] = trim(strip_tags($field->getIntroText()));
        }

        return implode(' ', $parts);
    }

    /** Same as CheckListItemType::getIcuDateFormat(): the date format of the field, e.g. "dd-MM-yyyy" */
    private function getIcuDateFormat(CheckListField $field): string
    {
        $dateFormat = (string) ($field->getOptionsAsArray()['dateFormat'] ?? '');
        $dateFormat = trim((string) preg_replace('/\s*\(.*\)$/', '', $dateFormat));

        return $dateFormat !== '' ? $dateFormat : 'yyyy-MM-dd';
    }

    /** Readonly and disabled fields can't be filled in on the form, so they can't be imported either */
    private function isLocked(CheckListField $field): bool
    {
        $options = $field->getOptionsAsArray();

        return in_array($field->getFormType(), self::LOCKABLE_TYPES, true)
            && (!empty($options['readonly']) || !empty($options['disabled']));
    }

    private function truncate(string $text, int $length): string
    {
        return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 1) . '…' : $text;
    }
}
