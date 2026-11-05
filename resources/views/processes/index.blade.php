<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('shared.favicon')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Processos · FlowCore BPM</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flowcore-page">
    <div class="fc-list-page">
        <header class="fc-header">
            <a class="fc-brand" href="{{ route('master.processes.index') }}">
                @include('shared.brand-logo')
            </a>
            <div class="fc-process-name"><span class="fc-eyebrow">ÁREA MASTER</span><h1>Processos</h1></div>
            <div class="fc-header-actions">
                @include('shared.master-navigation')
            </div>
        </header>
        <main class="fc-list-content fc-dashboard-content">
            <section class="fc-list-heading fc-dashboard-heading">
                <div><span class="fc-eyebrow">ÁREA MASTER</span><h2>Resumo operacional</h2><p>Acompanhe solicitações, prazos e produtividade em um só lugar.</p></div>
                <a class="fc-button fc-button-secondary fc-dashboard-report-link" href="{{ route('master.reports.index') }}">@include('shared.nav-icon', ['icon' => 'reports'])Ver relatórios</a>
            </section>
            @if (session('status')) <div class="fc-flash-success" role="status">{{ session('status') }}</div> @endif
            @error('process') <div class="fc-login-error" role="alert">{{ $message }}</div> @enderror
            <section class="fc-metrics-section" aria-labelledby="fc-metrics-title">
                <div class="fc-dashboard-section-heading"><h3 id="fc-metrics-title">Indicadores</h3><span>Solicitações e tarefas de todas as versões</span></div>
                <div class="fc-metrics-grid">
                    <article class="fc-metric-card"><span>Total de solicitações</span><strong>{{ number_format((int) ($requestMetrics->total_count ?? 0), 0, ',', '.') }}</strong><small>Registros iniciados no portal</small></article>
                    <article class="fc-metric-card is-running"><span>Em andamento</span><strong>{{ number_format((int) ($requestMetrics->running_count ?? 0), 0, ',', '.') }}</strong><small>Solicitações ainda abertas</small></article>
                    <article class="fc-metric-card is-completed"><span>Concluídas</span><strong>{{ number_format((int) ($requestMetrics->completed_count ?? 0), 0, ',', '.') }}</strong><small>Encerradas no motor</small></article>
                    <article class="fc-metric-card is-pending"><span>Tarefas pendentes</span><strong>{{ $runtimeMetricsAvailable ? number_format((int) $pendingTaskCount, 0, ',', '.') : '—' }}</strong><small>{{ $runtimeMetricsAvailable ? 'Aguardando ação dos responsáveis' : 'Motor indisponível para consulta' }}</small></article>
                    <article class="fc-metric-card is-overdue"><span>Tarefas atrasadas</span><strong>{{ $runtimeMetricsAvailable ? number_format((int) $overdueTaskCount, 0, ',', '.') : '—' }}</strong><small>{{ $runtimeMetricsAvailable ? 'Com prazo configurado já vencido' : 'Motor indisponível para consulta' }}</small></article>
                </div>
            </section>
            <section class="fc-dashboard-summary-grid" aria-label="Solicitações e pendências">
                <div class="fc-metrics-table-wrap fc-dashboard-panel">
                    <div class="fc-dashboard-panel-heading"><h3>Solicitações por processo</h3><span>Visão geral</span></div>
                    @if ($processMetrics->isEmpty())
                        <p class="fc-metrics-empty fc-dashboard-empty">Os indicadores por processo aparecerão quando houver definições cadastradas.</p>
                    @else
                        <div class="fc-dashboard-table-scroll">
                            <table class="fc-metrics-table fc-dashboard-process-table">
                                <thead><tr><th>Processo</th><th>Total</th><th>Em andamento</th><th>Concluídas</th></tr></thead>
                                <tbody>
                                    @foreach ($processMetrics as $metric)
                                        <tr><th scope="row"><span>{{ $metric->process_name }}</span><small>{{ $metric->active_version ? 'Em uso · V'.$metric->active_version : 'Sem versão publicada ativa' }}</small></th><td>{{ number_format((int) $metric->total_count, 0, ',', '.') }}</td><td>{{ number_format((int) $metric->running_count, 0, ',', '.') }}</td><td>{{ number_format((int) $metric->completed_count, 0, ',', '.') }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                <div class="fc-metrics-table-wrap fc-dashboard-panel fc-dashboard-groups-panel">
                    <div class="fc-dashboard-panel-heading"><h3>Pendências por grupo</h3><span>Fila atual</span></div>
                    @if (! $runtimeMetricsAvailable)
                        <p class="fc-metrics-empty fc-dashboard-empty">Conecte o motor Flowable para consultar as tarefas ativas.</p>
                    @elseif ($pendingGroupMetrics->isEmpty())
                        <p class="fc-metrics-empty fc-dashboard-empty">Não há tarefas ativas atribuídas a grupos no momento.</p>
                    @else
                        @php($maxGroupPending = max(1, (int) $pendingGroupMetrics->max('pending_count')))
                        <div class="fc-group-pending-list">
                            @foreach ($pendingGroupMetrics as $metric)
                                @php($barWidth = max(5, (int) round(((int) $metric->pending_count / $maxGroupPending) * 100)))
                                <div class="fc-group-pending-row">
                                    <div class="fc-group-pending-heading"><strong>{{ $metric->group_name }}</strong><span>{{ number_format($metric->pending_count, 0, ',', '.') }} pendentes</span></div>
                                    <div class="fc-group-pending-track" role="progressbar" aria-label="Tarefas pendentes de {{ $metric->group_name }}" aria-valuemin="0" aria-valuemax="{{ $maxGroupPending }}" aria-valuenow="{{ $metric->pending_count }}"><span style="width: {{ $barWidth }}%"></span></div>
                                    <small class="fc-group-pending-overdue">{{ number_format($metric->overdue_count, 0, ',', '.') }} atrasadas</small>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
            <section class="fc-metrics-section fc-operational-section" aria-labelledby="fc-operational-title">
                <div class="fc-dashboard-section-heading"><h3 id="fc-operational-title">Tempo médio por etapa</h3><span>Concluídas nos últimos 90 dias</span></div>
                <div class="fc-metrics-table-wrap fc-dashboard-panel">
                    @if ($stepMetrics->isEmpty())
                        <p class="fc-metrics-empty fc-dashboard-empty">Os tempos aparecerão conforme novas tarefas forem concluídas com o motor Flowable.</p>
                    @else
                        <div class="fc-dashboard-table-scroll">
                            <table class="fc-metrics-table">
                                <thead><tr><th>Processo e etapa</th><th>Média</th><th>Concluídas</th></tr></thead>
                                <tbody>
                                    @foreach ($stepMetrics as $metric)
                                        @php($duration = (int) $metric->average_seconds)
                                        @php($days = intdiv($duration, 86400))
                                        @php($hours = intdiv($duration % 86400, 3600))
                                        @php($minutes = intdiv($duration % 3600, 60))
                                        <tr><th scope="row"><span>{{ $metric->task_name }}</span><small>{{ $metric->process_name }} · V{{ $metric->version }}</small></th><td>{{ $days ? $days.' d ' : '' }}{{ $hours ? $hours.' h ' : '' }}{{ $minutes ?: ($days || $hours ? 0 : '<1') }} min</td><td>{{ number_format($metric->sample_count, 0, ',', '.') }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
            </section>
            <section class="fc-metrics-table-wrap fc-dashboard-panel fc-recent-activity" aria-labelledby="fc-recent-activity-title">
                <div class="fc-dashboard-panel-heading"><div><h3 id="fc-recent-activity-title">Atividades recentes</h3><span>Últimas solicitações iniciadas no portal</span></div></div>
                @if ($recentRequests->isEmpty())
                    <p class="fc-metrics-empty fc-dashboard-empty">As solicitações recentes aparecerão aqui quando alguém iniciar um processo.</p>
                @else
                    <div class="fc-dashboard-table-scroll">
                        <table class="fc-metrics-table fc-activity-table">
                            <thead><tr><th>Atividade</th><th>Processo</th><th>Solicitante</th><th>Status</th><th>Data</th></tr></thead>
                            <tbody>
                                @foreach ($recentRequests as $activity)
                                    @php($activityStatus = match ($activity->status) {
                                        'running' => ['label' => 'Em andamento', 'class' => 'is-running', 'activity' => 'Solicitação iniciada'],
                                        'completed' => ['label' => 'Concluída', 'class' => 'is-completed', 'activity' => 'Solicitação concluída'],
                                        'starting' => ['label' => 'Inicializando', 'class' => 'is-starting', 'activity' => 'Solicitação em inicialização'],
                                        'failed' => ['label' => 'Falha ao iniciar', 'class' => 'is-failed', 'activity' => 'Falha ao iniciar solicitação'],
                                        default => ['label' => 'Indisponível', 'class' => 'is-unknown', 'activity' => 'Solicitação registrada'],
                                    })
                                    <tr>
                                        <th scope="row"><span>{{ $activityStatus['activity'] }}</span><small>{{ $activity->displayId() }}</small></th>
                                        <td>{{ $activity->processDefinition?->name ?? 'Processo removido' }}</td>
                                        <td>{{ $activity->starter?->name ?? 'Conta removida' }}</td>
                                        <td><span @class(['fc-activity-status', $activityStatus['class']])>{{ $activityStatus['label'] }}</span></td>
                                        <td><time datetime="{{ $activity->created_at->toIso8601String() }}">{{ $activity->created_at->copy()->setTimezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</time></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
            <section class="fc-create-card">
                <h3>Novo processo</h3>
                <form method="POST" action="{{ route('master.processes.store') }}" class="fc-create-form">
                    @csrf
                    <div class="fc-form-field">
                        <label for="name">Nome</label>
                        <input id="name" name="name" maxlength="160" value="{{ old('name') }}" placeholder="Ex.: Aprovação de compras" required>
                        @error('name') <small class="fc-form-error">{{ $message }}</small> @enderror
                    </div>
                    <div class="fc-form-field">
                        <label for="description">Descrição <span>(opcional)</span></label>
                        <input id="description" name="description" maxlength="2000" value="{{ old('description') }}" placeholder="Para que serve este processo?">
                        @error('description') <small class="fc-form-error">{{ $message }}</small> @enderror
                    </div>
                    <button class="fc-button fc-button-primary" type="submit">Criar rascunho</button>
                </form>
            </section>
            <section class="fc-process-list">
                <div class="fc-list-title"><h3>Seus processos</h3><span>{{ $processes->count() }}</span></div>
                @if ($processes->isNotEmpty()) <p class="fc-version-usage-note">A versão <strong>Em uso</strong> recebe novas solicitações. Use <strong>Ativar</strong> em outra versão publicada para voltar atrás; solicitações existentes continuam na versão em que começaram.</p> @endif
                @forelse ($processes as $process)
                    @php($isCurrentPublished = $process->status === 'published' && ($currentPublishedByFamily[$process->family_uuid] ?? null) === $process->id)
                    <div class="fc-process-row-wrap">
                        <a class="fc-process-row" href="{{ route('master.processes.editor', $process) }}">
                            <span class="fc-process-icon">↗</span>
                            <span class="fc-process-row-main"><strong>{{ $process->name }}</strong><small>{{ $process->description ?: 'Sem descrição' }}</small></span>
                            <span @class(['fc-status-pill', 'is-published' => $isCurrentPublished, 'is-superseded' => $process->status === 'published' && ! $isCurrentPublished])>{{ $process->status === 'draft' ? 'Rascunho · V'.$process->version : ($isCurrentPublished ? 'Em uso · V'.$process->version : 'Substituída · V'.$process->version) }}</span>
                            <span class="fc-row-date">Atualizado {{ $process->updated_at->copy()->setTimezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</span>
                        </a>
                        <div class="fc-process-row-actions">
                            @if ($process->status === 'published' && ! $isCurrentPublished)
                                <form method="POST" action="{{ route('master.processes.activate', $process) }}" data-confirm="Ativar V{{ $process->version }} para novas solicitações? As solicitações existentes continuam na versão em que começaram.">
                                    @csrf
                                    <button class="fc-button fc-button-secondary" type="submit" aria-label="Ativar V{{ $process->version }} de {{ $process->name }}">Ativar</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('master.processes.destroy', $process) }}" class="fc-process-row-delete" data-confirm="Excluir permanentemente este processo e todas as versões? A exclusão só prosseguirá se nenhuma versão tiver solicitações ou histórico no Flowable.">
                                @csrf @method('DELETE')
                                <button class="fc-button fc-button-danger" type="submit" aria-label="Excluir {{ $process->name }} e todas as versões" title="Excluir processo e todas as versões">Excluir</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="fc-empty-state"><strong>Nenhum processo ainda</strong><span>Crie um rascunho acima para abrir o editor BPMN.</span></div>
                @endforelse
            </section>
        </main>
    </div>
</body>
</html>
