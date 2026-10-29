<?php

namespace App\Support;

use Closure;

class FormVisibilityRule
{
    /** @param array<int|string, mixed> $fields */
    public static function validateFields(array $fields, string $errorPrefix, Closure $addError): void
    {
        foreach ($fields as $fieldIndex => $field) {
            if (! is_array($field)) {
                continue;
            }

            $mode = $field['visible_when_mode'] ?? 'always';
            if (! in_array($mode, ['always', 'hidden_unless', 'shown_unless'], true)) {
                $addError("{$errorPrefix}.{$fieldIndex}.visible_when_mode", 'Escolha um modo de exibição válido.');
                continue;
            }
            if ($mode === 'always') {
                continue;
            }

            $conditions = collect($field['conditions'] ?? [])
                ->filter(fn ($condition): bool => is_array($condition) && (filled($condition['field'] ?? null) || filled($condition['value'] ?? null)));
            if ($conditions->isEmpty()) {
                $addError("{$errorPrefix}.{$fieldIndex}.conditions", 'Adicione ao menos uma condição para este modo de exibição.');
                continue;
            }

            if (! in_array($field['visible_when_match'] ?? 'all', ['all', 'any'], true)) {
                $addError("{$errorPrefix}.{$fieldIndex}.visible_when_match", 'Escolha como as condições devem ser combinadas.');
            }

            foreach ($conditions as $conditionIndex => $condition) {
                $base = "{$errorPrefix}.{$fieldIndex}.conditions.{$conditionIndex}";
                $controllerKey = $condition['field'] ?? null;
                $operator = $condition['operator'] ?? 'eq';
                $value = trim((string) ($condition['value'] ?? ''));
                if (! is_string($controllerKey) || $controllerKey === '') {
                    $addError("{$base}.field", 'Selecione um campo anterior.');
                    continue;
                }
                if (! in_array($operator, ['eq', 'neq', 'contains', 'not_contains'], true)) {
                    $addError("{$base}.operator", 'Escolha um operador válido.');
                    continue;
                }
                if ($value === '') {
                    $addError("{$base}.value", 'Informe o valor usado na condição.');
                    continue;
                }

                $controllerIndex = null;
                $controller = null;
                foreach ($fields as $candidateIndex => $candidate) {
                    if (is_array($candidate) && ($candidate['key'] ?? null) === $controllerKey) {
                        $controllerIndex = $candidateIndex;
                        $controller = $candidate;
                        break;
                    }
                }

                if (! is_array($controller) || $controllerIndex >= $fieldIndex || ($controller['type'] ?? null) === 'file') {
                    $addError("{$base}.field", 'Escolha um campo anterior existente, que não seja do tipo anexo.');
                    continue;
                }

                if (($controller['type'] ?? null) === 'select' && in_array($operator, ['eq', 'neq'], true)) {
                    $options = preg_split('/\R/u', trim((string) ($controller['options'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                    if (! in_array($value, array_map('trim', $options), true)) {
                        $addError("{$base}.value", 'O valor precisa existir nas opções do campo controlador.');
                    }
                } elseif (($controller['type'] ?? null) === 'boolean' && in_array($operator, ['eq', 'neq'], true) && ! in_array($value, ['0', '1'], true)) {
                    $addError("{$base}.value", 'Para um campo Sim ou não, use 1 para Sim ou 0 para Não.');
                }
            }
        }
    }

    /** @param array<string, mixed> $field
     *  @return array<string, mixed>|null
     */
    public static function normalize(array $field): ?array
    {
        $mode = $field['visible_when_mode'] ?? (filled($field['visible_when_field'] ?? null) ? 'hidden_unless' : 'always');
        if ($mode === 'always') {
            return null;
        }

        $conditions = collect($field['conditions'] ?? [])
            ->filter(fn ($condition): bool => is_array($condition) && filled($condition['field'] ?? null) && filled($condition['value'] ?? null))
            ->map(fn (array $condition): array => [
                'field' => $condition['field'],
                'operator' => $condition['operator'] ?? 'eq',
                'value' => trim((string) $condition['value']),
            ])->values()->all();

        // Keep schemas created by the earlier single-condition editor readable.
        if ($conditions === [] && filled($field['visible_when_field'] ?? null)) {
            $conditions = [[
                'field' => $field['visible_when_field'],
                'operator' => 'eq',
                'value' => trim((string) ($field['visible_when_value'] ?? '')),
            ]];
            $mode = 'hidden_unless';
        }

        return $conditions === [] ? null : [
            'mode' => $mode,
            'match' => $field['visible_when_match'] ?? 'all',
            'conditions' => $conditions,
        ];
    }
}
