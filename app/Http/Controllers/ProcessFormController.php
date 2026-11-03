<?php

namespace App\Http\Controllers;

use App\Models\ProcessDefinition;
use App\Models\AuditEvent;
use App\Support\FormVisibilityRule;
use App\Support\FormFieldCalculation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ProcessFormController extends Controller
{
    public function edit(ProcessDefinition $process): View
    {
        return view('processes.form-builder', [
            'process' => $process,
            'fields' => $process->form_schema ?? [],
            'readOnly' => $process->status !== 'draft',
        ]);
    }

    public function update(Request $request, ProcessDefinition $process): RedirectResponse
    {
        abort_unless($process->status === 'draft', 409, 'O formulário de uma versão publicada é imutável.');

        $validator = Validator::make($request->all(), [
            'fields' => ['required', 'array', 'min:1', 'max:30'],
            'fields.*.key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{0,49}$/', 'not_in:idempotency_key,_token,_method', 'distinct'],
            'fields.*.label' => ['required', 'string', 'max:100'],
            'fields.*.type' => ['required', 'in:text,textarea,number,date,select,multiselect,user,group,calculated,boolean,file'],
            'fields.*.required' => ['nullable', 'boolean'],
            'fields.*.options' => ['nullable', 'string', 'max:2000'],
            'fields.*.calculation' => ['nullable', 'array'],
            'fields.*.calculation.left' => ['nullable', 'string', 'max:50'],
            'fields.*.calculation.right' => ['nullable', 'string', 'max:50'],
            'fields.*.calculation.operation' => ['nullable', 'in:add,subtract,multiply,divide'],
            'fields.*.visible_when_mode' => ['nullable', 'in:always,hidden_unless,shown_unless'],
            'fields.*.visible_when_match' => ['nullable', 'in:all,any'],
            'fields.*.conditions' => ['nullable', 'array', 'max:10'],
            'fields.*.conditions.*.field' => ['nullable', 'string', 'max:50'],
            'fields.*.conditions.*.operator' => ['nullable', 'in:eq,neq,contains,not_contains'],
            'fields.*.conditions.*.value' => ['nullable', 'string', 'max:255'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            $fields = (array) $request->input('fields', []);
            FormVisibilityRule::validateFields($fields, 'fields', function (string $key, string $message) use ($validator): void {
                $validator->errors()->add($key, $message);
            });
            foreach ($fields as $index => $field) {
                if (! is_array($field)) {
                    continue;
                }

                if (in_array($field['type'] ?? null, ['select', 'multiselect'], true)) {
                    $options = preg_split('/\R/u', trim((string) ($field['options'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                    $options = array_values(array_unique(array_map('trim', $options)));
                    if (count($options) < 2 || collect($options)->contains(fn ($option): bool => mb_strlen($option) > 100)) {
                        $validator->errors()->add("fields.{$index}.options", 'Informe pelo menos duas opções, uma por linha, com até 100 caracteres cada.');
                    }
                }

                if (($field['type'] ?? null) === 'calculated') {
                    $formula = $field['calculation'] ?? [];
                    foreach (['left', 'right'] as $operand) {
                        $key = $formula[$operand] ?? null;
                        $sourceIndex = null;
                        $sourceType = null;
                        foreach ($fields as $candidateIndex => $candidate) {
                            if (is_array($candidate) && ($candidate['key'] ?? null) === $key) {
                                $sourceIndex = $candidateIndex;
                                $sourceType = $candidate['type'] ?? null;
                                break;
                            }
                        }
                        if (! is_string($key) || $key === '' || $sourceIndex === null || $sourceIndex >= $index || $sourceType !== 'number') {
                            $validator->errors()->add("fields.{$index}.calculation.{$operand}", 'Selecione um campo numérico anterior.');
                        }
                    }
                    if (! in_array($formula['operation'] ?? null, ['add', 'subtract', 'multiply', 'divide'], true)) {
                        $validator->errors()->add("fields.{$index}.calculation.operation", 'Escolha uma operação válida.');
                    }
                }
            }
        });

        $data = $validator->validate();
        $fields = collect($data['fields'])->map(function (array $field): array {
            $normalized = [
                'key' => $field['key'],
                'label' => trim($field['label']),
                'type' => $field['type'],
                'required' => (bool) ($field['required'] ?? false),
            ];

            if (in_array($field['type'], ['select', 'multiselect'], true)) {
                $options = preg_split('/\R/u', trim((string) ($field['options'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $normalized['options'] = array_values(array_unique(array_map('trim', $options)));
            }

            if ($field['type'] === 'calculated') {
                $normalized['calculation'] = [
                    'left' => $field['calculation']['left'],
                    'operation' => $field['calculation']['operation'],
                    'right' => $field['calculation']['right'],
                ];
            }

            if ($condition = FormVisibilityRule::normalize($field)) {
                $normalized['visible_when'] = $condition;
            }

            return $normalized;
        })->values()->all();

        $previousFields = $process->form_schema ?? [];
        DB::transaction(function () use ($process, $request, $previousFields, $fields): void {
            $process->forceFill([
                'form_schema' => $fields,
                'updated_by' => $request->user()->id,
            ])->save();

            if ($previousFields !== $fields) {
                AuditEvent::query()->create([
                    'actor_id' => $request->user()->id,
                    'actor_name' => $request->user()->name,
                    'action' => 'process.form.updated',
                    'subject' => "{$process->name} · V{$process->version}",
                    'details' => ['previous_fields' => $previousFields, 'fields' => $fields],
                ]);
            }
        });

        return redirect()->route('master.processes.form.edit', $process)->with('status', 'Formulário salvo. Ele será publicado junto com esta versão do processo.');
    }
}
