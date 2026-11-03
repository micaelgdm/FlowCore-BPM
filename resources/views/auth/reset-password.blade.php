<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('shared.favicon')
    @include('shared.favicon')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Redefinir senha · FlowCore BPM</title>
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
                    <h2>Uma nova senha para continuar.</h2>
                    <p>Crie uma senha segura para voltar a acompanhar seus processos e tarefas.</p>
                </div>
                <div class="fc-auth-brand-footer"><span class="fc-auth-brand-indicator"></span>Use pelo menos 12 caracteres</div>
            </section>
            <section class="fc-login-card" aria-labelledby="reset-title">
                <span class="fc-auth-eyebrow fc-auth-form-eyebrow">NOVA SENHA</span>
                <h1 id="reset-title">Redefina sua senha</h1>
                <p class="fc-login-intro">Escolha uma senha nova para sua conta.</p>
                @if ($errors->any())
                    <div class="fc-login-error" role="alert">{{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('password.update') }}" class="fc-login-form">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <label for="email">E-mail</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required autofocus>
                    <label for="password">Nova senha <span>(mínimo de 12 caracteres)</span></label>
                    <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required>
                    <label for="password_confirmation">Confirme a nova senha</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required>
                    <button class="fc-button fc-button-primary" type="submit">Salvar nova senha <span aria-hidden="true">→</span></button>
                </form>
                <a class="fc-auth-back-link" href="{{ route('login') }}"><span aria-hidden="true">←</span> Voltar para entrar</a>
            </section>
        </div>
    </main>
</body>
</html>
