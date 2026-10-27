<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Auditoria · FlowCore BPM</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flowcore-page">
    <div class="fc-list-page">
        <header class="fc-header">
            <a class="fc-brand" href="{{ route('master.processes.index') }}"><span class="fc-brand-mark">F</span><span><strong>FlowCore</strong><small>BPM</small></span></a>
            <div class="fc-process-name"><span class="fc-eyebrow">ÁREA MASTER</span><h1>Auditoria</h1></div>
            <div class="fc-header-actions"><a class="fc-button fc-button-secondary" href="{{ route('master.processes.index') }}">Processos</a><form method="POST" action="{{ route('logout') }}">@csrf<button class="fc-button fc-button-secondary" type="submit">Sair</button></form></div>
        </header>
        <main class="fc-list-content">
            <section class="fc-list-heading"><div><span class="fc-eyebrow">REGISTRO ADMINISTRATIVO</span><h2>Histórico de alterações</h2><p>Consulte publicações, versões e alterações de configuração feitas no sistema.</p></div></section>
            @if ($events->isEmpty())
                <div class="fc-empty-state"><strong>Nenhum evento registrado</strong><span>As próximas publicações e ativações de versões aparecerão aqui.</span></div>
            @else
                <div class="fc-audit-list">
                    @foreach ($events as $event)
                        <article class="fc-audit-event">
                            <span class="fc-audit-marker" aria-hidden="true"></span>
                            <div class="fc-audit-event-main">
                                <div class="fc-audit-event-heading"><strong>{{ match ($event->action) { 'process.version.published' => 'Versão publicada', 'process.version.activated' => 'Versão ativada', 'process.version.discarded' => 'Rascunho excluído', 'process.family.deleted' => 'Processo excluído', 'process.form.updated' => 'Formulário inicial alterado', 'process.tasks.updated' => 'Configuração das etapas alterada', 'process.access.updated' => 'Permissões de acesso alteradas', default => 'Alteração registrada' } }}</strong><time datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->copy()->setTimezone(config('app.display_timezone'))->format('d/m/Y H:i:s') }}</time></div>
                                <p><strong>{{ $event->subject }}</strong> · {{ $event->actor_name }}</p>
                                @if ($event->action === 'process.version.activated' && filled($event->details['previous_version'] ?? null))
                                    <small>Versão anterior em uso: V{{ $event->details['previous_version'] }}</small>
                                @endif
                                @if ($event->action === 'process.family.deleted' && ! empty($event->details['versions']))
                                    <small>Versões removidas: {{ collect($event->details['versions'])->map(fn ($version) => 'V'.$version)->join(', ') }}</small>
                                @endif
                                @if ($event->action === 'process.tasks.updated' && ! empty($event->details['changed_sections']))
                                    <small>Alterado: {{ collect($event->details['changed_sections'])->map(fn ($section) => match ($section) { 'task_group_assignments' => 'roteamento de grupos', 'task_form_schemas' => 'formulários e prazos das etapas', 'gateway_routes' => 'saídas dos gateways', default => $section })->join(', ') }}</small>
                                @endif
                                @if ($event->action === 'process.form.updated')
                                    <small>Campos do formulário: {{ count($event->details['fields'] ?? []) }}</small>
                                @endif
                                @if ($event->action === 'process.access.updated')
                                    <small>Contas autorizadas: {{ count($event->details['permissions'] ?? []) }}</small>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
                {{ $events->links() }}
            @endif
        </main>
    </div>
</body>
</html>
