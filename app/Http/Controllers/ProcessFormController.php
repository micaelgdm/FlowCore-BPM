<?php

namespace App\Http\Controllers;

use App\Models\ProcessDefinition;
use App\Models\AuditEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ProcessFormController extends Controller
{
    public function edit(ProcessDefinition $process): View
    {
        abort_unless($process->status === 'draft', 409, 'O formulário só pode ser configurado antes da publicação.');

        return view('processes.form-builder', [
            'process' => $process,
            'fields' => $process->form_schema ?? [],
        ]);
    }

    public function update(Request $request, ProcessDefinition $process): RedirectResponse
    {
        abort_unless($process->status === 'draft', 409, 'O formulário de uma versão publicada é imutável.');

        $validator = Validator::make($request->all(), [
            'fields' => ['required', 'array', 'min:1', 'max:30'],
            'fields.*.key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{0,49}$/', 'not_in:idempotency_key,_token,_method', 'distinct'],
            'fields.*.label' => ['required', 'string', 'max:100'],
            'fields.*.type' => ['required', 'in:text,textarea,number,date,select,boolean'],
            'fields.*.required' => ['nullable', 'boolean'],
            'fields.*.options' => ['nullable', 'string', 'max:2000'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            foreach ((array) $request->input('fields', []) as $index => $field) {
                if (! is_array($field) || ($field['type'] ?? null) !== 'select') {
                    continue;
                }

                $options = preg_split('/\R/u', trim((string) ($field['options'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $options = array_values(array_unique(array_map('trim', $options)));

                if (count($options) < 2 || collect($options)->contains(fn ($option): bool => mb_strlen($option) > 100)) {
                    $validator->errors()->add("fields.{$index}.options", 'Informe pelo menos duas opções, uma por linha, com até 100 caracteres cada.');
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

            if ($field['type'] === 'select') {
                $options = preg_split('/\R/u', trim((string) ($field['options'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $normalized['options'] = array_values(array_unique(array_map('trim', $options)));
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
