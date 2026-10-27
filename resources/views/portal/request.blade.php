<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Solicitação · {{ $instance->processDefinition->name }} · FlowCore BPM</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flowcore-page">
    <div class="fc-list-page">
        <header class="fc-header">
            <a class="fc-brand" href="{{ route('portal.processes.index') }}"><span class="fc-brand-mark">F</span><span><strong>FlowCore</strong><small>BPM</small></span></a>
            <div class="fc-process-name"><span class="fc-eyebrow">SOLICITAÇÃO</span><h1>{{ $instance->processDefinition->name }}</h1></div>
            <div class="fc-header-actions"><a class="fc-button fc-button-secondary" href="{{ route('portal.processes.index') }}">Meus processos</a><form method="POST" action="{{ route('logout') }}">@csrf<button class="fc-button fc-button-secondary" type="submit">Sair</button></form></div>
        </header>
        <main class="fc-list-content">
            @if (session('status')) <div class="fc-flash-success" role="status">{{ session('status') }}</div> @endif
            <section class="fc-list-heading"><div><span class="fc-eyebrow">ACOMPANHAMENTO</span><h2>Detalhes da solicitação</h2><p>Consulte os dados enviados e as decisões registradas na execução.</p></div></section>
            <dl class="fc-request-summary">
                <div><dt>Processo</dt><dd>{{ $instance->processDefinition->name }} · versão {{ $instance->processDefinition->version }}</dd></div>
                <div><dt>Status</dt><dd>{{ match ($instance->status) { 'running' => 'Em andamento no motor de processos', 'completed' => 'Concluída no motor de processos', default => 'Falha ao iniciar' } }}</dd></div>
                <div><dt>Identificador</dt><dd>{{ $instance->uuid }}</dd></div>
                <div><dt>Enviada em</dt><dd>{{ $instance->created_at->copy()->setTimezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</dd></div>
                @foreach ($instance->processDefinition->form_schema ?? [] as $field)
                    @if (array_key_exists($field['key'], $instance->form_data ?? []))
                        <div><dt>{{ $field['label'] }}</dt><dd>{{ is_bool($instance->form_data[$field['key']]) ? ($instance->form_data[$field['key']] ? 'Sim' : 'Não') : $instance->form_data[$field['key']] }}</dd></div>
                    @endif
                @endforeach
            </dl>
            <section class="fc-current-activity" aria-labelledby="current-activity-title">
                <div class="fc-current-activity-heading">
                    <div><span class="fc-eyebrow">ACOMPANHAMENTO DO FLUXO</span><h3 id="current-activity-title">Onde a solicitação está</h3></div>
                    @if ($instance->status === 'running') <span class="fc-status-pill is-published">Em andamento</span> @elseif ($instance->status === 'completed') <span class="fc-status-pill is-published">Concluída</span> @endif
                </div>
                @if ($instance->status === 'completed')
                    <div class="fc-current-activity-empty"><strong>Processo concluído</strong><span>Todas as atividades do fluxo foram finalizadas.</span></div>
                @elseif ($instance->status === 'running' && ! $activityLookupAvailable)
                    <div class="fc-current-activity-empty is-unavailable"><strong>Etapa atual indisponível</strong><span>Não foi possível consultar o motor agora. Atualize esta página mais tarde; seu histórico continua disponível abaixo.</span></div>
                @elseif ($currentActivities !== [])
                    <ol class="fc-current-activity-list">
                        @foreach ($currentActivities as $activity)
                            <li><span class="fc-activity-step" aria-hidden="true">{{ $loop->iteration }}</span><span class="fc-activity-copy"><strong>{{ $activity['name'] }}</strong><small>{{ $activity['group'] ? 'Responsável: '.$activity['group'] : 'Responsável definido no fluxo' }}</small></span><span class="fc-activity-state">{{ $activity['state'] }}</span></li>
                        @endforeach
                    </ol>
                @elseif ($instance->status === 'running')
                    <div class="fc-current-activity-empty"><strong>Fluxo aguardando uma atividade</strong><span>O motor não tem uma tarefa humana pendente neste momento.</span></div>
                @else
                    <div class="fc-current-activity-empty is-unavailable"><strong>Não foi possível iniciar</strong><span>O fluxo não chegou a uma atividade.</span></div>
                @endif
            </section>
            @if ($instance->decisions->isNotEmpty())
                <section class="fc-process-list fc-decision-history">
                    <div class="fc-list-title"><h3>Histórico de tarefas</h3><span>{{ $instance->decisions->count() }}</span></div>
                    @foreach ($instance->decisions as $decision)
                        @php($taskSchema = $instance->processDefinition->task_form_schemas[$decision->task_definition_key] ?? ['fields' => []])
                        @php($taskFieldLabels = collect($taskSchema['fields'] ?? [])->keyBy('key'))
                        @php($returnTask = isset($decision->form_data['return_to']) ? (\App\Http\Controllers\ProcessTaskAssignmentController::userTasks($instance->processDefinition->bpmn_xml ?? '')[$decision->form_data['return_to']]['name'] ?? $decision->form_data['return_to']) : null)
                        <article class="fc-process-row">
                            <span class="fc-process-icon">✓</span>
                            <span class="fc-process-row-main"><strong>{{ $decision->task_name }}</strong><small>{{ $decision->decision === 'rejected' && $returnTask ? "Devolvida para correção · {$returnTask}" : match ($decision->decision) { 'approved' => 'Aprovada', 'rejected' => 'Rejeitada', default => 'Concluída' } }} por {{ $decision->actor->name }} em {{ $decision->flowable_completed_at?->copy()->setTimezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</small>
                                @if (filled($decision->justification)) <small>{{ $decision->justification }}</small> @endif
                                @foreach ($decision->form_data ?? [] as $key => $value)
                                    @if ($key === 'return_to')
                                        <small>Setor de correção: {{ $returnTask }}</small>
                                    @else
                                        <small>{{ $taskFieldLabels[$key]['label'] ?? $key }}: {{ is_bool($value) ? ($value ? 'Sim' : 'Não') : $value }}</small>
                                    @endif
                                @endforeach
                            </span>
                        </article>
                    @endforeach
                </section>
            @endif
            <a class="fc-button fc-button-primary" href="{{ route('portal.processes.index') }}">Voltar aos processos</a>
        </main>
    </div>
</body>
</html>
