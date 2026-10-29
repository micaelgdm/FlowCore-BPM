<?php

namespace App\Http\Controllers;

use App\Models\ProcessDefinition;
use App\Models\ProcessGroup;
use App\Models\User;
use App\Models\AuditEvent;
use App\Support\FormVisibilityRule;
use DOMDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProcessTaskAssignmentController extends Controller
{
    public function edit(ProcessDefinition $process): View|RedirectResponse
    {
        $postedAssignments = collect(old('assignments', []))
            ->filter(fn ($assignment): bool => is_array($assignment) && is_string($assignment['task_id'] ?? null))
            ->mapWithKeys(fn (array $assignment): array => [$assignment['task_id'] => [
                'type' => $assignment['type'] ?? 'group',
                'group_id' => (int) ($assignment['group_id'] ?? 0),
                'user_id' => (int) ($assignment['user_id'] ?? 0),
            ]])
            ->all();
        $postedTaskForms = collect(old('task_forms', []))
            ->filter(fn ($taskForm): bool => is_array($taskForm) && is_string($taskForm['task_id'] ?? null))
            ->mapWithKeys(fn (array $taskForm): array => [$taskForm['task_id'] => $taskForm])
            ->all();
        $postedGatewayRoutes = collect(old('gateway_routes', []))
            ->filter(fn ($route): bool => is_array($route) && is_string($route['gateway_id'] ?? null) && is_string($route['flow_id'] ?? null))
            ->groupBy('gateway_id')
            ->mapWithKeys(fn ($routes, string $gatewayId): array => [$gatewayId => $routes->mapWithKeys(fn (array $route): array => [$route['flow_id'] => $route['outcome'] ?? ''])->all()])
            ->all();
        $gatewayBranches = self::decisionGateways($process->bpmn_xml ?? '');

        return view('processes.tasks', [
            'process' => $process,
            'readOnly' => $process->status !== 'draft',
            'userTasks' => self::userTasks($process->bpmn_xml ?? ''),
            'gatewayBranches' => $gatewayBranches,
            'hasInclusiveGateways' => collect($gatewayBranches)->contains(fn (array $gateway): bool => $gateway['type'] === 'inclusive'),
            'groups' => ProcessGroup::query()->orderBy('name')->get(),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']),
            'assignments' => session()->hasOldInput('assignments')
                ? $postedAssignments
                : collect($userTasks = self::userTasks($process->bpmn_xml ?? ''))->mapWithKeys(function ($task, string $taskId) use ($process): array {
                    $groupId = $process->task_group_assignments[$taskId] ?? null;
                    $userId = $process->task_user_assignments[$taskId] ?? null;
                    return [$taskId => [
                        'type' => $userId ? 'user' : 'group',
                        'group_id' => (int) ($groupId ?? 0),
                        'user_id' => (int) ($userId ?? 0),
                    ]];
                })->all(),
            'taskForms' => session()->hasOldInput('task_forms') ? $postedTaskForms : ($process->task_form_schemas ?? []),
            'gatewayRoutes' => session()->hasOldInput('gateway_routes') ? $postedGatewayRoutes : ($process->gateway_routes ?? []),
        ]);
    }

    public function update(Request $request, ProcessDefinition $process): RedirectResponse
    {
        abort_unless($process->status === 'draft', 409, 'As equipes de uma versão publicada são imutáveis.');
        $userTasks = self::userTasks($process->bpmn_xml ?? '');
        $gatewayBranches = self::decisionGateways($process->bpmn_xml ?? '');

        $validator = Validator::make($request->all(), [
            'assignments' => ['nullable', 'array', 'max:100'],
            'assignments.*.task_id' => ['required', 'string', 'distinct', 'max:255'],
            'assignments.*.type' => ['required', 'in:group,user'],
            'assignments.*.group_id' => ['nullable', 'integer'],
            'assignments.*.user_id' => ['nullable', 'integer'],
            'task_forms' => ['nullable', 'array', 'max:100'],
            'task_forms.*.task_id' => ['required', 'string', 'distinct', 'max:255'],
            'task_forms.*.kind' => ['required', 'in:work,approval'],
            'task_forms.*.show_process_data' => ['nullable', 'boolean'],
            'task_forms.*.sla_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'task_forms.*.fields' => ['nullable', 'array', 'max:30'],
            'task_forms.*.fields.*.key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{0,49}$/', 'not_in:idempotency_key,_token,_method'],
            'task_forms.*.fields.*.label' => ['required', 'string', 'max:100'],
            'task_forms.*.fields.*.type' => ['required', 'in:text,textarea,number,date,select,boolean,file'],
            'task_forms.*.fields.*.required' => ['nullable', 'boolean'],
            'task_forms.*.fields.*.options' => ['nullable', 'string', 'max:2000'],
            'task_forms.*.fields.*.visible_when_mode' => ['nullable', 'in:always,hidden_unless,shown_unless'],
            'task_forms.*.fields.*.visible_when_match' => ['nullable', 'in:all,any'],
            'task_forms.*.fields.*.conditions' => ['nullable', 'array', 'max:10'],
            'task_forms.*.fields.*.conditions.*.field' => ['nullable', 'string', 'max:50'],
            'task_forms.*.fields.*.conditions.*.operator' => ['nullable', 'in:eq,neq,contains,not_contains'],
            'task_forms.*.fields.*.conditions.*.value' => ['nullable', 'string', 'max:255'],
            'gateway_routes' => ['nullable', 'array', 'max:100'],
            'gateway_routes.*.gateway_id' => ['required', 'string', 'max:255'],
            'gateway_routes.*.flow_id' => ['required', 'string', 'distinct', 'max:255'],
            'gateway_routes.*.outcome' => ['required', 'string', 'max:255'],
        ]);

        $validator->after(function ($validator) use ($request, $userTasks, $gatewayBranches): void {
            $assignments = collect($request->input('assignments', []))
                ->filter(fn ($assignment): bool => is_array($assignment) && is_string($assignment['task_id'] ?? null))
                ->keyBy('task_id');
            $taskForms = collect($request->input('task_forms', []))
                ->filter(fn ($taskForm): bool => is_array($taskForm) && is_string($taskForm['task_id'] ?? null))
                ->keyBy('task_id');

            foreach ($assignments->keys()->merge($taskForms->keys())->unique() as $taskId) {
                if (! array_key_exists($taskId, $userTasks)) {
                    $validator->errors()->add('assignments', 'O diagrama mudou. Reabra a configuração de tarefas e tente novamente.');
                    return;
                }
            }

            foreach ($userTasks as $taskId => $task) {
                $assignment = $assignments->get($taskId);
                if (! is_array($assignment)) {
                    $validator->errors()->add("assignments.{$taskId}", "Escolha um responsável para a tarefa ‘{$task['name']}’.");
                } elseif (($assignment['type'] ?? null) === 'group') {
                    if (! filled($assignment['group_id'] ?? null)) {
                        $validator->errors()->add("assignments.{$taskId}.group_id", "Escolha um grupo para a tarefa ‘{$task['name']}’.");
                    } elseif (! ProcessGroup::query()->whereKey($assignment['group_id'])->exists()) {
                        $validator->errors()->add("assignments.{$taskId}.group_id", "O grupo escolhido para ‘{$task['name']}’ não existe mais.");
                    }
                } elseif (($assignment['type'] ?? null) === 'user') {
                    if (! filled($assignment['user_id'] ?? null)) {
                        $validator->errors()->add("assignments.{$taskId}.user_id", "Escolha um usuário para a tarefa ‘{$task['name']}’.");
                    } elseif (! User::query()->whereKey($assignment['user_id'])->where('is_active', true)->exists()) {
                        $validator->errors()->add("assignments.{$taskId}.user_id", "O usuário escolhido para ‘{$task['name']}’ está inativo ou não existe mais.");
                    }
                }

                if (! $taskForms->has($taskId)) {
                    $validator->errors()->add("task_forms.{$taskId}", "Configure o formulário da tarefa ‘{$task['name']}’.");
                    continue;
                }

                $fields = collect($taskForms->get($taskId)['fields'] ?? [])
                    ->filter(fn ($field): bool => is_array($field));
                $taskFormIndex = collect($request->input('task_forms', []))->search(fn ($taskForm): bool => is_array($taskForm) && ($taskForm['task_id'] ?? null) === $taskId);
                FormVisibilityRule::validateFields($fields->all(), "task_forms.{$taskFormIndex}.fields", function (string $key, string $message) use ($validator): void {
                    $validator->errors()->add($key, $message);
                });
                if ($fields->pluck('key')->filter()->duplicates()->isNotEmpty()) {
                    $validator->errors()->add("task_forms.{$taskId}.fields", "Os identificadores de campos devem ser únicos dentro da tarefa ‘{$task['name']}’.");
                }

                foreach ($fields as $fieldIndex => $field) {
                    if (! is_array($field) || ($field['type'] ?? null) !== 'select') {
                        continue;
                    }

                    $options = preg_split('/\R/u', trim((string) ($field['options'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                    $options = array_values(array_unique(array_map('trim', $options)));
                    if (count($options) < 2 || collect($options)->contains(fn ($option): bool => mb_strlen($option) > 100)) {
                        $validator->errors()->add("task_forms.{$taskId}.fields.{$fieldIndex}.options", 'Informe pelo menos duas opções, uma por linha, com até 100 caracteres cada.');
                    }
                }
            }

            $approvalConfigured = collect($request->input('task_forms', []))
                ->contains(fn ($taskForm): bool => is_array($taskForm) && ($taskForm['kind'] ?? null) === 'approval');
            $routes = collect($request->input('gateway_routes', []))
                ->filter(fn ($route): bool => is_array($route) && is_string($route['gateway_id'] ?? null) && is_string($route['flow_id'] ?? null))
                ->groupBy('gateway_id');

            if (! $approvalConfigured && $routes->isNotEmpty()) {
                $validator->errors()->add('gateway_routes', 'Configure uma etapa de aprovação antes de definir saídas por decisão.');
                return;
            }

            foreach ($routes->keys() as $gatewayId) {
                if (! array_key_exists($gatewayId, $gatewayBranches)) {
                    $validator->errors()->add('gateway_routes', 'O diagrama mudou. Reabra a configuração de saídas e tente novamente.');
                    return;
                }
            }

            $hasDecisionGateway = false;
            foreach ($gatewayBranches as $gatewayId => $gateway) {
                if (! $approvalConfigured) {
                    continue;
                }

                $gatewayRoutes = $routes->get($gatewayId, collect());
                $flowIds = $gatewayRoutes->pluck('flow_id')->all();
                $validFlowIds = array_column($gateway['flows'], 'id');

                if (count($flowIds) !== count($validFlowIds) || array_diff($validFlowIds, $flowIds) !== [] || array_diff($flowIds, $validFlowIds) !== []) {
                    $validator->errors()->add("gateway_routes.{$gatewayId}", "Configure uma saída para cada caminho do gateway ‘{$gateway['name']}’.");
                    continue;
                }

                $outcomes = $gatewayRoutes->pluck('outcome');
                if ($outcomes->duplicates()->isNotEmpty()) {
                    $validator->errors()->add("gateway_routes.{$gatewayId}", "Cada saída do gateway ‘{$gateway['name']}’ deve ter um resultado diferente.");
                }

                foreach ($gatewayRoutes as $routeIndex => $route) {
                    $outcome = $route['outcome'] ?? '';
                    if (in_array($outcome, ['approved', 'rejected', 'default'], true)) {
                        continue;
                    }

                    $targetTaskId = str_starts_with($outcome, 'return:') ? substr($outcome, 7) : '';
                    if (! isset($userTasks[$targetTaskId]) || ($taskForms->get($targetTaskId)['kind'] ?? null) !== 'work') {
                        $validator->errors()->add("gateway_routes.{$routeIndex}.outcome", 'Escolha uma tarefa de preenchimento válida para retorno da correção.');
                    }
                }

                $hasReturnPath = $outcomes->contains(fn ($outcome): bool => is_string($outcome) && str_starts_with($outcome, 'return:'));
                if ($outcomes->contains('approved') && ($outcomes->contains('rejected') || $hasReturnPath)) {
                    $hasDecisionGateway = true;
                }
            }

            if ($approvalConfigured && $gatewayBranches !== [] && ! $hasDecisionGateway) {
                $validator->errors()->add('gateway_routes', 'Configure em um gateway uma saída Aprovada e uma saída de rejeição ou retorno para correção.');
            }
        });

        $data = $validator->validate();
        $groupAssignments = [];
        $userAssignments = [];
        foreach ($data['assignments'] ?? [] as $assignment) {
            if (($assignment['type'] ?? 'group') === 'user') {
                $userAssignments[$assignment['task_id']] = (int) $assignment['user_id'];
            } else {
                $groupAssignments[$assignment['task_id']] = (int) $assignment['group_id'];
            }
        }
        $taskForms = collect($data['task_forms'] ?? [])->mapWithKeys(function (array $taskForm): array {
            $fields = collect($taskForm['fields'] ?? [])->map(function (array $field): array {
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

                if ($condition = FormVisibilityRule::normalize($field)) {
                    $normalized['visible_when'] = $condition;
                }

                return $normalized;
            })->values()->all();

            $normalizedTaskForm = [
                'kind' => $taskForm['kind'],
                'show_process_data' => filter_var($taskForm['show_process_data'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'fields' => $fields,
            ];
            if (filled($taskForm['sla_hours'] ?? null)) {
                $normalizedTaskForm['sla_hours'] = (int) $taskForm['sla_hours'];
            }

            return [$taskForm['task_id'] => $normalizedTaskForm];
        })->all();
        $gatewayRoutes = collect($data['gateway_routes'] ?? [])->groupBy('gateway_id')->mapWithKeys(function ($routes, string $gatewayId): array {
            return [$gatewayId => $routes->mapWithKeys(fn (array $route): array => [$route['flow_id'] => $route['outcome']])->all()];
        })->all();
        $previousConfiguration = [
            'task_group_assignments' => $process->task_group_assignments ?? [],
            'task_user_assignments' => $process->task_user_assignments ?? [],
            'task_form_schemas' => $process->task_form_schemas ?? [],
            'gateway_routes' => $process->gateway_routes ?? [],
        ];
        $nextConfiguration = [
            'task_group_assignments' => $groupAssignments,
            'task_user_assignments' => $userAssignments,
            'task_form_schemas' => $taskForms,
            'gateway_routes' => $gatewayRoutes,
        ];
        DB::transaction(function () use ($process, $request, $previousConfiguration, $nextConfiguration): void {
            $process->forceFill($nextConfiguration + ['updated_by' => $request->user()->id])->save();

            if ($previousConfiguration !== $nextConfiguration) {
                $changedSections = collect($previousConfiguration)
                    ->keys()
                    ->filter(fn (string $section): bool => $previousConfiguration[$section] !== $nextConfiguration[$section])
                    ->values()
                    ->all();
                AuditEvent::query()->create([
                    'actor_id' => $request->user()->id,
                    'actor_name' => $request->user()->name,
                    'action' => 'process.tasks.updated',
                    'subject' => "{$process->name} · V{$process->version}",
                    'details' => ['changed_sections' => $changedSections],
                ]);
            }
        });

        return redirect()->route('master.processes.tasks.edit', $process)->with('status', 'Responsáveis, formulários e saídas salvos. Publique o rascunho para usar esta configuração.');
    }

    /** @return array<string, array{id: string, name: string, documentation: string}> */
    public static function userTasks(string $xml): array
    {
        $document = new DOMDocument();
        $document->resolveExternals = false;
        $document->substituteEntities = false;

        if ($xml === '' || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml) || ! @$document->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
            return [];
        }

        $tasks = [];
        foreach ($document->getElementsByTagNameNS('http://www.omg.org/spec/BPMN/20100524/MODEL', 'userTask') as $task) {
            $id = $task->getAttribute('id');

            if ($id !== '') {
                $documentation = '';
                foreach ($task->childNodes as $child) {
                    if ($child instanceof \DOMElement
                        && $child->namespaceURI === 'http://www.omg.org/spec/BPMN/20100524/MODEL'
                        && $child->localName === 'documentation') {
                        $documentation = trim($child->textContent);
                        break;
                    }
                }

                $tasks[$id] = [
                    'id' => $id,
                    'name' => $task->getAttribute('name') ?: $id,
                    'documentation' => $documentation,
                ];
            }
        }

        return $tasks;
    }

    /** @return array<string, array{id: string, name: string, type: string, flows: array<int, array{id: string, name: string}>}> */
    public static function decisionGateways(string $xml): array
    {
        $document = new DOMDocument();
        $document->resolveExternals = false;
        $document->substituteEntities = false;
        $namespace = 'http://www.omg.org/spec/BPMN/20100524/MODEL';

        if ($xml === '' || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml) || ! @$document->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
            return [];
        }

        $names = [];
        foreach ($document->getElementsByTagNameNS($namespace, '*') as $element) {
            if ($element instanceof \DOMElement && $element->getAttribute('id') !== '') {
                $names[$element->getAttribute('id')] = $element->getAttribute('name') ?: $element->localName;
            }
        }

        $flowsByGateway = [];
        foreach ($document->getElementsByTagNameNS($namespace, 'sequenceFlow') as $flow) {
            $sourceId = $flow->getAttribute('sourceRef');
            $flowId = $flow->getAttribute('id');
            if ($sourceId !== '' && $flowId !== '') {
                $flowsByGateway[$sourceId][] = [
                    'id' => $flowId,
                    'name' => $flow->getAttribute('name') ?: ($names[$flow->getAttribute('targetRef')] ?? $flowId),
                ];
            }
        }

        $gateways = [];
        foreach (['exclusiveGateway' => 'exclusive', 'inclusiveGateway' => 'inclusive'] as $elementName => $type) {
            foreach ($document->getElementsByTagNameNS($namespace, $elementName) as $gateway) {
                $gatewayId = $gateway->getAttribute('id');
                $flows = $flowsByGateway[$gatewayId] ?? [];
                if ($gatewayId !== '' && count($flows) > 1) {
                    $gateways[$gatewayId] = [
                        'id' => $gatewayId,
                        'name' => $gateway->getAttribute('name') ?: $gatewayId,
                        'type' => $type,
                        'flows' => $flows,
                    ];
                }
            }
        }

        return $gateways;
    }
}
