<?php

namespace App\Support;

class FormFieldCalculation
{
    /** @param array<int, array<string, mixed>> $fields
     *  @param array<string, mixed> $values
     *  @return array<string, int|float|null>
     */
    public static function calculate(array $fields, array $values): array
    {
        $calculated = [];

        foreach ($fields as $field) {
            if (($field['type'] ?? null) !== 'calculated' || ! is_string($field['key'] ?? null)) {
                continue;
            }

            $formula = $field['calculation'] ?? [];
            $leftKey = $formula['left'] ?? null;
            $rightKey = $formula['right'] ?? null;
            $operation = $formula['operation'] ?? null;
            $left = is_string($leftKey) ? ($values[$leftKey] ?? null) : null;
            $right = is_string($rightKey) ? ($values[$rightKey] ?? null) : null;

            if (! is_numeric($left) || ! is_numeric($right)) {
                $result = null;
            } else {
                $left = (float) $left;
                $right = (float) $right;
                $result = match ($operation) {
                    'add' => $left + $right,
                    'subtract' => $left - $right,
                    'multiply' => $left * $right,
                    'divide' => $right == 0.0 ? null : $left / $right,
                    default => null,
                };
            }

            $calculated[$field['key']] = $result;
            $values[$field['key']] = $result;
        }

        return $calculated;
    }
}
