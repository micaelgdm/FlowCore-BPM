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
                @include('shared.profile-link')
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="fc-button fc-button-secondary" type="submit">Sair</button></form>
            </div>
        </header>
        <main class="fc-list-content">
            <section class="fc-list-heading">
                <div><span class="fc-eyebrow">CONTAS E PERFIS</span><h2>Cadastro de usuários</h2><p>Crie contas e defina o perfil inicial. O acesso a processos é concedido separadamente.</p></div>
            </section>
            @if (session('status')) <div class="fc-flash-success" role="status">{{ session('status') }}</div> @endif
            @if ($errors->has('user')) <div class="fc-login-error" role="alert">{{ $errors->first('user') }}</div> @endif
            @if ($errors->any() && ! $errors->has('user')) <div class="fc-login-error" role="alert">{{ $errors->first() }}</div> @endif
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
                    <details class="fc-user-card" {{ $errors->any() && old('_user_id') == $user->id ? 'open' : '' }}>
                        <summary class="fc-user-summary">
                            <span class="fc-user-identity"><strong>{{ $user->name }}{{ $user->is(auth()->user()) ? ' · Você' : '' }}</strong><small>{{ $user->email }} · {{ $user->role_label }}</small></span>
                            <span class="fc-user-summary-actions"><span class="fc-status-pill {{ $user->is_active ? 'is-published' : '' }}">{{ $user->is_active ? 'Ativo' : 'Desativado' }}</span>@unless ($user->is(auth()->user()))<span class="fc-user-edit-label">Editar cadastro</span>@endunless</span>
                        </summary>
                        @if ($user->is(auth()->user()))
                            <div class="fc-user-self-note">Para alterar sua senha, use a página <a href="{{ route('profile.edit') }}">Meu perfil</a>. A edição do próprio cadastro por esta tela fica bloqueada para proteger a conta Master.</div>
                        @else
                            <form method="POST" action="{{ route('master.users.update', $user) }}" class="fc-user-edit-form">
                                @csrf @method('PUT')
                                <input type="hidden" name="_user_id" value="{{ $user->id }}">
                                <div class="fc-form-field"><label for="user-name-{{ $user->id }}">Nome</label><input id="user-name-{{ $user->id }}" name="name" value="{{ old('_user_id') == $user->id ? old('name', $user->name) : $user->name }}" maxlength="255" required></div>
                                <div class="fc-form-field"><label for="user-email-{{ $user->id }}">E-mail</label><input id="user-email-{{ $user->id }}" name="email" type="email" value="{{ old('_user_id') == $user->id ? old('email', $user->email) : $user->email }}" maxlength="255" required></div>
                                <div class="fc-form-field"><label for="user-role-{{ $user->id }}">Perfil</label><select id="user-role-{{ $user->id }}" name="role" required>@foreach ($roles as $role)<option value="{{ $role->value }}" @selected((old('_user_id') == $user->id ? old('role', $user->role) : $user->role) === $role->value)>{{ $role->label() }}</option>@endforeach</select></div>
                                <div class="fc-form-field"><label for="user-active-{{ $user->id }}">Acesso</label><select id="user-active-{{ $user->id }}" name="is_active" required><option value="1" @selected((string) (old('_user_id') == $user->id ? old('is_active', (int) $user->is_active) : (int) $user->is_active) === '1')>Ativo</option><option value="0" @selected((string) (old('_user_id') == $user->id ? old('is_active', (int) $user->is_active) : (int) $user->is_active) === '0')>Desativado</option></select></div>
                                <div class="fc-form-field"><label for="user-password-{{ $user->id }}">Nova senha <span>opcional; mínimo de 12 caracteres</span></label><input id="user-password-{{ $user->id }}" name="password" type="password" autocomplete="new-password" minlength="12"></div>
                                <div class="fc-form-field"><label for="user-password-confirmation-{{ $user->id }}">Confirme a nova senha</label><input id="user-password-confirmation-{{ $user->id }}" name="password_confirmation" type="password" autocomplete="new-password" minlength="12"></div>
                                <div class="fc-user-edit-actions"><button class="fc-button fc-button-primary" type="submit">Salvar alterações</button></div>
                            </form>
                        @endif
                    </details>
                @endforeach
            </section>
        </main>
    </div>
</body>
</html>
