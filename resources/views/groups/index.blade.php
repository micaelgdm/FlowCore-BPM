<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Grupos · FlowCore BPM</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flowcore-page">
    <div class="fc-list-page">
        <header class="fc-header">
            <a class="fc-brand" href="{{ route('master.processes.index') }}"><span class="fc-brand-mark">F</span><span><strong>FlowCore</strong><small>BPM</small></span></a>
            <div class="fc-process-name"><span class="fc-eyebrow">ÁREA MASTER</span><h1>Grupos</h1></div>
            <div class="fc-header-actions">
                <a class="fc-button fc-button-secondary" href="{{ route('master.processes.index') }}">Processos</a>
                <a class="fc-button fc-button-secondary" href="{{ route('master.users.index') }}">Usuários</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="fc-button fc-button-secondary" type="submit">Sair</button></form>
            </div>
        </header>
        <main class="fc-list-content">
            <section class="fc-list-heading">
                <div><span class="fc-eyebrow">EQUIPES E DEPARTAMENTOS</span><h2>Grupos de trabalho</h2><p>Organize usuários em equipes como Fiscal, Contabilidade e Compras para configurar o encaminhamento de tarefas.</p></div>
            </section>
            @if (session('status')) <div class="fc-flash-success" role="status">{{ session('status') }}</div> @endif
            @if ($errors->any()) <div class="fc-login-error" role="alert">{{ $errors->first() }}</div> @endif
            <section class="fc-create-card">
                <h3>Novo grupo</h3>
                <form method="POST" action="{{ route('master.groups.store') }}" class="fc-group-create-form">
                    @csrf
                    <div class="fc-form-field"><label for="name">Nome do grupo</label><input id="name" name="name" value="{{ old('name') }}" maxlength="120" placeholder="Ex.: Fiscal" required>@error('name')<small class="fc-form-error">{{ $message }}</small>@enderror</div>
                    <div class="fc-form-field"><label for="description">Descrição <span>(opcional)</span></label><input id="description" name="description" value="{{ old('description') }}" maxlength="500" placeholder="Responsáveis pelos dados fiscais do produto">@error('description')<small class="fc-form-error">{{ $message }}</small>@enderror</div>
                    <button class="fc-button fc-button-primary" type="submit">Criar grupo</button>
                </form>
            </section>
            <section class="fc-process-list">
                <div class="fc-list-title"><h3>Grupos cadastrados</h3><span>{{ $groups->count() }}</span></div>
                @forelse ($groups as $group)
                    <details class="fc-group-card">
                        <summary class="fc-group-summary">
                            <span class="fc-group-summary-copy">
                                <strong>{{ $group->name }}</strong>
                                <small>{{ $group->description ?: 'Sem descrição' }}</small>
                            </span>
                            <span class="fc-group-summary-meta">{{ $group->users->count() }} {{ $group->users->count() === 1 ? 'membro' : 'membros' }}</span>
                            <span class="fc-group-summary-toggle" aria-hidden="true"></span>
                        </summary>
                        <form method="POST" action="{{ route('master.groups.update', $group) }}" class="fc-group-form">
                            @csrf @method('PUT')
                            <div class="fc-group-form-heading">
                                <div class="fc-group-fields">
                                    <div class="fc-form-field"><label for="group-name-{{ $group->id }}">Nome</label><input id="group-name-{{ $group->id }}" name="name" value="{{ $group->name }}" maxlength="120" required></div>
                                    <div class="fc-form-field"><label for="group-description-{{ $group->id }}">Descrição</label><input id="group-description-{{ $group->id }}" name="description" value="{{ $group->description }}" maxlength="500"></div>
                                </div>
                                <div class="fc-group-actions"><button class="fc-button fc-button-secondary" type="submit">Salvar grupo</button></div>
                            </div>
                            <fieldset class="fc-group-members">
                                <legend>Membros da equipe</legend>
                                <details class="fc-member-picker" data-member-picker>
                                    <summary><span>Selecionar usuários</span><strong data-member-count>{{ $group->users->count() }} selecionados</strong><span class="fc-group-summary-toggle" aria-hidden="true"></span></summary>
                                    <div class="fc-member-picker-panel">
                                        <label class="fc-member-search">
                                            <span>Buscar por nome, e-mail ou perfil</span>
                                            <input type="search" data-member-search placeholder="Digite para filtrar usuários" autocomplete="off">
                                        </label>
                                        <div class="fc-group-member-list" data-member-list>
                                            @foreach ($users as $user)
                                                <label class="fc-group-member" data-member-option>
                                                    <input type="checkbox" name="users[]" value="{{ $user->id }}" @checked($group->users->contains('id', $user->id))>
                                                    <span><strong>{{ $user->name }}{{ $user->is_active ? '' : ' · desativado' }}</strong><small>{{ $user->role_label }} · {{ $user->email }}</small></span>
                                                </label>
                                            @endforeach
                                            <p class="fc-member-no-results" data-member-empty hidden>Nenhum usuário corresponde à busca.</p>
                                        </div>
                                    </div>
                                </details>
                            </fieldset>
                        </form>
                        <form method="POST" action="{{ route('master.groups.destroy', $group) }}" onsubmit="return confirm('Remover este grupo?')">
                            @csrf @method('DELETE')
                            <button class="fc-button fc-button-danger" type="submit">Remover grupo</button>
                        </form>
                    </details>
                @empty
                    <div class="fc-empty-state"><strong>Nenhum grupo ainda</strong><span>Crie um grupo para começar a organizar as equipes que atuarão nos processos.</span></div>
                @endforelse
            </section>
        </main>
    </div>
</body>
</html>
