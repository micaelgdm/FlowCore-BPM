<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Http\UploadedFile;

class FormFieldCondition
{
    /** @param array<string, mixed> $field
     *  @param array<string, mixed> $values
     */
    public static function isVisible(array $field, array $values): bool
    {
        $condition = $field['visible_when'] ?? null;
        if (! is_array($condition)) {
            return true;
        }

        // Compatibility for schemas created by the previous single equality rule.
        if (isset($condition['field'])) {
            $condition = [
                'mode' => 'hidden_unless',
                'match' => 'all',
                'conditions' => [[
                    'field' => $condition['field'],
                    'operator' => 'eq',
                    'value' => $condition['equals'] ?? '',
                ]],
            ];
        }

        $mode = $condition['mode'] ?? 'always';
        $conditions = $condition['conditions'] ?? [];
        if ($mode === 'always' || ! is_array($conditions) || $conditions === []) {
            return true;
        }

        $matches = [];
        foreach ($conditions as $rule) {
            if (! is_array($rule) || ! is_string($rule['field'] ?? null)) {
                $matches[] = false;
                continue;
            }

            $current = Arr::get($values, $rule['field']);
            $expected = (string) ($rule['value'] ?? '');
            if ($current instanceof UploadedFile || $current === null || $current === '') {
                $matches[] = in_array($rule['operator'] ?? 'eq', ['neq', 'not_contains'], true);
                continue;
            }

            $current = is_scalar($current) ? (string) $current : '';
            $matches[] = match ($rule['operator'] ?? 'eq') {
                'neq' => $current !== $expected,
                'contains' => str_contains(mb_strtolower($current), mb_strtolower($expected)),
                'not_contains' => ! str_contains(mb_strtolower($current), mb_strtolower($expected)),
                default => $current === $expected,
            };
        }

        $matched = ($condition['match'] ?? 'all') === 'any'
            ? in_array(true, $matches, true)
            : ! in_array(false, $matches, true);

        return $mode === 'shown_unless' ? ! $matched : $matched;
    }
}
