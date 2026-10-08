<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CheckList;
use App\Entity\CheckListField;
use App\Entity\CheckListItem;
use App\Repository\CheckListItemRepository;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Options as CsvOptions;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\WriterInterface;
use OpenSpout\Writer\XLSX\Options as XlsxOptions;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Exports all answers of a checklist as CSV or Excel (xlsx): one row per item, one column per field.
 */
class CheckListExporter
{
    public const FORMATS = ['csv', 'xlsx'];

    public function __construct(
        private CheckListService $checkListService,
        private CheckListItemRepository $checkListItemRepository,
        private SluggerInterface $slugger,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     */
    /**
     * @param CheckListItem[]|null $items the items to export (e.g. the current selection of the answers list); null = all
     */
    public function createResponse(CheckList $checkList, string $format, ?array $items = null): StreamedResponse
    {
        if (!in_array($format, self::FORMATS, true)) {
            throw new \InvalidArgumentException(sprintf('Unknown export format "%s".', $format));
        }

        $filename = sprintf('%s-%s.%s', strtolower((string) $this->slugger->slug($checkList->getName())), date('Y-m-d'), $format);

        $response = new StreamedResponse(function () use ($checkList, $format, $items): void {
            $writer = $format === 'xlsx'
                ? new XlsxWriter($this->createXlsxOptions())
                // Semicolon: Dutch Excel uses it as list separator, so the file opens in columns
                : new CsvWriter(new CsvOptions(FIELD_DELIMITER: ';'));
            $writer->openToFile('php://output');
            $this->writeRows($writer, $checkList, $format, $items ?? $this->checkListItemRepository->findAllByCheckList($checkList));
            $writer->close();
        });

        $response->headers->set('Content-Type', $format === 'xlsx'
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename));
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    /**
     * @param CheckListItem[] $items
     */
    private function writeRows(WriterInterface $writer, CheckList $checkList, string $format, array $items): void
    {
        $columns = $this->getColumns($checkList);
        $values = $this->checkListService->getItemOverviewValues($items, array_column($columns, 'field'));

        $header = array_merge(['ID', 'Created', 'Changed'], array_column($columns, 'header'));
        $headerStyle = $format === 'xlsx' ? (new Style())->withFontBold(true) : null;
        $writer->addRow(new Row(array_map(fn (string $title) => new StringCell($title, $headerStyle), $header)));

        foreach ($items as $item) {
            $cells = [
                new NumericCell((int) $item->getId()),
                $this->textCell($item->getDateCreated()->format('Y-m-d H:i'), $format),
                $this->textCell($item->getDateChanged()?->format('Y-m-d H:i'), $format),
            ];
            foreach ($columns as $column) {
                $cells[] = $this->valueCell($item, $column['field'], $values[$item->getId()][$column['field']->getId()] ?? null, $format);
            }
            $writer->addRow(new Row($cells));
        }
    }

    /**
     * Fields with a value, in the same (tree) order as the item detail page.
     * Fields inside a fieldset get the fieldset name in their header.
     *
     * @return list<array{field: CheckListField, header: string}>
     */
    private function getColumns(CheckList $checkList): array
    {
        $fields = $this->checkListService->getCheckListFieldsByCheckListWithChildren($checkList);
        $columns = [];
        $walk = function (iterable $fields, string $prefix) use (&$walk, &$columns): void {
            foreach ($fields as $field) {
                if ($field->getFormType() === 'fieldset') {
                    $walk($field->getChildren(), $prefix . $field->getName() . ' › ');
                    continue;
                }
                if ($field->getFormType() !== 'hidden') {
                    $columns[] = ['field' => $field, 'header' => $prefix . $field->getName()];
                }
                $walk($field->getChildren(), $prefix);
            }
        };
        $walk(array_filter($fields, fn (CheckListField $field) => $field->getParent() === null), '');

        return $columns;
    }

    private function valueCell(CheckListItem $item, CheckListField $field, ?string $value, string $format): Cell
    {
        if ($value === null) {
            return new EmptyCell(null);
        }

        if ($field->getFormType() === 'number' && is_numeric($value)) {
            return new NumericCell(str_contains($value, '.') ? (float) $value : (int) $value);
        }

        if ($field->getFormType() === 'file') {
            // Uploads aren't public: link to the protected download (opening it requires being logged in)
            $url = $this->urlGenerator->generate(
                'app_checklist_item_file',
                ['id' => $item->getId(), 'field' => $field->getFormFieldName()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            return $format === 'xlsx' ? new StringCell($url, null, null, $url) : $this->textCell($url, $format);
        }

        if ($field->getFormType() === 'url' && $format === 'xlsx') {
            return new StringCell($value, null, null, $value);
        }

        return $this->textCell($value, $format);
    }

    /**
     * Always a text cell, never a formula: answers are user input.
     * In CSV, a value starting with = + - @ is prefixed with ' so spreadsheet programs don't run it as a formula.
     */
    private function textCell(?string $value, string $format): Cell
    {
        if ($value === null || $value === '') {
            return new EmptyCell(null);
        }

        if ($format === 'csv' && preg_match('/^[=+\-@\t\r]/', $value)) {
            $value = "'" . $value;
        }

        return new StringCell($value);
    }

    private function createXlsxOptions(): XlsxOptions
    {
        return new XlsxOptions(DEFAULT_COLUMN_WIDTH: 20);
    }
}
