<?php

namespace App\Console\Commands;

use App\Models\ProcessInstance;
use App\Models\TaskEscalationTransfer;
use App\Services\FlowableClient;
use App\Services\TaskAssignmentNotificationService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

class ApplyTaskEscalations extends Command
{
    protected $signature = 'flowcore:apply-task-escalations';

    protected $description = 'Forward overdue process tasks to the configured escalation user';

    public function handle(FlowableClient $flowable, TaskAssignmentNotificationService $notifications): int
    {
        try {
            $tasks = $flowable->allActiveTasks();
            if ($tasks === []) {
                $this->info('Tarefas escalonadas: 0.');
                return self::SUCCESS;
            }

            $taskIds = collect($tasks)->pluck('id')->filter('is_string')->values();
            $existingTransfers = TaskEscalationTransfer::query()->whereIn('flowable_task_id', $taskIds)->get()->keyBy('flowable_task_id');
            $processInstanceIds = collect($tasks)->pluck('processInstanceId')->filter('is_string')->unique()->values();
            $instances = ProcessInstance::query()
                ->with('processDefinition')
                ->where('status', 'running')
                ->whereIn('flowable_process_instance_id', $processInstanceIds)
                ->get()
                ->keyBy('flowable_process_instance_id');
            $transferredInstances = [];

            foreach ($tasks as $task) {
                $taskId = $task['id'] ?? null;
                $taskKey = $task['taskDefinitionKey'] ?? null;
                $instance = $instances->get($task['processInstanceId'] ?? '');
                if (! is_string($taskId) || ! is_string($taskKey) || ! $instance || $existingTransfers->has($taskId)) {
                    continue;
                }

                $schema = $instance->processDefinition->task_form_schemas[$taskKey] ?? [];
                $targetId = $schema['escalation_user_id'] ?? null;
                $slaHours = $schema['sla_hours'] ?? null;
                $createdAt = $task['createTime'] ?? null;
                if (! is_numeric($targetId) || ! is_numeric($slaHours) || (int) $slaHours < 1 || ! is_string($createdAt)) {
                    continue;
                }

                $target = \App\Models\User::query()->whereKey((int) $targetId)->where('is_active', true)->first();
                $dueAt = \Illuminate\Support\Carbon::parse($createdAt)->addHours((int) $slaHours);
                if (! $target || ! $dueAt->isPast()) {
                    continue;
                }

                $flowable->updateTaskAssignee($taskId, \App\Http\Controllers\PortalProcessController::flowableAssignee($target->id));
                $transfer = TaskEscalationTransfer::query()->firstOrCreate(
                    ['flowable_task_id' => $taskId],
                    [
                        'process_instance_id' => $instance->id,
                        'original_assignee' => is_string($task['assignee'] ?? null) ? $task['assignee'] : null,
                        'escalated_to_user_id' => $target->id,
                        'transferred_at' => now(),
                    ],
                );

                if ($transfer->wasRecentlyCreated) {
                    $transferredInstances[$instance->id] = $instance;
                }
            }

            foreach ($transferredInstances as $instance) {
                $notifications->notifyNewTasks($instance, $flowable);
            }

            $this->info('Tarefas escalonadas: '.count($transferredInstances).'.');
            return self::SUCCESS;
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            report($exception);
            $this->error('O motor não respondeu durante o escalonamento. A próxima execução tentará novamente.');
            return self::FAILURE;
        }
    }
}
