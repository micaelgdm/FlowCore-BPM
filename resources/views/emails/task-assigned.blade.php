<!DOCTYPE html>
<html lang="pt-BR">
<body style="margin:0;padding:32px;background:#f3f5f8;color:#182230;font-family:Arial,sans-serif">
    <main style="max-width:560px;margin:0 auto;padding:28px;border:1px solid #e1e6ee;border-radius:16px;background:#fff">
        <p style="margin:0 0 8px;color:#315fe8;font-size:12px;font-weight:bold;letter-spacing:.08em;text-transform:uppercase">FlowCore BPM · {{ match ($notificationType) { 'deadline_soon' => 'Prazo próximo', 'overdue' => 'Tarefa atrasada', 'approval' => 'Etapa aprovada', 'returned' => 'Correção solicitada', 'rejected' => 'Solicitação rejeitada', 'completed' => 'Processo concluído', default => 'Nova tarefa' } }}</p>
        <h1 style="margin:0 0 20px;font-size:22px">Olá, {{ $recipientName }}</h1>
        <p>{{ match ($notificationType) { 'deadline_soon' => 'Esta tarefa está próxima do prazo.', 'overdue' => 'O prazo desta tarefa venceu.', 'approval' => 'Uma etapa de aprovação da sua solicitação foi concluída.', 'returned' => 'Sua solicitação foi devolvida para correção.', 'rejected' => 'Sua solicitação foi rejeitada.', 'completed' => 'Todas as etapas do processo foram concluídas.', default => 'Uma tarefa foi direcionada para você:' } }}</p>
        <p><strong>Processo:</strong> {{ $processName }}<br><strong>Etapa:</strong> {{ $taskName }}<br><strong>Solicitação:</strong> {{ $requestCode }}</p>
        @if ($context)<p>{{ $context }}</p>@endif
        @if ($dueAtText)<p><strong>Prazo:</strong> {{ $dueAtText }}</p>@endif
        <p style="margin:26px 0"><a href="{{ $taskUrl }}" style="display:inline-block;padding:12px 18px;border-radius:9px;background:#315fe8;color:#fff;font-weight:bold;text-decoration:none">{{ in_array($notificationType, ['assigned', 'deadline_soon', 'overdue'], true) ? 'Abrir minhas tarefas' : 'Abrir minha solicitação' }}</a></p>
        <p style="color:#6c788b;font-size:12px">Entre no FlowCore BPM para consultar os dados e o histórico.</p>
    </main>
</body>
</html>
