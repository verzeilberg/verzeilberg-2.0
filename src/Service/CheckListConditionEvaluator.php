<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CheckListField;

/**
 * Decides whether a field is shown, based on its "show only when" condition:
 * options.condition = {"field": "<formFieldName of a radio/select/checkbox field>", "values": ["a", "b"]}.
 *
 * A field is hidden when its own condition is not met, when a parent fieldset is hidden,
 * or when the field its condition depends on is hidden itself.
 * The same rules are applied in the browser (see _item_form_scripts.html.twig).
 */
class CheckListConditionEvaluator
{
    /** Field types whose answers can be used in a condition */
    public const CONDITION_SOURCE_TYPES = ['radio', 'select', 'checkbox'];

    /**
     * @return array{field: string, values: list<string>}|null
     */
    public static function getCondition(CheckListField $field): ?array
    {
        $condition = $field->getOptionsAsArray()['condition'] ?? null;
        if (!is_array($condition) || empty($condition['field']) || empty($condition['values']) || !is_array($condition['values'])) {
            return null;
        }

        return [
            'field' => (string) $condition['field'],
            'values' => array_values(array_map('strval', $condition['values'])),
        ];
    }

    /**
     * IDs of the fields that are hidden for the given values.
     *
     * @param iterable<CheckListField> $fields all fields of the checklist
     * @param callable(string): mixed  $valueOf returns the current value of a field by its formFieldName (string, array or null)
     *
     * @return array<int, true>
     */
    public function getHiddenFieldIds(iterable $fields, callable $valueOf): array
    {
        $byName = [];
        foreach ($fields as $field) {
            $byName[$field->getFormFieldName()] = $field;
        }

        $hidden = [];
        foreach ($byName as $field) {
            if (!$this->isVisible($field, $byName, $valueOf, [])) {
                $hidden[(int) $field->getId()] = true;
            }
        }

        return $hidden;
    }

    /**
     * Same as getHiddenFieldIds(), for stored item content ("field_<name>" => value).
     *
     * @param iterable<CheckListField> $fields
     * @param array<string, mixed>     $itemContent
     *
     * @return array<int, true>
     */
    public function getHiddenFieldIdsForContent(iterable $fields, array $itemContent): array
    {
        return $this->getHiddenFieldIds($fields, fn (string $name) => $itemContent['field_' . $name] ?? null);
    }

    /**
     * @param array<string, CheckListField> $byName
     * @param array<string, true>           $visiting guards against conditions that refer to each other
     */
    private function isVisible(CheckListField $field, array $byName, callable $valueOf, array $visiting): bool
    {
        $name = $field->getFormFieldName();
        if (isset($visiting[$name])) {
            return false;
        }
        $visiting[$name] = true;

        $parent = $field->getParent();
        if ($parent !== null && !$this->isVisible($parent, $byName, $valueOf, $visiting)) {
            return false;
        }

        $condition = self::getCondition($field);
        if ($condition === null) {
            return true;
        }

        $source = $byName[$condition['field']] ?? null;
        if ($source === null || !$this->isVisible($source, $byName, $valueOf, $visiting)) {
            return false;
        }

        $value = $valueOf($condition['field']);
        $selected = is_array($value) ? array_map('strval', $value) : ($value === null || $value === '' ? [] : [(string) $value]);

        return array_intersect($selected, $condition['values']) !== [];
    }
}
