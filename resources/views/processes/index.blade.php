<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Processos · FlowCore BPM</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flowcore-page">
    <div class="fc-list-page">
        <header class="fc-header">
            <a class="fc-brand" href="{{ route('master.processes.index') }}">
                <span class="fc-brand-mark">F</span>
                <span><strong>FlowCore</strong><small>BPM</small></span>
            </a>
            <div class="fc-process-name"><span class="fc-eyebrow">ÁREA MASTER</span><h1>Processos</h1></div>
            <div class="fc-header-actions">
                <a class="fc-button fc-button-secondary" href="{{ route('master.users.index') }}">Usuários</a>
                <a class="fc-button fc-button-secondary" href="{{ route('master.groups.index') }}">Grupos</a>
                <a class="fc-button fc-button-secondary" href="{{ route('master.audit.index') }}">Auditoria</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="fc-button fc-button-secondary" type="submit">Sair</button>
                </form>
            </div>
        </header>
        <main class="fc-list-content">
            <section class="fc-list-heading">
                <div><span class="fc-eyebrow">DESENHO E CONFIGURAÇÃO</span><h2>Definições de processo</h2><p>Crie rascunhos BPMN e continue a edição quando precisar.</p></div>
            </section>
            @if (session('status')) <div class="fc-flash-success" role="status">{{ session('status') }}</div> @endif
            @error('process') <div class="fc-login-error" role="alert">{{ $message }}</div> @enderror
            <section class="fc-metrics-section" aria-labelledby="fc-metrics-title">
                <div class="fc-metrics-heading"><div><span class="fc-eyebrow">ACOMPANHAMENTO</span><h3 id="fc-metrics-title">Indicadores das solicitações</h3></div><small>Visão geral de todas as versões</small></div>
                <div class="fc-metrics-grid">
                    <article class="fc-metric-card"><span>Total de solicitações</span><strong>{{ number_format((int) ($requestMetrics->total_count ?? 0), 0, ',', '.') }}</strong><small>Registros iniciados no portal</small></article>
                    <article class="fc-metric-card is-running"><span>Em andamento</span><strong>{{ number_format((int) ($requestMetrics->running_count ?? 0), 0, ',', '.') }}</strong><small>Solicitações ainda abertas</small></article>
                    <article class="fc-metric-card is-completed"><span>Concluídas</span><strong>{{ number_format((int) ($requestMetrics->completed_count ?? 0), 0, ',', '.') }}</strong><small>Encerradas no motor</small></article>
                    <article class="fc-metric-card is-attention"><span>Falha ao iniciar</span><strong>{{ number_format((int) ($requestMetrics->failed_count ?? 0), 0, ',', '.') }}</strong><small>Exigem revisão ou novo envio</small></article>
                    <article class="fc-metric-card is-starting"><span>Inicialização pendente</span><strong>{{ number_format((int) ($requestMetrics->pending_start_count ?? 0), 0, ',', '.') }}</strong><small>Aguardando confirmação do motor</small></article>
                </div>
                <div class="fc-metrics-table-wrap">
                    <div class="fc-metrics-heading"><div><h4>Solicitações por processo</h4></div></div>
                    @if ($processMetrics->isEmpty())
                        <p class="fc-metrics-empty">Os indicadores por processo aparecerão quando houver definições cadastradas.</p>
                    @else
                        <table class="fc-metrics-table">
                            <thead><tr><th>Processo</th><th>Total</th><th>Em andamento</th><th>Concluídas</th><th>Falha ao iniciar</th></tr></thead>
                            <tbody>
                                @foreach ($processMetrics as $metric)
                                    <tr><th scope="row"><span>{{ $metric->process_name }}</span><small>{{ $metric->active_version ? 'Em uso · V'.$metric->active_version : 'Sem versão publicada ativa' }}</small></th><td>{{ number_format((int) $metric->total_count, 0, ',', '.') }}</td><td>{{ number_format((int) $metric->running_count, 0, ',', '.') }}</td><td>{{ number_format((int) $metric->completed_count, 0, ',', '.') }}</td><td>{{ number_format((int) $metric->failed_count, 0, ',', '.') }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
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
