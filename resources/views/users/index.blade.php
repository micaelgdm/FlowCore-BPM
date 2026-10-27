<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Usuários · FlowCore BPM</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flowcore-page">
    <div class="fc-list-page">
        <header class="fc-header">
            <a class="fc-brand" href="{{ route('master.processes.index') }}"><span class="fc-brand-mark">F</span><span><strong>FlowCore</strong><small>BPM</small></span></a>
            <div class="fc-process-name"><span class="fc-eyebrow">ÁREA MASTER</span><h1>Usuários</h1></div>
            <div class="fc-header-actions">
                <a class="fc-button fc-button-secondary" href="{{ route('master.processes.index') }}">Processos</a>
                <a class="fc-button fc-button-secondary" href="{{ route('master.groups.index') }}">Grupos</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="fc-button fc-button-secondary" type="submit">Sair</button></form>
            </div>
        </header>
        <main class="fc-list-content">
            <section class="fc-list-heading">
                <div><span class="fc-eyebrow">CONTAS E PERFIS</span><h2>Cadastro de usuários</h2><p>Crie contas e defina o perfil inicial. O acesso a processos é concedido separadamente.</p></div>
            </section>
            @if (session('status')) <div class="fc-flash-success" role="status">{{ session('status') }}</div> @endif
            @if ($errors->has('user')) <div class="fc-login-error" role="alert">{{ $errors->first('user') }}</div> @endif
            <section class="fc-create-card">
                <h3>Novo usuário</h3>
                <form method="POST" action="{{ route('master.users.store') }}" class="fc-user-create-form">
                    @csrf
                    <div class="fc-form-field"><label for="name">Nome</label><input id="name" name="name" value="{{ old('name') }}" maxlength="255" required>@error('name')<small class="fc-form-error">{{ $message }}</small>@enderror</div>
                    <div class="fc-form-field"><label for="email">E-mail</label><input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="255" required>@error('email')<small class="fc-form-error">{{ $message }}</small>@enderror</div>
                    <div class="fc-form-field"><label for="role">Perfil</label><select id="role" name="role" required>@foreach ($roles as $role)<option value="{{ $role->value }}" @selected(old('role', 'user') === $role->value)>{{ $role->label() }}</option>@endforeach</select>@error('role')<small class="fc-form-error">{{ $message }}</small>@enderror</div>
                    <div class="fc-form-field"><label for="password">Senha inicial <span>(mínimo de 12 caracteres)</span></label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required>@error('password')<small class="fc-form-error">{{ $message }}</small>@enderror</div>
                    <div class="fc-form-field"><label for="password_confirmation">Confirme a senha inicial</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required></div>
                    <button class="fc-button fc-button-primary" type="submit">Criar conta</button>
                </form>
            </section>
            <section class="fc-process-list">
                <div class="fc-list-title"><h3>Contas cadastradas</h3><span>{{ $users->count() }}</span></div>
                @foreach ($users as $user)
                    <article class="fc-user-row">
                        <div class="fc-user-identity"><strong>{{ $user->name }}</strong><small>{{ $user->email }}{{ $user->is(auth()->user()) ? ' · Você' : '' }}</small></div>
                        <span class="fc-status-pill {{ $user->is_active ? 'is-published' : '' }}">{{ $user->is_active ? 'Ativo' : 'Desativado' }}</span>
                        @if ($user->is(auth()->user()))
                            <span class="fc-role-label">{{ $user->role_label }}</span>
                        @else
                            <form method="POST" action="{{ route('master.users.update', $user) }}" class="fc-user-update-form">
                                @csrf @method('PUT')
                                <select name="role" aria-label="Perfil de {{ $user->name }}">@foreach ($roles as $role)<option value="{{ $role->value }}" @selected($user->role === $role->value)>{{ $role->label() }}</option>@endforeach</select>
                                <select name="is_active" aria-label="Estado de {{ $user->name }}"><option value="1" @selected($user->is_active)>Ativo</option><option value="0" @selected(! $user->is_active)>Desativado</option></select>
                                <button class="fc-button fc-button-secondary" type="submit">Salvar</button>
                            </form>
                        @endif
                    </article>
                @endforeach
            </section>
        </main>
    </div>
</body>
</html>
