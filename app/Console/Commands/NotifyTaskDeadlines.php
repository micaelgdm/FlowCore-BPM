<?php

namespace App\Console\Commands;

use App\Models\ProcessInstance;
use App\Services\FlowableClient;
use App\Services\TaskAssignmentNotificationService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class NotifyTaskDeadlines extends Command
{
    protected $signature = 'flowcore:notify-task-deadlines {--limit=25 : Maximum number of process instances scanned per run}';

    protected $description = 'Queue one upcoming-deadline and one overdue email per active task and responsible user';

    public function handle(FlowableClient $flowable, TaskAssignmentNotificationService $notifications): int
    {
        $limit = max(1, min(250, (int) $this->option('limit')));
        $instances = ProcessInstance::query()
            ->where('status', 'running')
            ->whereNotNull('flowable_process_instance_id')
            ->orderByRaw('task_deadline_notifications_checked_at IS NULL DESC')
            ->orderBy('task_deadline_notifications_checked_at')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'flowable_process_instance_id']);
        $checked = 0;
        $queued = 0;

        foreach ($instances as $instance) {
            try {
                $queued += $notifications->notifyDeadlines($instance, $flowable);
            } catch (ConnectionException|RequestException|RuntimeException $exception) {
                report($exception);
                $this->error('O Flowable não respondeu durante a verificação de prazos; o próximo ciclo tentará novamente.');

                return self::FAILURE;
            }

            $checked += ProcessInstance::query()
                ->whereKey($instance->id)
                ->where('status', 'running')
                ->update(['task_deadline_notifications_checked_at' => now()]);
        }

        $this->info("Solicitações verificadas: {$checked}. E-mails de prazo colocados na fila: {$queued}.");

        return self::SUCCESS;
    }
}
