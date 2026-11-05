<details class="fc-nav-dropdown fc-process-actions-dropdown">
    <summary class="fc-button fc-button-secondary">@include('shared.nav-icon', ['icon' => 'editor'])Ações do processo<span class="fc-nav-chevron" aria-hidden="true"></span></summary>
    <div class="fc-nav-dropdown-menu">
        <span class="fc-nav-dropdown-heading">Configuração</span>
        <a href="{{ route('master.processes.editor', $process) }}" @if (($currentPage ?? '') === 'editor') aria-current="page" @endif>@include('shared.nav-icon', ['icon' => 'editor'])Diagrama</a>
        <a href="{{ route('master.processes.form.edit', $process) }}" @if (($currentPage ?? '') === 'form') aria-current="page" @endif>@include('shared.nav-icon', ['icon' => 'form'])Formulário</a>
        <a href="{{ route('master.processes.tasks.edit', $process) }}" @if (($currentPage ?? '') === 'tasks') aria-current="page" @endif>@include('shared.nav-icon', ['icon' => 'groups'])Equipes</a>
        @if ($process->status !== 'draft')
            <a href="{{ route('master.processes.access.edit', $process) }}" @if (($currentPage ?? '') === 'access') aria-current="page" @endif>@include('shared.nav-icon', ['icon' => 'access'])Acessos</a>
        @endif

        <span class="fc-nav-dropdown-heading">Diagrama</span>
        @if (($currentPage ?? '') === 'editor' && $process->status === 'draft')
            <button id="open-bpmn" type="button">@include('shared.nav-icon', ['icon' => 'open'])Abrir BPMN</button>
            <button id="export-bpmn" type="button">@include('shared.nav-icon', ['icon' => 'export'])Exportar XML</button>
        @else
            <a href="{{ route('master.processes.editor', $process) }}">@include('shared.nav-icon', ['icon' => 'open'])Abrir diagrama</a>
        @endif

        <span class="fc-nav-dropdown-heading">Publicação</span>
        @if (($currentPage ?? '') === 'editor' && $process->status === 'draft')
            <button id="save-bpmn" type="button">@include('shared.nav-icon', ['icon' => 'save'])Salvar rascunho</button>
            <button id="publish-bpmn" class="fc-nav-action-primary" type="button">@include('shared.nav-icon', ['icon' => 'publish'])Publicar versão</button>
            @if ($process->version > 1)
                <form method="POST" action="{{ route('master.processes.versions.destroy', $process) }}" data-confirm="Descartar a versão V{{ $process->version }}? Este rascunho será removido. A versão publicada continuará intacta.">
                    @csrf @method('DELETE')
                    <button class="fc-nav-action-danger" type="submit">@include('shared.nav-icon', ['icon' => 'discard'])Descartar versão</button>
                </form>
            @endif
        @elseif (($currentPage ?? '') === 'editor')
            @if ($process->is_active)
                <form method="POST" action="{{ route('master.processes.versions.store', $process) }}">
                    @csrf
                    <button class="fc-nav-action-primary" type="submit">Criar nova versão</button>
                </form>
                <span class="fc-nav-dropdown-note">Em uso · publicado em {{ $process->published_at?->copy()->setTimezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</span>
            @else
                <form method="POST" action="{{ route('master.processes.activate', $process) }}" data-confirm="Ativar V{{ $process->version }} para novas solicitações? As solicitações existentes continuam na versão em que começaram.">
                    @csrf
                    <button class="fc-nav-action-primary" type="submit">Ativar esta versão</button>
                </form>
                <span class="fc-nav-dropdown-note">Versão substituída · publicada em {{ $process->published_at?->copy()->setTimezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</span>
            @endif
        @else
            <a href="{{ route('master.processes.editor', $process) }}">@include('shared.nav-icon', ['icon' => 'editor'])Salvar, publicar ou gerenciar versões</a>
        @endif
    </div>
</details>
