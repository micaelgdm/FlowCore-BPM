<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('shared.favicon')
    <title>Atualize sua senha · FlowCore BPM</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flowcore-page">
    <main class="fc-login-page fc-auth-page">
        @include('shared.theme-toggle')
        <div class="fc-auth-shell">
            <section class="fc-auth-brand-panel" aria-label="Grupo Doce Mel">
                <div class="fc-auth-brand-logo">@include('shared.brand-logo')</div>
                <div class="fc-auth-brand-copy">
                    <span class="fc-auth-eyebrow">FLOWCORE · GESTÃO DE PROCESSOS</span>
                    <h2>Proteja o acesso à sua conta.</h2>
                    <p>Antes de continuar, defina uma senha pessoal que só você conheça.</p>
                </div>
                <div class="fc-auth-brand-footer"><span class="fc-auth-brand-indicator"></span>As senhas precisam ser atualizadas a cada 90 dias</div>
            </section>
            <section class="fc-login-card" aria-labelledby="password-required-title">
                <span class="fc-auth-eyebrow fc-auth-form-eyebrow">AÇÃO NECESSÁRIA</span>
                <h1 id="password-required-title">Atualize sua senha</h1>
                <p class="fc-login-intro">Sua senha inicial ou atual expirou. Para continuar usando o FlowCore, confirme a senha atual e escolha uma nova.</p>
                @if ($errors->any())
                    <div class="fc-login-error" role="alert">{{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('password.required.update') }}" class="fc-login-form">
                    @csrf
                    @method('PUT')
                    <label for="current_password">Senha atual</label>
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password" required autofocus>
                    <label for="password">Nova senha <span>(mínimo de 8 caracteres)</span></label>
                    <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required>
                    <label for="password_confirmation">Confirme a nova senha</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
                    <small class="fc-password-policy-hint">Inclua ao menos uma letra maiúscula, uma minúscula, um número e um caractere especial. A nova senha deve ser diferente da atual.</small>
                    <button class="fc-button fc-button-primary" type="submit">Salvar e continuar <span aria-hidden="true">→</span></button>
                </form>
                <form method="POST" action="{{ route('logout') }}" class="fc-required-password-logout">
                    @csrf
                    <button class="fc-auth-back-link" type="submit">Sair da conta</button>
                </form>
            </section>
        </div>
    </main>
</body>
</html>
