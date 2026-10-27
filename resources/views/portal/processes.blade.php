<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Meus processos · FlowCore BPM</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flowcore-page">
    <div class="fc-list-page">
        <header class="fc-header">
            <a class="fc-brand" href="{{ route('portal.processes.index') }}"><span class="fc-brand-mark">F</span><span><strong>FlowCore</strong><small>BPM</small></span></a>
            <div class="fc-process-name"><span class="fc-eyebrow">ÁREA DO USUÁRIO · {{ auth()->user()->role_label }}</span><h1>Meus processos</h1></div>
            <div class="fc-header-actions"><a class="fc-button fc-button-secondary fc-task-nav-link" href="{{ route('portal.tasks.index') }}" @if ($pendingTaskCount > 0) aria-label="Minhas tarefas, {{ $pendingTaskCount }} pendentes" @endif>Minhas tarefas @if ($pendingTaskCount > 0)<span class="fc-task-nav-count" aria-hidden="true">{{ $pendingTaskCount > 99 ? '99+' : $pendingTaskCount }}</span>@endif</a><form method="POST" action="{{ route('logout') }}">@csrf<button class="fc-button fc-button-secondary" type="submit">Sair</button></form></div>
        </header>
        <main class="fc-list-content">
            <section class="fc-list-heading"><div><span class="fc-eyebrow">PROCESSOS DISPONÍVEIS</span><h2>Seu catálogo</h2><p>A lista contém somente processos publicados aos quais sua conta recebeu acesso.</p></div></section>
            <section class="fc-process-list">
                <div class="fc-list-title"><h3>Disponíveis para você</h3><span>{{ $processes->count() }}</span></div>
                @if ($processes->isEmpty())
                    <div class="fc-empty-state"><strong>Nenhum processo liberado</strong><span>Quando o administrador conceder acesso a um processo, ele aparecerá aqui.</span></div>
                @else
                    <div class="fc-portal-process-grid">
                        @foreach ($processes as $process)
                            @php
                                $processName = mb_strtolower($process->name);
                                $processIcon = str_contains($processName, 'produto') ? 'product' : (str_contains($processName, 'compra') ? 'purchase' : (str_contains($processName, 'cliente') ? 'customer' : (str_contains($processName, 'fornecedor') ? 'supplier' : 'process')));
                            @endphp
                            <article class="fc-portal-process-card">
                                <div class="fc-portal-process-card-heading">
                                    <span class="fc-process-type-icon is-{{ $processIcon }}" aria-hidden="true">
                                        @switch($processIcon)
                                            @case('product')
                                                <svg viewBox="0 0 24 24" fill="none"><path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z"/><path d="m4.5 7.75 7.5 4.5 7.5-4.5M12 12.25V21M8 5.25l8 4.5"/></svg>
                                                @break
                                            @case('purchase')
                                                <svg viewBox="0 0 24 24" fill="none"><path d="M4 5h2l2 11h10l2-8H7"/><circle cx="10" cy="20" r="1.4"/><circle cx="17" cy="20" r="1.4"/><path d="M12 7v6m-3-3h6"/></svg>
                                                @break
                                            @case('customer')
                                                <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c.7-3.4 3.2-5.2 7-5.2s6.3 1.8 7 5.2"/><path d="M18 5v4m-2-2h4"/></svg>
                                                @break
                                            @case('supplier')
                                                <svg viewBox="0 0 24 24" fill="none"><path d="M3 7h11v10H3zM14 10h4l3 3v4h-7"/><circle cx="7" cy="18" r="1.7"/><circle cx="18" cy="18" r="1.7"/></svg>
                                                @break
                                            @default
                                                <svg viewBox="0 0 24 24" fill="none"><rect x="3.5" y="4" width="6" height="6" rx="1.5"/><rect x="14.5" y="14" width="6" height="6" rx="1.5"/><path d="M9.5 7h4a3 3 0 0 1 3 3v4M14.5 17h-4a3 3 0 0 1-3-3v-4"/></svg>
                                        @endswitch
                                    </span>
                                    <span class="fc-status-pill is-published">Publicado</span>
                                </div>
                                <div class="fc-portal-process-card-copy">
                                    <h3>{{ $process->name }}</h3>
                                    <p>{{ $process->description ?: 'Processo disponível para sua conta' }}</p>
                                </div>
                                <div class="fc-portal-process-card-action">
                                    @if ($process->pivot->can_start && is_array($process->form_schema) && count($process->form_schema) > 0)
                                        <a class="fc-button fc-button-primary" href="{{ route('portal.processes.start', $process) }}">Iniciar solicitação <span aria-hidden="true">→</span></a>
                                    @elseif (! $process->pivot->can_start)
                                        <span class="fc-role-label">Acesso para consulta</span>
                                    @else
                                        <span class="fc-role-label">Formulário pendente</span>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
            <section class="fc-process-list">
                <div class="fc-list-title"><h3>Minhas solicitações</h3><span>{{ $instances->count() }}</span></div>
                @forelse ($instances as $instance)
                    <a class="fc-process-row" href="{{ route('portal.requests.show', $instance->uuid) }}">
                        <span class="fc-process-icon">↗</span>
                        <span class="fc-process-row-main"><strong>{{ $instance->processDefinition->name }}</strong><small>Enviada em {{ $instance->created_at->copy()->setTimezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</small></span>
                        <span class="fc-status-pill {{ in_array($instance->status, ['running', 'completed'], true) ? 'is-published' : '' }}">{{ match ($instance->status) { 'running' => 'Em andamento', 'completed' => 'Concluída', default => 'Falha ao iniciar' } }}</span>
                    </a>
                @empty
                    <div class="fc-empty-state"><strong>Você ainda não enviou solicitações</strong><span>Os formulários dos processos liberados aparecem no catálogo acima.</span></div>
                @endforelse
            </section>
        </main>
    </div>
</body>
</html>
