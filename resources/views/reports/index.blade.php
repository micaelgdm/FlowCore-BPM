<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('shared.favicon')
    <title>Relatórios · FlowCore BPM</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flowcore-page">
    <div class="fc-list-page">
        <header class="fc-header">
            <a class="fc-brand" href="{{ route('master.processes.index') }}">@include('shared.brand-logo')</a>
            <div class="fc-process-name"><span class="fc-eyebrow">ÁREA MASTER</span><h1>Relatórios</h1></div>
            <div class="fc-header-actions">@include('shared.master-navigation')</div>
        </header>
        <main class="fc-list-content fc-report-page">
            <section class="fc-list-heading">
                <div><span class="fc-eyebrow">ACOMPANHAMENTO OPERACIONAL</span><h2>Relatórios de processos</h2><p>Consulte solicitações, etapas, prazos e grupos em um período. A exportação contém dados de acompanhamento, sem respostas dos formulários.</p></div>
            </section>

            <form method="GET" action="{{ route('master.reports.index') }}" class="fc-report-filters">
                <label class="fc-form-field"><span>De</span><input type="date" name="from" value="{{ $filters['from'] }}" required></label>
                <label class="fc-form-field"><span>Até</span><input type="date" name="to" value="{{ $filters['to'] }}" required></label>
                <label class="fc-form-field"><span>Processo</span><select name="process_id"><option value="">Todos os processos</option>@foreach ($processOptions as $processOption)<option value="{{ $processOption->id }}" @selected((string) $filters['process_id'] === (string) $processOption->id)>{{ $processOption->name }} · V{{ $processOption->version }}</option>@endforeach</select></label>
                <label class="fc-form-field"><span>Situação</span><select name="status"><option value="">Todas</option><option value="running" @selected($filters['status'] === 'running')>Em andamento</option><option value="completed" @selected($filters['status'] === 'completed')>Concluída</option><option value="starting" @selected($filters['status'] === 'starting')>Iniciando</option><option value="failed" @selected($filters['status'] === 'failed')>Falha ao iniciar</option></select></label>
                <label class="fc-form-field"><span>Grupo com tarefa pendente</span><select name="group_id"><option value="">Todos os grupos</option>@foreach ($groupOptions as $groupOption)<option value="{{ $groupOption->id }}" @selected((string) $filters['group_id'] === (string) $groupOption->id)>{{ $groupOption->name }}</option>@endforeach</select></label>
                <div class="fc-report-filter-actions">
                    <button class="fc-button fc-button-primary" type="submit">Aplicar filtros</button>
                    <button class="fc-button fc-button-secondary" type="submit" formaction="{{ route('master.reports.export') }}">@include('shared.nav-icon', ['icon' => 'export'])Exportar CSV</button>
                    <a class="fc-report-clear" href="{{ route('master.reports.index') }}">Limpar</a>
                </div>
                @if ($errors->any())<div class="fc-report-filter-error" role="alert">{{ $errors->first() }}</div>@endif
                @error('from')<small class="fc-form-error">{{ $message }}</small>@enderror
                @error('to')<small class="fc-form-error">{{ $message }}</small>@enderror
            </form>

            @if (! $runtimeAvailable)
                <div class="fc-report-engine-note" role="status">O motor Flowable não respondeu. Os dados das solicitações continuam disponíveis, mas etapa atual, prazos e pendências podem estar incompletos.</div>
            @elseif ($runtimeLimitReached)
                <div class="fc-report-engine-note" role="status">A consulta do motor atingiu o limite de tarefas ativas desta visualização. Refine o período ou os filtros para conferir os resultados.</div>
            @endif

            <section class="fc-metrics-grid fc-report-summary" aria-label="Resumo das solicitações filtradas">
                <article class="fc-metric-card"><span>Solicitações</span><strong>{{ number_format($summary['total'], 0, ',', '.') }}</strong><small>No período selecionado</small></article>
                <article class="fc-metric-card is-running"><span>Em andamento</span><strong>{{ number_format($summary['running'], 0, ',', '.') }}</strong><small>Ainda não concluídas</small></article>
                <article class="fc-metric-card is-completed"><span>Concluídas</span><strong>{{ number_format($summary['completed'], 0, ',', '.') }}</strong><small>Encerradas no motor</small></article>
                <article class="fc-metric-card is-pending"><span>Tarefas pendentes</span><strong>{{ $runtimeAvailable ? number_format($summary['pending_tasks'], 0, ',', '.') : '—' }}</strong><small>Nas solicitações filtradas</small></article>
                <article class="fc-metric-card is-overdue"><span>Tarefas atrasadas</span><strong>{{ $runtimeAvailable ? number_format($summary['overdue_tasks'], 0, ',', '.') : '—' }}</strong><small>Prazo configurado já vencido</small></article>
            </section>

            <section class="fc-report-panel" aria-labelledby="fc-report-requests-title">
                <div class="fc-report-panel-heading"><div><span class="fc-eyebrow">REGISTROS</span><h3 id="fc-report-requests-title">Solicitações</h3></div><span>{{ number_format($requests->total(), 0, ',', '.') }} registros</span></div>
                @if ($requests->isEmpty())
                    <div class="fc-empty-state"><strong>Nenhuma solicitação encontrada</strong><span>Altere o período ou remova algum filtro para ampliar a consulta.</span></div>
                @else
                    <div class="fc-report-table-wrap">
                        <table class="fc-report-table">
                            <thead><tr><th>ID e processo</th><th>Solicitante</th><th>Iniciada em</th><th>Situação</th><th>Etapa atual</th><th>Responsável</th><th>Prazo</th></tr></thead>
                            <tbody>
                                @foreach ($requests as $item)
                                    <tr>
                                        <th scope="row"><span class="fc-report-id">{{ $item['display_id'] }}</span><small>{{ $item['process_name'] }} · V{{ $item['version'] }}</small></th>
                                        <td>{{ $item['requester'] }}</td>
                                        <td>{{ $item['created_at'] }}</td>
                                        <td><span class="fc-report-status is-{{ $item['status'] }}">{{ $item['status_label'] }}</span></td>
                                        <td>{{ $item['current_activity'] }}</td>
                                        <td>{{ $item['responsible'] }}</td>
                                        <td><span class="fc-report-deadline @if ($item['deadline_label'] === 'Atrasada') is-overdue @endif">{{ $item['deadline_label'] }}</span>@if ($item['due_at'] !== '—')<small class="fc-report-cell-note">{{ $item['due_at'] }}</small>@endif</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($requests->hasPages())<div class="fc-pagination">{{ $requests->links() }}</div>@endif
                @endif
            </section>

            <div class="fc-report-lower-grid">
                <section class="fc-report-panel" aria-labelledby="fc-report-stages-title">
                    <div class="fc-report-panel-heading"><div><span class="fc-eyebrow">DURAÇÃO</span><h3 id="fc-report-stages-title">Tempo médio por etapa</h3></div><span>{{ number_format($stageMetrics->sum('sample_count'), 0, ',', '.') }} conclusões</span></div>
                    @if ($stageMetrics->isEmpty())
                        <p class="fc-metrics-empty">Ainda não há tarefas concluídas com horários registrados neste recorte.</p>
                    @else
                        <div class="fc-report-table-wrap"><table class="fc-report-table fc-report-compact-table"><thead><tr><th>Etapa e processo</th><th>Grupo</th><th>Tempo médio</th><th>Concluídas</th></tr></thead><tbody>
                            @foreach ($stageMetrics as $metric)
                                @php($duration = (int) $metric['average_seconds'])
                                @php($days = intdiv($duration, 86400))
                                @php($hours = intdiv($duration % 86400, 3600))
                                @php($minutes = intdiv($duration % 3600, 60))
                                <tr><th scope="row">{{ $metric['task_name'] }}<small>{{ $metric['process'] }} · V{{ $metric['version'] }}</small></th><td>{{ $metric['group_name'] }}</td><td>{{ $days ? $days.' d ' : '' }}{{ $hours ? $hours.' h ' : '' }}{{ $minutes ?: ($days || $hours ? 0 : '<1') }} min</td><td>{{ number_format($metric['sample_count'], 0, ',', '.') }}</td></tr>
                            @endforeach
                        </tbody></table></div>
                    @endif
                </section>

                <section class="fc-report-panel" aria-labelledby="fc-report-groups-title">
                    <div class="fc-report-panel-heading"><div><span class="fc-eyebrow">CARGA ATUAL</span><h3 id="fc-report-groups-title">Pendências por grupo</h3></div><span>{{ number_format($summary['pending_tasks'], 0, ',', '.') }} tarefas</span></div>
                    @if (! $runtimeAvailable)
                        <p class="fc-metrics-empty">Os dados do motor não estão disponíveis neste momento.</p>
                    @elseif ($pendingGroupMetrics->isEmpty())
                        <p class="fc-metrics-empty">Não há tarefas pendentes de grupos nas solicitações filtradas.</p>
                    @else
                        <div class="fc-report-table-wrap"><table class="fc-report-table fc-report-compact-table"><thead><tr><th>Grupo responsável</th><th>Pendentes</th><th>Atrasadas</th></tr></thead><tbody>
                            @foreach ($pendingGroupMetrics as $metric)
                                <tr><th scope="row">{{ $metric['group_name'] }}</th><td>{{ number_format($metric['pending_count'], 0, ',', '.') }}</td><td>{{ number_format($metric['overdue_count'], 0, ',', '.') }}</td></tr>
                            @endforeach
                        </tbody></table></div>
                    @endif
                </section>
            </div>
        </main>
    </div>
</body>
</html>
