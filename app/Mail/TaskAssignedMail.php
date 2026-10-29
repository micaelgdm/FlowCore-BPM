<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TaskAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $processName,
        public string $taskName,
        public string $requestCode,
        public string $taskUrl,
        public string $notificationType = 'assigned',
        public ?string $dueAtText = null,
        public ?string $context = null,
    ) {}

    public function envelope(): Envelope
    {
        $prefix = match ($this->notificationType) {
            'deadline_soon' => 'Prazo próximo',
            'overdue' => 'Tarefa atrasada',
            'approval' => 'Etapa aprovada',
            'returned' => 'Correção solicitada',
            'rejected' => 'Solicitação rejeitada',
            'completed' => 'Processo concluído',
            default => 'Nova tarefa',
        };

        return new Envelope(subject: "{$prefix}: {$this->taskName}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.task-assigned');
    }
}
