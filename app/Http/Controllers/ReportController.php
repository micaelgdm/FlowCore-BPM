<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\ProcessDefinition;
use App\Models\ProcessGroup;
use App\Models\ProcessInstance;
use App\Models\User;
use App\Services\FlowableClient;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, FlowableClient $flowable): View
    {
        $data = $this->reportData($request, $flowable);
        $page = LengthAwarePaginator::resolveCurrentPage();

        return view('reports.index', [
            ...$data,
            'requests' => new LengthAwarePaginator(
                $data['requests']->forPage($page, 25)->values(),
                $data['requests']->count(),
                25,
                $page,
                ['path' => $request->url(), 'query' => $data['filters']],
            ),
            'processOptions' => ProcessDefinition::query()->orderBy('name')->orderByDesc('version')->get(['id', 'name', 'version']),
            'groupOptions' => ProcessGroup::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function export(Request $request, FlowableClient $flowable): StreamedResponse
    {
        $report = $this->reportData($request, $flowable);
        AuditEvent::query()->create([
            'actor_id' => $request->user()->id,
            'actor_name' => mb_substr($request->user()->name, 0, 120),
            'action' => 'report.requests.exported',
            'subject' => 'Relatório operacional de solicitações',
            'details' => [
                'from' => $report['filters']['from'],
                'to' => $report['filters']['to'],
                'status' => $report['filters']['status'] ?: null,
                'process_id' => $report['filters']['process_id'] ?: null,
                'group_id' => $report['filters']['group_id'] ?: null,
                'record_count' => $report['requests']->count(),
            ],
        ]);
        $filename = 'flowcore-solicitacoes-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($report): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['ID da solicitação', 'Processo', 'Versão', 'Solicitante', 'Iniciada em', 'Situação', 'Etapa atual', 'Responsável atual', 'Prazo da etapa', 'Prazo'], ';', '"', '\\');

            foreach ($report['requests'] as $item) {
                fputcsv($output, array_map($this->safeCsvValue(...), [
                    $item['display_id'],
                    $item['process_name'],
                    'V'.$item['version'],
                    $item['requester'],
                    $item['created_at'],
                    $item['status_label'],
                    $item['current_activity'],
                    $item['responsible'],
                    $item['due_at'],
                    $item['deadline_label'],
                ]), ';', '"', '\\');
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array<string, mixed> */
    private function reportData(Request $request, FlowableClient $flowable): array
    {
        $timezone = (string) config('app.display_timezone');
        $today = now($timezone)->toDateString();
        $request->merge([
            'from' => filled($request->query('from')) ? $request->query('from') : now($timezone)->subDays(29)->toDateString(),
            'to' => filled($request->query('to')) ? $request->query('to') : $today,
        ]);
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['nullable', 'in:starting,running,completed,failed'],
            'process_id' => ['nullable', 'integer', 'exists:process_definitions,id'],
            'group_id' => ['nullable', 'integer', 'exists:process_groups,id'],
        ]);
        $filters = [
            'from' => $validated['from'],
            'to' => $validated['to'],
            'status' => $validated['status'] ?? '',
            'process_id' => $validated['process_id'] ?? '',
            'group_id' => $validated['group_id'] ?? '',
        ];

        $from = Carbon::parse($filters['from'], $timezone)->startOfDay()->utc();
        $to = Carbon::parse($filters['to'], $timezone)->endOfDay()->utc();
        $instanceQuery = ProcessInstance::query()
            ->with(['processDefinition', 'starter'])
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to);

        if ($filters['status'] !== '') {
            $instanceQuery->where('status', $filters['status']);
        }
        if ($filters['process_id'] !== '') {
            $instanceQuery->where('process_definition_id', (int) $filters['process_id']);
        }

        $instances = $instanceQuery->orderByDesc('created_at')->get();
        $instanceIds = $instances->pluck('id')->all();
        $groups = ProcessGroup::query()->get()->keyBy('id');
        $runtimeAvailable = true;
        $runtimeLimitReached = false;
        $activeTasks = collect();
        $instanceKeys = $instances->pluck('flowable_process_instance_id')->filter()->flip();
        $instancesByFlowableId = $instances->keyBy('flowable_process_instance_id');

        if ($instances->contains(fn (ProcessInstance $instance): bool => $instance->status === 'running' && filled($instance->flowable_process_instance_id))) {
            try {
                $tasks = $flowable->allActiveTasks(20000);
                $runtimeLimitReached = count($tasks) >= 20000;
                $activeTasks = collect($tasks)
                    ->filter(fn (array $task): bool => isset($instanceKeys[$task['processInstanceId'] ?? '']))
                    ->map(function (array $task) use ($instancesByFlowableId, $groups): array {
                        $instance = $instancesByFlowableId->get($task['processInstanceId'] ?? '');
                        $taskKey = $task['taskDefinitionKey'] ?? null;
                        $taskKey = is_string($taskKey) ? $taskKey : '';
                        $definition = $instance?->processDefinition;
                        $groupId = $definition?->task_group_assignments[$taskKey] ?? null;
                        $group = is_numeric($groupId) ? $groups->get((int) $groupId) : null;
                        $assignedUserId = $definition?->task_user_assignments[$taskKey] ?? null;
                        $assignee = (string) ($task['assignee'] ?? '');
                        if (preg_match('/^flowcore_user_(\d+)$/', $assignee, $matches)) {
                            $assignedUserId = (int) $matches[1];
                        }

                        $dueAt = null;
                        $startedAt = null;
                        if (is_string($task['createTime'] ?? null) && $task['createTime'] !== '') {
                            try {
                                $startedAt = Carbon::parse($task['createTime'])->utc();
                                $slaHours = $definition?->task_form_schemas[$taskKey]['sla_hours'] ?? null;
                                if (is_numeric($slaHours) && (int) $slaHours > 0) {
                                    $dueAt = $startedAt->copy()->addHours((int) $slaHours);
                                }
                            } catch (\Throwable) {
                                $startedAt = null;
                            }
                        }

                        return [
                            'process_instance_id' => $task['processInstanceId'],
                            'name' => is_string($task['name'] ?? null) && $task['name'] !== '' ? $task['name'] : 'Etapa sem nome',
                            'group_id' => is_numeric($groupId) ? (int) $groupId : null,
                            'group_name' => $group?->name ?? (is_numeric($groupId) ? 'Grupo removido #'.$groupId : null),
                            'assigned_user_id' => is_numeric($assignedUserId) ? (int) $assignedUserId : null,
                            'due_at' => $dueAt,
                            'started_at' => $startedAt,
                            'overdue' => $dueAt?->isPast() ?? false,
                        ];
                    });
            } catch (ConnectionException|RequestException|RuntimeException $exception) {
                report($exception);
                $runtimeAvailable = false;
            }
        }

        $assigneeIds = $activeTasks->pluck('assigned_user_id')->filter()->unique()->all();
        $assignees = User::query()->whereIn('id', $assigneeIds)->get(['id', 'name'])->keyBy('id');
        $tasksByInstance = $activeTasks->groupBy('process_instance_id');

        $requestRows = $instances->map(function (ProcessInstance $instance) use ($tasksByInstance, $assignees, $runtimeAvailable, $timezone): array {
            $tasks = $tasksByInstance->get($instance->flowable_process_instance_id, collect());
            $activityNames = $tasks->pluck('name')->unique()->values();
            $responsibles = $tasks->map(function (array $task) use ($assignees): ?string {
                $parts = collect([$task['group_name'], $assignees->get($task['assigned_user_id'])?->name])->filter();
                return $parts->isNotEmpty() ? $parts->join(' · ') : null;
            })->filter()->unique()->values();
            $dueAt = $tasks->pluck('due_at')->filter()->sort()->first();
            $isOverdue = $tasks->contains(fn (array $task): bool => $task['overdue']);
            $statusLabel = match ($instance->status) {
                'running' => 'Em andamento',
                'completed' => 'Concluída',
                'failed' => 'Falha ao iniciar',
                'starting' => 'Iniciando',
                default => 'Indefinida',
            };
            $currentActivity = $activityNames->isNotEmpty()
                ? $activityNames->join(', ')
                : ($instance->status === 'running' && ! $runtimeAvailable ? 'Motor indisponível' : ($instance->status === 'running' ? 'Aguardando próxima etapa' : '—'));

            return [
                'id' => $instance->id,
                'uuid' => $instance->uuid,
                'display_id' => $instance->displayId(),
                'process_name' => $instance->processDefinition->name,
                'version' => $instance->processDefinition->version,
                'requester' => $instance->starter?->name ?? 'Usuário removido',
                'created_at' => $instance->created_at?->copy()->setTimezone($timezone)->format('d/m/Y H:i') ?? '—',
                'status' => $instance->status,
                'status_label' => $statusLabel,
                'current_activity' => $currentActivity,
                'responsible' => $responsibles->isNotEmpty() ? $responsibles->join(', ') : '—',
                'due_at' => $dueAt?->copy()->setTimezone($timezone)->format('d/m/Y H:i') ?? '—',
                'deadline_label' => $isOverdue ? 'Atrasada' : ($dueAt ? 'No prazo' : ($tasks->isNotEmpty() ? 'Sem prazo' : '—')),
                'group_ids' => $tasks->pluck('group_id')->filter()->unique()->values()->all(),
                'tasks' => $tasks,
            ];
        });

        if ($filters['group_id'] !== '') {
            $groupId = (int) $filters['group_id'];
            $requestRows = $requestRows->filter(fn (array $row): bool => in_array($groupId, $row['group_ids'], true))->values();
        }

        $requestIds = $requestRows->pluck('id')->all();
        $stageRows = $requestIds === [] ? collect() : DB::table('process_task_decisions as decisions')
            ->whereIn('decisions.process_instance_id', $requestIds)
            ->whereNotNull('decisions.flowable_started_at')
            ->whereNotNull('decisions.flowable_completed_at')
            ->select(['decisions.process_instance_id', 'decisions.task_definition_key', 'decisions.task_name', 'decisions.flowable_started_at', 'decisions.flowable_completed_at'])
            ->get();
        $definitionsById = $instances->pluck('processDefinition')->keyBy('id');
        $stageMetrics = $stageRows
            ->map(function (object $row) use ($instances, $definitionsById, $groups, $filters): ?array {
                $startedAt = Carbon::parse($row->flowable_started_at);
                $completedAt = Carbon::parse($row->flowable_completed_at);
                $seconds = $completedAt->getTimestamp() - $startedAt->getTimestamp();
                $instance = $instances->firstWhere('id', $row->process_instance_id);
                $definition = $instance ? $definitionsById->get($instance->process_definition_id) : null;
                $taskKey = $row->task_definition_key;
                $groupId = is_string($taskKey) ? ($definition?->task_group_assignments[$taskKey] ?? null) : null;

                if ($filters['group_id'] !== '' && (int) $groupId !== (int) $filters['group_id']) {
                    return null;
                }
                if (! $instance || ! $definition || $seconds < 0) {
                    return null;
                }

                return [
                    'key' => $definition->id.'|'.$definition->version.'|'.$taskKey,
                    'process' => $definition->name,
                    'version' => $definition->version,
                    'task_name' => $row->task_name,
                    'group_name' => is_numeric($groupId) ? ($groups->get((int) $groupId)?->name ?? 'Grupo removido') : 'Sem grupo',
                    'seconds' => $seconds,
                ];
            })
            ->filter()
            ->groupBy('key')
            ->map(function ($items): array {
                $first = $items->first();
                return [
                    'process' => $first['process'],
                    'version' => $first['version'],
                    'task_name' => $first['task_name'],
                    'group_name' => $first['group_name'],
                    'average_seconds' => (int) round($items->avg('seconds')),
                    'sample_count' => $items->count(),
                ];
            })
            ->sortByDesc('sample_count')
            ->values();

        $pendingTasks = $requestRows->flatMap(fn (array $row) => $row['tasks']);
        $pendingGroupMetrics = $pendingTasks
            ->filter(fn (array $task): bool => filled($task['group_name']))
            ->groupBy('group_id')
            ->map(function ($items): array {
                $first = $items->first();
                return [
                    'group_name' => $first['group_name'],
                    'pending_count' => $items->count(),
                    'overdue_count' => $items->where('overdue', true)->count(),
                ];
            })
            ->sortByDesc('pending_count')
            ->values();

        return [
            'filters' => $filters,
            'requests' => $requestRows,
            'stageMetrics' => $stageMetrics,
            'pendingGroupMetrics' => $pendingGroupMetrics,
            'summary' => [
                'total' => $requestRows->count(),
                'running' => $requestRows->where('status', 'running')->count(),
                'completed' => $requestRows->where('status', 'completed')->count(),
                'pending_tasks' => $pendingTasks->count(),
                'overdue_tasks' => $pendingTasks->where('overdue', true)->count(),
            ],
            'runtimeAvailable' => $runtimeAvailable,
            'runtimeLimitReached' => $runtimeLimitReached,
        ];
    }

    private function safeCsvValue(mixed $value): string
    {
        $value = (string) $value;
        return preg_match('/^[\s]*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }
}
