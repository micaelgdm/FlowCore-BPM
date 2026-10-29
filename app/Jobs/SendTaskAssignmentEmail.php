<?php

namespace App\Jobs;

use App\Mail\TaskAssignedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendTaskAssignmentEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(public int $outboxId) {}

    public function handle(): void
    {
        $message = DB::table('task_notification_outbox')->where('id', $this->outboxId)->first();

        if (! $message || $message->status === 'sent') {
            return;
        }

        $taskUrl = route('portal.tasks.index');
        if (in_array($message->notification_type, ['approval', 'returned', 'rejected', 'completed'], true)) {
            $requestUuid = DB::table('process_instances')->where('id', $message->process_instance_id)->value('uuid');
            if (is_string($requestUuid)) {
                $taskUrl = route('portal.requests.show', $requestUuid);
            }
        }

        Mail::to($message->recipient_email)->send(new TaskAssignedMail(
            recipientName: $message->recipient_name,
            processName: $message->process_name,
            taskName: $message->task_name,
            requestCode: $message->request_code,
            taskUrl: $taskUrl,
            notificationType: $message->notification_type,
            dueAtText: filled($message->due_at)
                ? Carbon::parse($message->due_at)->setTimezone(config('app.display_timezone'))->format('d/m/Y H:i')
                : null,
            context: $message->context,
        ));

        DB::table('task_notification_outbox')->where('id', $this->outboxId)->update([
            'status' => 'sent',
            'sent_at' => now(),
            'last_error' => null,
            'updated_at' => now(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        report($exception);

        DB::table('task_notification_outbox')->where('id', $this->outboxId)->update([
            'status' => 'failed',
            'last_error' => 'Falha no transporte de e-mail após as tentativas configuradas.',
            'updated_at' => now(),
        ]);
    }
}
