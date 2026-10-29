<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Meu perfil · FlowCore BPM</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flowcore-page">
    <div class="fc-list-page">
        <header class="fc-header">
            <a class="fc-brand" href="{{ $user->role === 'master' ? route('master.processes.index') : route('portal.processes.index') }}"><span class="fc-brand-mark">F</span><span><strong>FlowCore</strong><small>BPM</small></span></a>
            <div class="fc-process-name"><span class="fc-eyebrow">CONTA DO USUÁRIO</span><h1>Meu perfil</h1></div>
            <div class="fc-header-actions"><a class="fc-button fc-button-secondary" href="{{ $user->role === 'master' ? route('master.processes.index') : route('portal.processes.index') }}">Voltar</a><form method="POST" action="{{ route('logout') }}">@csrf<button class="fc-button fc-button-secondary" type="submit">Sair</button></form></div>
        </header>
        <main class="fc-list-content fc-profile-content">
            <section class="fc-list-heading"><div><span class="fc-eyebrow">DADOS DA CONTA</span><h2>Seu perfil</h2><p>Consulte seus dados de acesso e altere sua senha quando precisar.</p></div></section>
            @if (session('status')) <div class="fc-flash-success" role="status">{{ session('status') }}</div> @endif
            <section class="fc-process-list fc-profile-card">
                <div class="fc-list-title"><h3>Identificação</h3></div>
                <div class="fc-profile-identity">
                    <label class="fc-form-field"><span>Nome de usuário</span><input value="{{ $user->name }}" readonly aria-readonly="true"></label>
                    <label class="fc-form-field"><span>E-mail</span><input type="email" value="{{ $user->email }}" readonly aria-readonly="true"></label>
                    <label class="fc-form-field"><span>Perfil de acesso</span><input value="{{ $user->role_label }}" readonly aria-readonly="true"></label>
                </div>
                <p class="fc-profile-note">Nome de usuário e e-mail são administrados pelo Master e não podem ser alterados nesta página.</p>
            </section>
            <section class="fc-process-list fc-profile-card">
                <div class="fc-list-title"><h3>Alterar senha</h3></div>
                <form method="POST" action="{{ route('profile.password.update') }}" class="fc-profile-password-form">
                    @csrf @method('PUT')
                    <label class="fc-form-field"><span>Senha atual</span><input type="password" name="current_password" autocomplete="current-password" required>@error('current_password')<small class="fc-form-error">{{ $message }}</small>@enderror</label>
                    <label class="fc-form-field"><span>Nova senha <small>(mínimo de 12 caracteres)</small></span><input type="password" name="password" autocomplete="new-password" minlength="12" required>@error('password')<small class="fc-form-error">{{ $message }}</small>@enderror</label>
                    <label class="fc-form-field"><span>Confirme a nova senha</span><input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required></label>
                    <div class="fc-form-builder-actions"><button class="fc-button fc-button-primary" type="submit">Salvar nova senha</button></div>
                </form>
            </section>
        </main>
    </div>
</body>
</html>
