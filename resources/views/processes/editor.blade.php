<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $process->name }} · FlowCore BPM</title>
    @vite(['resources/css/app.css', 'resources/css/bpmn-editor.css', 'resources/js/app.js'])
</head>
<body class="flowcore-page">
    <div class="fc-editor">
        <header class="fc-header">
            <a class="fc-brand" href="{{ route('master.processes.index') }}" aria-label="FlowCore BPM processos">
                <span class="fc-brand-mark">F</span>
                <span><strong>FlowCore</strong><small>BPM</small></span>
            </a>
            <div class="fc-process-name">
                <span class="fc-eyebrow">{{ $process->status === 'published' ? 'PUBLICADO' : 'RASCUNHO' }} · V{{ $process->version }}</span>
                <h1>{{ $process->name }}</h1>
            </div>
            <div class="fc-header-actions">
                @if ($process->status === 'draft')
                    <a class="fc-button fc-button-secondary" href="{{ route('master.processes.form.edit', $process) }}">Formulário</a>
                    <a class="fc-button fc-button-secondary" href="{{ route('master.processes.tasks.edit', $process) }}">Equipes</a>
                @endif
                @if ($process->status === 'draft')
                    <button id="open-bpmn" class="fc-button fc-button-secondary" type="button">Abrir BPMN</button>
                @endif
                <input id="bpmn-file" type="file" accept=".bpmn,.xml,application/xml,text/xml" hidden>
                @if ($process->status === 'draft')
                    <button id="export-bpmn" class="fc-button fc-button-secondary" type="button">Exportar XML</button>
                    <button id="save-bpmn" class="fc-button fc-button-secondary" type="button">Salvar rascunho</button>
                    <button id="publish-bpmn" class="fc-button fc-button-primary" type="button">Publicar versão</button>
                    @if ($process->version > 1)
                        <form method="POST" action="{{ route('master.processes.versions.destroy', $process) }}" onsubmit="return confirm('Descartar a versão V{{ $process->version }}? Este rascunho será removido. A versão publicada continuará intacta.')">
                            @csrf @method('DELETE')
                            <button class="fc-button fc-button-danger" type="submit">Descartar versão</button>
                        </form>
                    @endif
                @else
                    <a class="fc-button fc-button-secondary" href="{{ route('master.processes.access.edit', $process) }}">Acessos</a>
                    @if ($process->is_active)
                        <form method="POST" action="{{ route('master.processes.versions.store', $process) }}">
                            @csrf
                            <button class="fc-button fc-button-primary" type="submit">Criar nova versão</button>
                        </form>
                        <span class="fc-published-pill">Em uso · publicado em {{ $process->published_at?->copy()->setTimezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</span>
                    @else
                        <form method="POST" action="{{ route('master.processes.activate', $process) }}" onsubmit="return confirm('Ativar V{{ $process->version }} para novas solicitações? As solicitações existentes continuam na versão em que começaram.')">
                            @csrf
                            <button class="fc-button fc-button-primary" type="submit">Ativar esta versão</button>
                        </form>
                        <span class="fc-published-pill">Versão substituída · publicada em {{ $process->published_at?->copy()->setTimezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</span>
                    @endif
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="fc-button fc-button-secondary" type="submit">Sair</button>
                </form>
            </div>
        </header>

        @if (session('status'))
            <div class="fc-editor-flash" role="status">{{ session('status') }}</div>
        @endif

        <main class="fc-main">
            <aside class="fc-panel fc-left-panel">
                <span class="fc-eyebrow">MODELO</span>
                <h2>Elementos BPMN</h2>
                <p>Arraste elementos da paleta para o quadro. Conecte-os para definir a sequência. Configure o grupo responsável em cada tarefa humana.</p>
                <div class="fc-note">
                    <strong>Primeira versão do editor</strong>
                    <span>Início, tarefa humana, conexões e encerramento. Use a paleta do diagrama para adicionar outros elementos BPMN.</span>
                </div>
                <div class="fc-note fc-note-muted">
                    <strong>Rascunho</strong>
                    <span>Salve o rascunho. Ao publicar, a versão fica imutável e é enviada ao motor Flowable.</span>
                </div>
            </aside>

            <section class="fc-canvas-wrap" aria-label="Área de edição BPMN">
                <div class="fc-canvas-toolbar">
                    <div class="fc-breadcrumb"><a href="{{ route('master.processes.index') }}">Processos</a><i>/</i><strong>Editor</strong></div>
                    <div class="fc-zoom-tools" aria-label="Controles de zoom">
                        <button id="zoom-out" type="button" aria-label="Diminuir zoom" title="Diminuir zoom">−</button>
                        <button id="fit-canvas" type="button" aria-label="Ajustar diagrama" title="Ajustar diagrama">Ajustar</button>
                        <button id="zoom-in" type="button" aria-label="Aumentar zoom" title="Aumentar zoom">+</button>
                    </div>
                </div>
                <div id="bpmn-canvas" data-initial-xml="{{ $process->bpmn_xml ?? '' }}" data-save-url="{{ route('master.processes.diagram.update', $process) }}" data-publish-url="{{ route('master.processes.publish', $process) }}" data-published="{{ $process->status === 'published' ? 'true' : 'false' }}"></div>
                <div class="fc-canvas-status"><span class="fc-status-dot"></span><span id="editor-status" role="status" aria-live="polite">Carregando diagrama…</span></div>
            </section>

            <aside class="fc-panel fc-right-panel">
                <span class="fc-eyebrow">PROPRIEDADES</span>
                <h2>Elemento selecionado</h2>
                <div id="element-properties-empty" class="fc-property-empty">
                    <p>Selecione um elemento para alterar suas propriedades. Tarefas humanas também aceitam instruções para o executor.</p>
                    <ol class="fc-instructions">
                        <li><span>1</span><p>Selecione um elemento no diagrama.</p></li>
                        <li><span>2</span><p>Edite o nome ou as instruções da tarefa e aplique a alteração.</p></li>
                        <li><span>3</span><p>Salve o rascunho para persistir as alterações.</p></li>
                    </ol>
                </div>
                <form id="element-properties-form" class="fc-element-properties-form" hidden>
                    <div class="fc-property-element-type"><span class="fc-eyebrow">TIPO DE ELEMENTO</span><strong id="selected-element-type"></strong></div>
                    <div class="fc-form-field"><label for="selected-element-name">Nome exibido</label><input id="selected-element-name" name="name" maxlength="120" autocomplete="off" @disabled($process->status === 'published')></div>
                    <div id="task-instructions-field" class="fc-form-field" hidden><label for="selected-task-instructions">Instruções para quem executa</label><textarea id="selected-task-instructions" rows="5" maxlength="2000" placeholder="Explique o que o grupo deve fazer nesta etapa." @disabled($process->status === 'published')></textarea></div>
                    <div class="fc-property-element-id"><span class="fc-eyebrow">IDENTIFICADOR BPMN</span><code id="selected-element-id"></code></div>
                    <button id="apply-element-name" class="fc-button fc-button-primary" type="submit" @disabled($process->status === 'published')>Aplicar propriedades</button>
                    <p class="fc-property-hint">O identificador fica protegido porque pode ser usado no roteamento de equipes e decisões.</p>
                    @if ($process->status === 'published') <p class="fc-property-hint">Esta versão publicada é somente leitura.</p> @endif
                </form>
                <div class="fc-selection-card">
                    <span class="fc-eyebrow">VALIDAÇÃO</span>
                    <p>Antes de publicar, o servidor valida o BPMN 2.0 e testa a criação da definição no Flowable. Uma publicação não substitui processos em execução.</p>
                </div>
            </aside>
        </main>
    </div>
</body>
</html>
