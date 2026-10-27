<?php

namespace App\Http\Controllers;

use App\Models\ProcessInstance;
use App\Models\ProcessTaskDecision;
use App\Models\User;
use App\Services\FlowableClient;
use Illuminate\Support\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class ProcessTaskController extends Controller
{
    public function index(Request $request, FlowableClient $flowable): View
    {
        try {
            $assignee = PortalProcessController::flowableAssignee($request->user()->id);
            $activeInstances = ProcessInstance::query()
                ->with('processDefinition.authorizedUsers')
                ->where('status', 'running')
                ->whereNotNull('flowable_process_instance_id')
                ->get();

            foreach ($activeInstances as $instance) {
                if (($instance->processDefinition->task_group_assignments ?? []) !== []) {
                    continue;
                }

                $isAssignedApprover = $instance->processDefinition->authorizedUsers->contains(
                    fn (User $user): bool => $user->id === $request->user()->id && (bool) $user->pivot->can_approve,
                );

                if ($isAssignedApprover) {
                    $flowable->assignUnassignedTasks($instance->flowable_process_instance_id, $assignee);
                }
            }

            $engineTasks = $flowable->tasksForAssignee($assignee);

            foreach ($request->user()->processGroups as $group) {
                foreach ($flowable->tasksForCandidateGroup($group->flowable_group_id) as $candidateTask) {
                    if (! is_string($candidateTask['id'] ?? null) || ! is_string($candidateTask['processInstanceId'] ?? null)) {
                        continue;
                    }

                    $instance = ProcessInstance::query()
                        ->with('processDefinition.authorizedUsers')
                        ->where('flowable_process_instance_id', $candidateTask['processInstanceId'])
                        ->where('status', 'running')
                        ->first();

                    if (! $instance || ! self::userCanAccessTask($instance, $candidateTask, $request->user())) {
                        continue;
                    }

                    if (blank($candidateTask['assignee'] ?? null)) {
                        $flowable->claimTask($candidateTask['id'], $assignee);
                        $candidateTask = $flowable->task($candidateTask['id']) ?? $candidateTask;
                    }

                    if (($candidateTask['assignee'] ?? null) === $assignee) {
                        $engineTasks[] = $candidateTask;
                    }
                }
            }

            $engineTasks = collect($engineTasks)->unique('id')->values()->all();
            $processInstanceIds = collect($engineTasks)->pluck('processInstanceId')->filter()->unique()->all();
            $instances = ProcessInstance::query()
                ->with(['processDefinition.authorizedUsers', 'starter'])
                ->whereIn('flowable_process_instance_id', $processInstanceIds)
                ->get()
                ->keyBy('flowable_process_instance_id');
            $decisionsByInstance = ProcessTaskDecision::query()
                ->with('actor')
                ->whereIn('process_instance_id', $instances->pluck('id'))
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->groupBy('process_instance_id');

            $tasks = collect($engineTasks)->filter(function (array $task) use ($instances, $request): bool {
                $instance = $instances->get($task['processInstanceId'] ?? '');

                return is_array($task)
                    && is_string($task['id'] ?? null)
                    && is_string($task['processInstanceId'] ?? null)
                    && $instance instanceof ProcessInstance
                    && self::userCanAccessTask($instance, $task, $request->user());
            })->map(function (array $task) use ($instances, $decisionsByInstance): array {
                $instance = $instances->get($task['processInstanceId']);
                $schema = self::schemaForTask($instance, $task);
                $showProcessData = $schema['show_process_data'] ?? (($schema['kind'] ?? null) === 'approval');
                $decisions = $instance ? $decisionsByInstance->get($instance->id, collect()) : collect();
                $taskKey = $task['taskDefinitionKey'] ?? null;
                $returnNotice = is_string($taskKey)
                    ? $decisions->filter(fn (ProcessTaskDecision $decision): bool => $decision->decision === 'rejected'
                        && ($decision->form_data['return_to'] ?? null) === $taskKey)->last()
                    : null;
                $previousDecision = is_string($taskKey)
                    ? $decisions->where('task_definition_key', $taskKey)->last()
                    : null;
                $previousData = $previousDecision?->form_data ?? [];
                unset($previousData['return_to']);
                $slaHours = $schema['sla_hours'] ?? null;
                $dueAt = is_numeric($slaHours) && (int) $slaHours > 0 && is_string($task['createTime'] ?? null)
                    ? Carbon::parse($task['createTime'])->addHours((int) $slaHours)->setTimezone(config('app.display_timezone'))
                    : null;

                if (! $previousDecision && $returnNotice && $instance && is_string($taskKey)) {
                    $expectedFieldKeys = collect($schema['fields'] ?? [])->pluck('key')->filter()->values()->all();
                    $taskGroupId = self::groupIdForTask($instance, $task);
                    $bestScore = 0;

                    if ($expectedFieldKeys !== [] && $taskGroupId !== null) {
                        foreach ($decisions as $candidate) {
                            $sourceKey = $candidate->task_definition_key;
                            if (! is_string($sourceKey)
                                || $sourceKey === $taskKey
                                || $candidate->decision === 'rejected'
                                || ($instance->processDefinition->task_group_assignments[$sourceKey] ?? null) != $taskGroupId
                                || ($instance->processDefinition->task_form_schemas[$sourceKey]['kind'] ?? 'work') !== 'work') {
                                continue;
                            }

                            $candidateData = $candidate->form_data ?? [];
                            $score = count(array_intersect($expectedFieldKeys, array_keys($candidateData)));
                            if ($score > 0 && $score >= $bestScore) {
                                $previousData = $candidateData;
                                $bestScore = $score;
                            }
                        }
                    }
                }

                return [
                    'task' => $task,
                    'instance' => $instance,
                    'schema' => $schema,
                    'due_at' => $dueAt,
                    'is_overdue' => $dueAt?->isPast() ?? false,
                    'previous_data' => $previousData,
                    'return_notice' => $returnNotice ? [
                        'justification' => $returnNotice->justification,
                        'actor' => $returnNotice->actor?->name ?? 'Aprovador',
                        'completed_at' => $returnNotice->flowable_completed_at,
                    ] : null,
                    'completed_steps' => $showProcessData && $instance
                        ? self::completedSteps($instance, $decisions)
                        : [],
                ];
            })->values();

            return view('portal.tasks', ['tasks' => $tasks, 'unavailable' => false]);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            report($exception);

            return view('portal.tasks', ['tasks' => collect(), 'unavailable' => true]);
        }
    }

    public function approve(Request $request, string $taskId, FlowableClient $flowable): RedirectResponse
    {
        $assignee = PortalProcessController::flowableAssignee($request->user()->id);

        try {
            $task = $flowable->task($taskId);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            report($exception);

            return back()->withErrors(['flowable' => 'O motor de processos não respondeu. A conclusão não foi enviada.']);
        }

        if (! $task) {
            $existing = ProcessTaskDecision::query()->where('flowable_task_id', $taskId)->first();

            if ($existing?->flowable_completed_at && $existing->decided_by === $request->user()->id) {
                return back()->with('status', 'Esta tarefa já foi concluída.');
            }

            abort(404);
        }

        abort_unless(($task['assignee'] ?? null) === $assignee && is_string($task['processInstanceId'] ?? null), 404);

        $instance = ProcessInstance::query()
            ->with('processDefinition.authorizedUsers')
            ->where('flowable_process_instance_id', $task['processInstanceId'])
            ->firstOrFail();
        $hasPermission = self::userCanAccessTask($instance, $task, $request->user());
        abort_unless($hasPermission, 403);
        $schema = self::schemaForTask($instance, $task);
        $data = self::validateTaskSubmission($request, $schema);
        $decisionValue = ($schema['kind'] ?? 'approval') === 'approval' ? $data['decision'] : 'completed';
        $taskFormData = self::normalizeTaskData($schema['fields'] ?? [], $data['task_data'] ?? []);
        $returnTo = $decisionValue === 'rejected' ? ($data['return_to'] ?? null) : null;
        if (is_string($returnTo) && $returnTo !== '') {
            $taskFormData['return_to'] = $returnTo;
        }
        $variables = self::flowableVariables($task, $schema, $decisionValue, $data['justification'] ?? null, $taskFormData, $returnTo);

        try {
            $decision = DB::transaction(function () use ($request, $taskId, $task, $instance, $data, $taskFormData, $decisionValue): ProcessTaskDecision {
                $decision = ProcessTaskDecision::query()->where('flowable_task_id', $taskId)->lockForUpdate()->first();

                if ($decision) {
                    abort_unless($decision->decided_by === $request->user()->id, 409);

                    if ($decision->flowable_completed_at) {
                        return $decision;
                    }

                    $decision->forceFill([
                        'task_definition_key' => is_string($task['taskDefinitionKey'] ?? null) ? $task['taskDefinitionKey'] : null,
                        'decision' => $decisionValue,
                        'justification' => $data['justification'] ?? '',
                        'form_data' => $taskFormData,
                    ])->save();

                    return $decision;
                }

                return ProcessTaskDecision::query()->create([
                    'process_instance_id' => $instance->id,
                    'flowable_task_id' => $taskId,
                    'task_name' => is_string($task['name'] ?? null) ? $task['name'] : 'Tarefa de aprovação',
                    'task_definition_key' => is_string($task['taskDefinitionKey'] ?? null) ? $task['taskDefinitionKey'] : null,
                    'decided_by' => $request->user()->id,
                    'decision' => $decisionValue,
                    'justification' => $data['justification'] ?? '',
                    'form_data' => $taskFormData,
                ]);
            });
        } catch (QueryException $exception) {
            $decision = ProcessTaskDecision::query()->where('flowable_task_id', $taskId)->first();

            if (! $decision) {
                throw $exception;
            }
        }

        if (! $decision->flowable_completed_at) {
            try {
                $flowable->completeTask($taskId, $variables);
            } catch (ConnectionException|RequestException|RuntimeException $exception) {
                report($exception);
                $historicTask = null;

                try {
                    $historicTask = $flowable->historicTask($taskId);
                } catch (ConnectionException|RequestException|RuntimeException $historyException) {
                    report($historyException);
                }

                if (($historicTask['assignee'] ?? null) !== $assignee || empty($historicTask['endTime'])) {
                    return back()->withErrors(['flowable' => 'Não foi possível confirmar a conclusão. Atualize a página antes de tentar novamente.']);
                }
            }

            $decision->forceFill(['flowable_completed_at' => now()])->save();
        }

        try {
            if (($instance->processDefinition->task_group_assignments ?? []) === []) {
                $flowable->assignUnassignedTasks($instance->flowable_process_instance_id, $assignee);
            }
            $instance->forceFill([
                'status' => $flowable->processInstanceIsActive($instance->flowable_process_instance_id) ? 'running' : 'completed',
            ])->save();
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            report($exception);

            return back()->with('status', 'A tarefa foi concluída. A atualização do estado será repetida ao consultar as tarefas.');
        }

        $status = match ($decision->decision) {
            'approved' => 'Aprovação registrada no histórico.',
            'rejected' => 'Tarefa devolvida para correção e registrada no histórico.',
            default => 'Tarefa concluída e registrada no histórico.',
        };

        return redirect()->route('portal.tasks.index')->with('status', $status);
    }

    /** @param array<string, mixed> $task
     *  @return array{kind: string, fields: array<int, array<string, mixed>>, return_targets?: array<string, string>}
     */
    private static function schemaForTask(?ProcessInstance $instance, array $task): array
    {
        $taskKey = $task['taskDefinitionKey'] ?? null;
        $configured = $instance && is_string($taskKey)
            ? ($instance->processDefinition->task_form_schemas[$taskKey] ?? null)
            : null;

        $schema = is_array($configured)
            ? $configured
            : ['kind' => $instance && self::isGroupTask($instance, $task) ? 'work' : 'approval', 'fields' => []];

        $taskInstructions = $instance && is_string($taskKey)
            ? (ProcessTaskAssignmentController::userTasks($instance->processDefinition->bpmn_xml ?? '')[$taskKey]['documentation'] ?? '')
            : '';
        if (filled($taskInstructions)) {
            $schema['instructions'] = $taskInstructions;
        }

        if (($schema['kind'] ?? null) === 'approval' && $instance) {
            $schema['return_targets'] = self::returnTargets($instance);
        }

        return $schema;
    }

    /** @return array<string, string> */
    private static function returnTargets(ProcessInstance $instance): array
    {
        $routes = $instance->processDefinition->gateway_routes ?? [];
        $targetIds = collect($routes)->flatten()->filter(fn ($outcome): bool => is_string($outcome) && str_starts_with($outcome, 'return:'))
            ->map(fn (string $outcome): string => substr($outcome, 7))->unique()->values()->all();

        if ($targetIds === []) {
            return [];
        }

        return collect(ProcessTaskAssignmentController::userTasks($instance->processDefinition->bpmn_xml ?? []))
            ->only($targetIds)
            ->mapWithKeys(fn (array $task, string $taskId): array => [$taskId => $task['name']])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private static function completedSteps(ProcessInstance $instance, iterable $decisions): array
    {
        $returnTargets = self::returnTargets($instance);
        $steps = [];

        foreach ($decisions as $decision) {
            $taskSchema = $instance->processDefinition->task_form_schemas[$decision->task_definition_key] ?? ['fields' => []];
            $formData = $decision->form_data ?? [];
            $fields = [];

            foreach ($taskSchema['fields'] ?? [] as $field) {
                $key = $field['key'] ?? null;
                if (! is_string($key)) continue;
                $fields[] = [
                    'label' => $field['label'] ?? $key,
                    'value' => array_key_exists($key, $formData) ? $formData[$key] : 'Não preenchido',
                ];
            }

            foreach ($formData as $key => $value) {
                if ($key === 'return_to') {
                    $fields[] = ['label' => 'Setor que recebeu a correção', 'value' => $returnTargets[$value] ?? $value];
                }
            }

            $steps[] = [
                'name' => $decision->task_name,
                'actor' => $decision->actor?->name ?? 'Usuário',
                'completed_at' => $decision->flowable_completed_at,
                'decision' => match ($decision->decision) {
                    'approved' => 'Aprovação registrada',
                    'rejected' => 'Devolvida para correção',
                    default => 'Etapa concluída',
                },
                'justification' => $decision->justification,
                'fields' => $fields,
            ];
        }

        return $steps;
    }

    /** @param array{kind: string, fields: array<int, array<string, mixed>>} $schema
     *  @return array<string, mixed>
     */
    private static function validateTaskSubmission(Request $request, array $schema): array
    {
        $kind = ($schema['kind'] ?? 'approval') === 'approval' ? 'approval' : 'work';
        $returnTargets = $schema['return_targets'] ?? [];
        $returnRules = $returnTargets === []
            ? ['prohibited']
            : ['nullable', 'string', Rule::in(array_keys($returnTargets))];
        if ($kind === 'approval' && $returnTargets !== []) {
            array_unshift($returnRules, 'required_if:decision,rejected');
        }
        $rules = [
            'justification' => [$kind === 'approval' ? 'required' : 'nullable', 'string', 'max:2000'],
            'decision' => [$kind === 'approval' ? 'required' : 'prohibited', Rule::in(['approved', 'rejected'])],
            'return_to' => $returnRules,
            'task_data' => ['nullable', 'array'],
        ];

        foreach ($schema['fields'] ?? [] as $field) {
            if (! is_array($field) || ! is_string($field['key'] ?? null)) {
                continue;
            }

            $presence = ($field['required'] ?? false) ? 'required' : 'nullable';
            $typeRules = match ($field['type'] ?? null) {
                'text' => ['string', 'max:2000'],
                'textarea' => ['string', 'max:10000'],
                'number' => ['numeric', 'between:-999999999999,999999999999'],
                'date' => ['date_format:Y-m-d'],
                'select' => [Rule::in($field['options'] ?? [])],
                'boolean' => ['boolean'],
                default => ['prohibited'],
            };
            $rules["task_data.{$field['key']}"] = [$presence, ...$typeRules];
        }

        $validator = Validator::make($request->all(), $rules);
        $validator->after(function ($validator) use ($request, $schema): void {
            $allowed = collect($schema['fields'] ?? [])->pluck('key')->all();
            $submitted = array_keys((array) $request->input('task_data', []));
            $extra = array_diff($submitted, $allowed);

            if ($extra !== []) {
                $validator->errors()->add('task_data', 'O formulário foi alterado. Recarregue a tarefa antes de enviar.');
            }
        });

        return $validator->validate();
    }

    /** @param array<int, array<string, mixed>> $fields
     *  @param array<string, mixed> $submitted
     *  @return array<string, mixed>
     */
    private static function normalizeTaskData(array $fields, array $submitted): array
    {
        $values = [];

        foreach ($fields as $field) {
            $key = $field['key'] ?? null;
            $value = is_string($key) ? ($submitted[$key] ?? null) : null;

            if ($value === null || $value === '') {
                continue;
            }

            $values[$key] = match ($field['type']) {
                'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'number' => str_contains((string) $value, '.') ? (float) $value : (int) $value,
                default => $value,
            };
        }

        return $values;
    }

    /** @param array<string, mixed> $task
     *  @param array{kind: string, fields: array<int, array<string, mixed>>} $schema
     *  @param array<string, mixed> $formData
     *  @return array<int, array{name: string, type: string, value: mixed}>
     */
    private static function flowableVariables(array $task, array $schema, string $decision, ?string $justification, array $formData, ?string $returnTo = null): array
    {
        $taskKey = is_string($task['taskDefinitionKey'] ?? null) ? $task['taskDefinitionKey'] : 'task';
        $token = rtrim(strtr(base64_encode($taskKey), '+/', '-_'), '=');
        $variables = [];

        if (($schema['kind'] ?? 'approval') === 'approval') {
            $variables[] = ['name' => 'fc_decision', 'type' => 'string', 'value' => $decision];
            $variables[] = ['name' => 'fc_justification', 'type' => 'string', 'value' => trim((string) $justification)];
            $variables[] = ['name' => 'fc_return_to', 'type' => 'string', 'value' => $returnTo ?? ''];
        } else {
            $variables[] = ['name' => "fc_task_decision_{$token}", 'type' => 'string', 'value' => 'completed'];
            if (filled($justification)) {
                $variables[] = ['name' => "fc_task_comment_{$token}", 'type' => 'string', 'value' => trim((string) $justification)];
            }
        }

        foreach ($formData as $key => $value) {
            $type = match (true) {
                is_bool($value) => 'boolean',
                is_int($value) => 'integer',
                is_float($value) => 'double',
                default => 'string',
            };
            $variables[] = ['name' => "fc_task_{$token}__{$key}", 'type' => $type, 'value' => $value];
        }

        return $variables;
    }

    public static function pendingCountFor(User $user, FlowableClient $flowable): int
    {
        $assignee = PortalProcessController::flowableAssignee($user->id);
        $engineTasks = collect($flowable->tasksForAssignee($assignee));

        foreach ($user->processGroups()->get() as $group) {
            foreach ($flowable->tasksForCandidateGroup($group->flowable_group_id) as $candidateTask) {
                if (! is_string($candidateTask['id'] ?? null)
                    || ! is_string($candidateTask['processInstanceId'] ?? null)
                    || (filled($candidateTask['assignee'] ?? null) && $candidateTask['assignee'] !== $assignee)) {
                    continue;
                }

                $engineTasks->push($candidateTask);
            }
        }

        $engineTasks = $engineTasks->filter(fn ($task): bool => is_array($task)
            && is_string($task['id'] ?? null)
            && is_string($task['processInstanceId'] ?? null))
            ->unique('id')
            ->values();
        $processInstanceIds = $engineTasks->pluck('processInstanceId')->unique()->all();

        if ($processInstanceIds === []) {
            return 0;
        }

        $instances = ProcessInstance::query()
            ->with('processDefinition.authorizedUsers')
            ->where('status', 'running')
            ->whereIn('flowable_process_instance_id', $processInstanceIds)
            ->get()
            ->keyBy('flowable_process_instance_id');

        return $engineTasks->filter(function (array $task) use ($instances, $user): bool {
            $instance = $instances->get($task['processInstanceId']);

            return $instance instanceof ProcessInstance
                && self::userCanAccessTask($instance, $task, $user);
        })->count();
    }

    /** @param array<string, mixed> $task */
    public static function userCanAccessTask(ProcessInstance $instance, array $task, User $user): bool
    {
        $groupId = self::groupIdForTask($instance, $task);

        if (is_numeric($groupId)) {
            return $user->processGroups()->whereKey((int) $groupId)->exists();
        }

        return $instance->processDefinition->authorizedUsers->contains(
            fn (User $authorized): bool => $authorized->id === $user->id && (bool) $authorized->pivot->can_approve,
        );
    }

    /** @param array<string, mixed> $task */
    private static function isGroupTask(ProcessInstance $instance, array $task): bool
    {
        return self::groupIdForTask($instance, $task) !== null;
    }

    /** @param array<string, mixed> $task */
    private static function groupIdForTask(ProcessInstance $instance, array $task): mixed
    {
        $taskKey = $task['taskDefinitionKey'] ?? null;

        return is_string($taskKey)
            ? ($instance->processDefinition->task_group_assignments[$taskKey] ?? null)
            : null;
    }
}
