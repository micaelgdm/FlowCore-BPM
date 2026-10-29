<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acessos · {{ $process->name }} · FlowCore BPM</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flowcore-page">
    <div class="fc-list-page">
        <header class="fc-header">
            <a class="fc-brand" href="{{ route('master.processes.index') }}"><span class="fc-brand-mark">F</span><span><strong>FlowCore</strong><small>BPM</small></span></a>
            <div class="fc-process-name"><span class="fc-eyebrow">PERMISSÕES · V{{ $process->version }}</span><h1>{{ $process->name }}</h1></div>
            <div class="fc-header-actions">
                <a class="fc-button fc-button-secondary" href="{{ route('master.processes.editor', $process) }}">Voltar ao processo</a>
                @include('shared.profile-link')
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="fc-button fc-button-secondary" type="submit">Sair</button></form>
            </div>
        </header>
        <main class="fc-list-content">
            <section class="fc-list-heading"><div><span class="fc-eyebrow">PERMISSÕES DO PROCESSO</span><h2>Quem pode ver e solicitar?</h2><p>Defina quem pode consultar e iniciar este processo. Quem executa cada etapa, inclusive as aprovações, é definido pelo grupo responsável no roteamento das tarefas.</p></div></section>
            @if (session('status')) <div class="fc-flash-success" role="status">{{ session('status') }}</div> @endif
            @error('permissions') <div class="fc-login-error" role="alert">{{ $message }}</div> @enderror
            <section class="fc-create-card">
                <form method="POST" action="{{ route('master.processes.access.update', $process) }}">
                    @csrf @method('PUT')
                    <div class="fc-access-list">
                        <details class="fc-member-picker" data-access-picker>
                            <summary><span>Selecionar usuários com acesso</span><strong data-access-count>{{ $accessibleUserCount }} {{ $accessibleUserCount === 1 ? 'usuário liberado' : 'usuários liberados' }}</strong><span class="fc-group-summary-toggle" aria-hidden="true"></span></summary>
                            <div class="fc-member-picker-panel">
                                <label class="fc-member-search">
                                    <span>Buscar por nome ou e-mail</span>
                                    <input type="search" data-access-search placeholder="Digite para filtrar usuários" autocomplete="off">
                                </label>
                                <div class="fc-access-user-list" data-access-list>
                        @forelse ($users as $user)
                            @php
                                $access = $permissions[$user->id] ?? [];
                            @endphp
                            <div class="fc-access-user-row" data-access-option>
                                <div class="fc-access-identity"><strong>{{ $user->name }}</strong><small>{{ $user->email }} · {{ $user->role_label }}</small></div>
                                <div class="fc-access-permissions">
                                    <label><input type="hidden" name="permissions[{{ $user->id }}][can_view]" value="0"><input type="checkbox" name="permissions[{{ $user->id }}][can_view]" value="1" @checked($access['can_view'] ?? false)> Ver</label>
                                    <label><input type="hidden" name="permissions[{{ $user->id }}][can_start]" value="0"><input type="checkbox" name="permissions[{{ $user->id }}][can_start]" value="1" @checked($access['can_start'] ?? false)> Solicitar</label>
                                </div>
                            </div>
                        @empty
                            <div class="fc-empty-state"><strong>Nenhuma conta elegível</strong><span>Cadastre usuários para definir quem pode consultar ou iniciar este processo.</span></div>
                        @endforelse
                                    <p class="fc-member-no-results" data-access-empty hidden>Nenhum usuário corresponde à busca.</p>
                                </div>
                            </div>
                        </details>
                    </div>
                    <button class="fc-button fc-button-primary" type="submit">Salvar permissões</button>
                </form>
            </section>
        </main>
    </div>
</body>
</html>
