<?php

namespace App\Console\Commands;

use App\Models\ProcessInstance;
use App\Models\TaskDelegationTransfer;
use App\Models\TaskEscalationTransfer;
use App\Models\UserTaskDelegation;
use App\Services\FlowableClient;
use App\Services\TaskAssignmentNotificationService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

class ApplyTaskDelegations extends Command
{
    protected $signature = 'flowcore:apply-task-delegations {--owner= : Only apply delegations created by this user} {--delegate= : Only apply delegations assigned to this substitute}';

    protected $description = 'Forward active and new individual tasks to the current temporary substitute';

    public function handle(FlowableClient $flowable, TaskAssignmentNotificationService $notifications): int
    {
        try {
            $delegationQuery = UserTaskDelegation::query()
                ->with('delegate')
                ->whereNull('cancelled_at')
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now())
                ->whereHas('delegate', fn ($query) => $query->where('is_active', true));
            if (is_numeric($this->option('owner'))) {
                $delegationQuery->where('user_id', (int) $this->option('owner'));
            }
            if (is_numeric($this->option('delegate'))) {
                $delegationQuery->where('delegate_user_id', (int) $this->option('delegate'));
            }

            $activeDelegations = $delegationQuery->get()->keyBy('user_id');
            if ($activeDelegations->isEmpty()) {
                $this->info('Tarefas encaminhadas para substitutos: 0.');

                return self::SUCCESS;
            }

            $tasks = $flowable->allActiveTasks();
            $processInstanceIds = collect($tasks)->pluck('processInstanceId')->filter()->unique()->values();
            $escalatedTaskIds = TaskEscalationTransfer::query()
                ->whereIn('flowable_task_id', collect($tasks)->pluck('id')->filter()->values())
                ->pluck('flowable_task_id')
                ->flip();
            $instances = ProcessInstance::query()
                ->with('processDefinition')
                ->where('status', 'running')
                ->whereIn('flowable_process_instance_id', $processInstanceIds)
                ->get()
                ->keyBy('flowable_process_instance_id');
            $transferredInstances = [];
            $transferredCount = 0;

            foreach ($tasks as $task) {
                $taskId = $task['id'] ?? null;
                $taskKey = $task['taskDefinitionKey'] ?? null;
                $processInstanceId = $task['processInstanceId'] ?? null;
                if (! is_string($taskId) || ! is_string($taskKey) || ! is_string($processInstanceId)) {
                    continue;
                }
                if ($escalatedTaskIds->has($taskId)) {
                    continue;
                }

                $instance = $instances->get($processInstanceId);
                if (! $instance) {
                    continue;
                }

                $definition = $instance->processDefinition;
                $originalUserId = $definition->task_user_assignments[$taskKey] ?? null;
                $currentAssignee = $task['assignee'] ?? null;

                if (is_numeric($originalUserId)) {
                    $originalUserId = (int) $originalUserId;
                } elseif (is_numeric($definition->task_group_assignments[$taskKey] ?? null)
                    && is_string($currentAssignee)
                    && preg_match('/^flowcore_user_(\d+)$/', $currentAssignee, $matches) === 1) {
                    // Group tasks already claimed by the absent user move with them;
                    // unclaimed tasks remain available in the shared group queue.
                    $originalUserId = (int) $matches[1];
                } else {
                    continue;
                }

                $delegation = $activeDelegations->get($originalUserId);
                $delegate = $delegation?->delegate;
                if (! $delegation || ! $delegate || ! $delegate->is_active || $delegate->id === $originalUserId) {
                    continue;
                }

                $existingTransfer = TaskDelegationTransfer::query()->where('flowable_task_id', $taskId)->exists();
                if ($existingTransfer) {
                    continue;
                }

                $expectedAssignee = "flowcore_user_{$originalUserId}";
                $delegateAssignee = "flowcore_user_{$delegate->id}";
                if ($currentAssignee === $expectedAssignee || blank($currentAssignee)) {
                    $flowable->updateTaskAssignee($taskId, $delegateAssignee);
                } elseif ($currentAssignee !== $delegateAssignee) {
                    continue;
                }

                $transfer = TaskDelegationTransfer::query()->firstOrCreate(
                    ['flowable_task_id' => $taskId],
                    [
                        'user_task_delegation_id' => $delegation->id,
                        'process_instance_id' => $instance->id,
                        'original_user_id' => $originalUserId,
                        'delegate_user_id' => $delegate->id,
                        'transferred_at' => now(),
                    ],
                );

                if ($transfer->wasRecentlyCreated) {
                    $transferredCount++;
                    $transferredInstances[$instance->id] = $instance;
                }
            }

            foreach ($transferredInstances as $instance) {
                $notifications->notifyNewTasks($instance, $flowable);
            }
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            report($exception);
            $this->error('Não foi possível aplicar as delegações porque o motor de processos não respondeu. A próxima execução tentará novamente.');

            return self::FAILURE;
        }

        $this->info("Tarefas encaminhadas para substitutos: {$transferredCount}.");

        return self::SUCCESS;
    }
}
