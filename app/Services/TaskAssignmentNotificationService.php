<?php

namespace App\Services;

use App\Http\Controllers\ProcessTaskAssignmentController;
use App\Jobs\SendTaskAssignmentEmail;
use App\Models\ProcessGroup;
use App\Models\ProcessInstance;
use App\Models\TaskDelegationTransfer;
use App\Models\User;
use App\Models\UserTaskDelegation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TaskAssignmentNotificationService
{
    public function notifyNewTasks(ProcessInstance $instance, FlowableClient $flowable): int
    {
        if (blank($instance->flowable_process_instance_id)) {
            return 0;
        }

        $definition = $instance->processDefinition;
        $taskDefinitions = ProcessTaskAssignmentController::userTasks($definition->bpmn_xml ?? '');
        $notificationsQueued = 0;

        foreach ($flowable->tasksForProcessInstance($instance->flowable_process_instance_id) as $task) {
            $taskId = $task['id'] ?? null;
            $taskKey = $task['taskDefinitionKey'] ?? null;

            if (! is_string($taskId) || ! is_string($taskKey)) {
                continue;
            }

            $context = $this->delegationContextForTask($instance, $taskKey, $taskId);
            foreach ($this->recipientsForTask($instance, $taskKey, $task['assignee'] ?? null, $taskId) as $recipient) {
                $notificationsQueued += (int) $this->queueNotification(
                    $instance,
                    $task,
                    $taskDefinitions[$taskKey]['name'] ?? 'Tarefa',
                    $recipient,
                    'assigned',
                    context: $context,
                );
            }
        }

        return $notificationsQueued;
    }

    public function notifyDeadlines(ProcessInstance $instance, FlowableClient $flowable): int
    {
        if (blank($instance->flowable_process_instance_id)) {
            return 0;
        }

        $definition = $instance->processDefinition;
        $taskDefinitions = ProcessTaskAssignmentController::userTasks($definition->bpmn_xml ?? '');
        $taskSchemas = $definition->task_form_schemas ?? [];
        $queued = 0;

        foreach ($flowable->tasksForProcessInstance($instance->flowable_process_instance_id) as $task) {
            $taskId = $task['id'] ?? null;
            $taskKey = $task['taskDefinitionKey'] ?? null;
            $createdAt = $task['createTime'] ?? null;
            $slaHours = is_string($taskKey) ? ($taskSchemas[$taskKey]['sla_hours'] ?? null) : null;

            if (! is_string($taskId) || ! is_string($taskKey) || ! is_string($createdAt) || ! is_numeric($slaHours) || (int) $slaHours < 1) {
                continue;
            }

            $dueAt = Carbon::parse($createdAt)->addHours((int) $slaHours);
            $notificationType = $dueAt->isPast()
                ? 'overdue'
                : ($dueAt->lessThanOrEqualTo(now()->addHours(24)) ? 'deadline_soon' : null);

            if ($notificationType === null) {
                continue;
            }

            $context = $this->delegationContextForTask($instance, $taskKey, $taskId);
            foreach ($this->recipientsForTask($instance, $taskKey, $task['assignee'] ?? null, $taskId) as $recipient) {
                $queued += (int) $this->queueNotification(
                    $instance,
                    $task,
                    $taskDefinitions[$taskKey]['name'] ?? 'Tarefa',
                    $recipient,
                    $notificationType,
                    $dueAt,
                    $context,
                );
            }
        }

        return $queued;
    }

    /** @param array<string, mixed> $task */
    public function notifyRequester(ProcessInstance $instance, array $task, string $notificationType, ?string $context = null): bool
    {
        if (! in_array($notificationType, ['approval', 'returned', 'rejected', 'completed'], true)) {
            return false;
        }

        $recipient = $instance->starter()->first();
        if (! $recipient) {
            return false;
        }

        return $this->queueNotification(
            $instance,
            $task,
            is_string($task['name'] ?? null) ? $task['name'] : 'Etapa do processo',
            $recipient,
            $notificationType,
            context: $context,
        );
    }

    /** @param array<string, mixed> $task */
    private function queueNotification(
        ProcessInstance $instance,
        array $task,
        string $taskName,
        User $recipient,
        string $notificationType,
        ?Carbon $dueAt = null,
        ?string $context = null,
    ): bool {
        if (blank($recipient->email) || ! is_string($task['id'] ?? null)) {
            return false;
        }

        $now = now();
        $inserted = DB::table('task_notification_outbox')->insertOrIgnore([
            'process_instance_id' => $instance->id,
            'flowable_task_id' => $task['id'],
            'notification_type' => $notificationType,
            'recipient_user_id' => $recipient->id,
            'recipient_email' => $recipient->email,
            'recipient_name' => $recipient->name,
            'process_name' => $instance->processDefinition->name,
            'task_name' => $task['name'] ?? $taskName,
            'due_at' => $dueAt?->copy()->utc(),
            'context' => $context,
            'request_code' => $instance->displayId(),
            'status' => 'queued',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted === 0) {
            return false;
        }

        $outboxId = (int) DB::table('task_notification_outbox')
            ->where('flowable_task_id', $task['id'])
            ->where('recipient_user_id', $recipient->id)
            ->where('notification_type', $notificationType)
            ->value('id');

        SendTaskAssignmentEmail::dispatch($outboxId)->afterCommit();

        return true;
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function recipientsForTask(ProcessInstance $instance, string $taskKey, mixed $assignee, string $taskId)
    {
        $transfer = TaskDelegationTransfer::query()->where('flowable_task_id', $taskId)->first();
        if ($transfer) {
            return User::query()->whereKey($transfer->delegate_user_id)->where('is_active', true)->get();
        }

        $definition = $instance->processDefinition;
        $userId = $definition->task_user_assignments[$taskKey] ?? null;
        if (is_numeric($userId)) {
            $delegate = $this->activeDelegateFor((int) $userId);
            if ($delegate) {
                return collect([$delegate]);
            }

            $user = User::query()->whereKey((int) $userId)->where('is_active', true)->first();

            return $user ? collect([$user]) : collect();
        }

        $groupId = $definition->task_group_assignments[$taskKey] ?? null;
        if (is_numeric($groupId)) {
            if (is_string($assignee) && preg_match('/^flowcore_user_(\d+)$/', $assignee, $matches) === 1) {
                return User::query()->whereKey((int) $matches[1])->where('is_active', true)->get();
            }

            return ProcessGroup::query()
                ->whereKey((int) $groupId)
                ->with(['users' => fn ($query) => $query->where('is_active', true)])
                ->first()?->users ?? collect();
        }

        if (is_string($assignee) && preg_match('/^flowcore_user_(\d+)$/', $assignee, $matches) === 1) {
            $delegate = $this->activeDelegateFor((int) $matches[1]);
            if ($delegate) {
                return collect([$delegate]);
            }

            return User::query()->whereKey((int) $matches[1])->where('is_active', true)->get();
        }

        return $definition->authorizedUsers()
            ->wherePivot('can_approve', true)
            ->where('is_active', true)
            ->get();
    }

    private function activeDelegateFor(int $userId): ?User
    {
        $delegate = UserTaskDelegation::query()
            ->with('delegate')
            ->where('user_id', $userId)
            ->whereNull('cancelled_at')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->first()?->delegate;

        return $delegate?->is_active ? $delegate : null;
    }

    private function delegationContextForTask(ProcessInstance $instance, string $taskKey, string $taskId): ?string
    {
        $transfer = TaskDelegationTransfer::query()->with('originalUser')->where('flowable_task_id', $taskId)->first();
        if ($transfer) {
            return 'Você está atuando como substituto de '.$transfer->originalUser->name.'.';
        }

        $originalUserId = $instance->processDefinition->task_user_assignments[$taskKey] ?? null;
        if (! is_numeric($originalUserId) || ! $this->activeDelegateFor((int) $originalUserId)) {
            return null;
        }

        $originalUserName = User::query()->whereKey((int) $originalUserId)->value('name');

        return is_string($originalUserName) ? 'Você está atuando como substituto de '.$originalUserName.'.' : null;
    }
}
