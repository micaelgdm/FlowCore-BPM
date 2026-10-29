<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Minhas solicitações · FlowCore BPM</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flowcore-page">
    <div class="fc-list-page">
        <header class="fc-header">
            <a class="fc-brand" href="{{ route('portal.processes.index') }}"><span class="fc-brand-mark">F</span><span><strong>FlowCore</strong><small>BPM</small></span></a>
            <div class="fc-process-name"><span class="fc-eyebrow">ÁREA DO USUÁRIO · {{ auth()->user()->role_label }}</span><h1>Minhas solicitações</h1></div>
            <div class="fc-header-actions"><a class="fc-button fc-button-secondary" href="{{ route('portal.tasks.index') }}">Minhas tarefas</a><a class="fc-button fc-button-secondary" href="{{ route('portal.processes.index') }}">Meus processos</a>@include('shared.profile-link')<form method="POST" action="{{ route('logout') }}">@csrf<button class="fc-button fc-button-secondary" type="submit">Sair</button></form></div>
        </header>
        <main class="fc-list-content">
            <section class="fc-list-heading"><div><span class="fc-eyebrow">ACOMPANHAMENTO</span><h2>Solicitações iniciadas por você</h2><p>Acompanhe o status e abra uma solicitação para consultar as atividades e o histórico.</p></div></section>
            <section class="fc-process-list">
                <div class="fc-list-title"><h3>Histórico de solicitações</h3><span>{{ $instances->total() }}</span></div>
                @forelse ($instances as $instance)
                    <a class="fc-process-row" href="{{ route('portal.requests.show', $instance->uuid) }}">
                        <span class="fc-process-icon">↗</span>
                        <span class="fc-process-row-main"><strong>{{ $instance->processDefinition->name }}</strong><small>ID {{ $instance->displayId() }} · Enviada em {{ $instance->created_at->copy()->setTimezone(config('app.display_timezone'))->format('d/m/Y H:i') }} · V{{ $instance->processDefinition->version }}</small></span>
                        <span class="fc-status-pill {{ in_array($instance->status, ['running', 'completed'], true) ? 'is-published' : '' }}">{{ match ($instance->status) { 'running' => 'Em andamento', 'completed' => 'Concluída', 'cancelled' => 'Encerrada', default => 'Falha ao iniciar' } }}</span>
                    </a>
                @empty
                    <div class="fc-empty-state"><strong>Você ainda não enviou solicitações</strong><span>As solicitações iniciadas nos processos liberados aparecerão aqui.</span></div>
                @endforelse
            </section>
            @if ($instances->hasPages())
                <div class="fc-pagination">{{ $instances->onEachSide(2)->links() }}</div>
            @endif
        </main>
    </div>
</body>
</html>
