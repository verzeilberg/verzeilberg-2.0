<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\CheckListField;
use App\Entity\CheckListFieldType;
use App\Service\CheckListConditionEvaluator;
use PHPUnit\Framework\TestCase;

class CheckListConditionEvaluatorTest extends TestCase
{
    private CheckListConditionEvaluator $evaluator;
    private int $nextId = 1;

    protected function setUp(): void
    {
        $this->evaluator = new CheckListConditionEvaluator();
    }

    public function testFieldWithoutConditionIsVisible(): void
    {
        $name = $this->field('naam', 'text');

        $this->assertSame([], $this->hidden([$name], []));
    }

    public function testFieldIsShownOnlyWhenTheSourceHasOneOfTheValues(): void
    {
        $type = $this->field('type', 'radio');
        $company = $this->field('bedrijf', 'text', ['field' => 'type', 'values' => ['zakelijk']]);

        $this->assertArrayHasKey($company->getId(), $this->hidden([$type, $company], ['field_type' => 'particulier']));
        $this->assertArrayHasKey($company->getId(), $this->hidden([$type, $company], []));
        $this->assertSame([], $this->hidden([$type, $company], ['field_type' => 'zakelijk']));
    }

    public function testCheckboxSourceMatchesAnySelectedValue(): void
    {
        $hobbies = $this->field('hobbies', 'checkbox');
        $club = $this->field('club', 'text', ['field' => 'hobbies', 'values' => ['sport', 'muziek']]);

        $this->assertSame([], $this->hidden([$hobbies, $club], ['field_hobbies' => ['lezen', 'muziek']]));
        $this->assertArrayHasKey($club->getId(), $this->hidden([$hobbies, $club], ['field_hobbies' => ['lezen']]));
    }

    public function testFieldInsideAHiddenFieldsetIsHidden(): void
    {
        $type = $this->field('type', 'radio');
        $fieldset = $this->field('bedrijfsgegevens', 'fieldset', ['field' => 'type', 'values' => ['zakelijk']]);
        $kvk = $this->field('kvk', 'text', null, $fieldset);

        $hidden = $this->hidden([$type, $fieldset, $kvk], ['field_type' => 'particulier']);

        $this->assertArrayHasKey($fieldset->getId(), $hidden);
        $this->assertArrayHasKey($kvk->getId(), $hidden);
    }

    public function testFieldDependingOnAHiddenFieldIsHidden(): void
    {
        $type = $this->field('type', 'radio');
        $size = $this->field('grootte', 'select', ['field' => 'type', 'values' => ['zakelijk']]);
        $employees = $this->field('medewerkers', 'number', ['field' => 'grootte', 'values' => ['groot']]);

        // "grootte" still has a (stale) value, but it is hidden itself
        $hidden = $this->hidden([$type, $size, $employees], ['field_type' => 'particulier', 'field_grootte' => 'groot']);

        $this->assertArrayHasKey($employees->getId(), $hidden);
    }

    public function testConditionsThatReferToEachOtherDontLoopForever(): void
    {
        $a = $this->field('a', 'radio', ['field' => 'b', 'values' => ['ja']]);
        $b = $this->field('b', 'radio', ['field' => 'a', 'values' => ['ja']]);

        $hidden = $this->hidden([$a, $b], ['field_a' => 'ja', 'field_b' => 'ja']);

        $this->assertCount(2, $hidden);
    }

    public function testConditionOnAnUnknownFieldHidesTheField(): void
    {
        $orphan = $this->field('weeskind', 'text', ['field' => 'bestaat_niet', 'values' => ['x']]);

        $this->assertArrayHasKey($orphan->getId(), $this->hidden([$orphan], []));
    }

    /**
     * @param CheckListField[]     $fields
     * @param array<string, mixed> $content
     *
     * @return array<int, true>
     */
    private function hidden(array $fields, array $content): array
    {
        return $this->evaluator->getHiddenFieldIdsForContent($fields, $content);
    }

    /** @param array{field: string, values: list<string>}|null $condition */
    private function field(string $name, string $formType, ?array $condition = null, ?CheckListField $parent = null): CheckListField
    {
        $field = new CheckListField();
        $field->setName($name);
        $field->setFormFieldName($name);
        $field->setFieldType((new CheckListFieldType())->setName($formType)->setFormType($formType));
        $field->setOptionsAsArray($condition ? ['condition' => $condition] : []);
        if ($parent) {
            $field->setParent($parent);
        }
        // Entities get their id from the database; set one so hidden fields can be identified
        (new \ReflectionProperty(CheckListField::class, 'id'))->setValue($field, $this->nextId++);

        return $field;
    }
}
