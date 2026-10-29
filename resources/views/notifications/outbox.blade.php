<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Notificações · FlowCore BPM</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flowcore-page">
    <div class="fc-list-page">
        <header class="fc-header">
            <a class="fc-brand" href="{{ route('master.processes.index') }}"><span class="fc-brand-mark">F</span><span><strong>FlowCore</strong><small>BPM</small></span></a>
            <div class="fc-process-name"><span class="fc-eyebrow">ÁREA MASTER</span><h1>Notificações</h1></div>
            <div class="fc-header-actions"><a class="fc-button fc-button-secondary" href="{{ route('master.processes.index') }}">Processos</a><a class="fc-button fc-button-secondary" href="{{ route('master.audit.index') }}">Auditoria</a>@include('shared.profile-link')<form method="POST" action="{{ route('logout') }}">@csrf<button class="fc-button fc-button-secondary" type="submit">Sair</button></form></div>
        </header>
        <main class="fc-list-content">
            <section class="fc-list-heading"><div><span class="fc-eyebrow">OUTBOX DE E-MAIL</span><h2>Acompanhamento dos envios</h2><p>Consulte notificações de tarefas, identifique falhas e coloque um e-mail com falha novamente na fila.</p></div></section>
            @if (session('status')) <div class="fc-flash-success" role="status">{{ session('status') }}</div> @endif
            @error('notification') <div class="fc-login-error" role="alert">{{ $message }}</div> @enderror
            <section class="fc-metrics-grid fc-notification-metrics" aria-label="Resumo de e-mails">
                <article class="fc-metric-card"><span>Total registrados</span><strong>{{ number_format((int) ($summary->total ?? 0), 0, ',', '.') }}</strong><small>Mensagens no outbox</small></article>
                <article class="fc-metric-card is-starting"><span>Na fila</span><strong>{{ number_format((int) ($summary->queued ?? 0), 0, ',', '.') }}</strong><small>Aguardando o worker</small></article>
                <article class="fc-metric-card is-completed"><span>Processados</span><strong>{{ number_format((int) ($summary->sent ?? 0), 0, ',', '.') }}</strong><small>Entregues ao transporte configurado</small></article>
                <article class="fc-metric-card is-attention"><span>Com falha</span><strong>{{ number_format((int) ($summary->failed ?? 0), 0, ',', '.') }}</strong><small>Podem ser reenviados abaixo</small></article>
            </section>
            <section class="fc-process-list fc-notification-outbox">
                <div class="fc-list-title"><h3>Histórico de mensagens</h3><span>{{ $messages->total() }}</span></div>
                <form method="GET" action="{{ route('master.notifications.index') }}" class="fc-outbox-filters">
                    <label class="fc-form-field"><span>Buscar</span><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="ID, processo, etapa ou destinatário"></label>
                    <label class="fc-form-field"><span>Status</span><select name="status"><option value="">Todos</option><option value="queued" @selected(($filters['status'] ?? '') === 'queued')>Na fila</option><option value="sent" @selected(($filters['status'] ?? '') === 'sent')>Processado</option><option value="failed" @selected(($filters['status'] ?? '') === 'failed')>Com falha</option></select></label>
                    <button class="fc-button fc-button-primary" type="submit">Filtrar</button>
                    @if (($filters['search'] ?? '') !== '' || ($filters['status'] ?? '') !== '') <a class="fc-button fc-button-secondary" href="{{ route('master.notifications.index') }}">Limpar</a> @endif
                </form>
                @if ($messages->isEmpty())
                    <div class="fc-empty-state"><strong>Nenhuma mensagem encontrada</strong><span>As notificações geradas por novas tarefas aparecerão aqui.</span></div>
                @else
                    <div class="fc-outbox-table-wrap">
                        <table class="fc-metrics-table fc-outbox-table">
                            <thead><tr><th>Solicitação / tarefa</th><th>Destinatário</th><th>Status</th><th>Atualizado</th><th>Ação</th></tr></thead>
                            <tbody>
                                @foreach ($messages as $message)
                                    <tr>
                                        <th scope="row"><span>{{ $message->process_name }}</span><small>{{ $message->task_name }} · {{ $message->request_code }} · {{ match ($message->notification_type) { 'deadline_soon' => 'Prazo próximo', 'overdue' => 'Tarefa atrasada', 'approval' => 'Etapa aprovada', 'returned' => 'Correção solicitada', 'rejected' => 'Solicitação rejeitada', 'completed' => 'Processo concluído', default => 'Nova tarefa' } }}</small>@if ($message->context)<small>{{ $message->context }}</small>@endif @if ($message->due_at)<small>Prazo: {{ \Illuminate\Support\Carbon::parse($message->due_at)->setTimezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</small>@endif @if ($message->last_error)<small class="fc-outbox-error">{{ $message->last_error }}</small>@endif</th>
                                        <td>{{ $message->recipient_name }}<small>{{ $message->recipient_email }}</small></td>
                                        <td><span class="fc-status-pill {{ $message->status === 'sent' ? 'is-published' : '' }}">{{ match ($message->status) { 'queued' => 'Na fila', 'sent' => 'Processado', default => 'Falha' } }}</span></td>
                                        <td>{{ \Illuminate\Support\Carbon::parse($message->updated_at)->setTimezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</td>
                                        <td>@if ($message->status === 'failed')<form method="POST" action="{{ route('master.notifications.retry', $message->id) }}">@csrf<button class="fc-button fc-button-secondary fc-outbox-retry" type="submit">Reenviar</button></form>@else<span>—</span>@endif</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    {{ $messages->onEachSide(1)->links() }}
                @endif
            </section>
        </main>
    </div>
</body>
</html>
