<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('shared.favicon')
    @include('shared.favicon')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Recuperar senha · FlowCore BPM</title>
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
                    <h2>Retome seu acesso com segurança.</h2>
                    <p>Informe o e-mail da sua conta e enviaremos as instruções para criar uma nova senha.</p>
                </div>
                <div class="fc-auth-brand-footer"><span class="fc-auth-brand-indicator"></span>O link de recuperação tem prazo de validade</div>
            </section>
            <section class="fc-login-card" aria-labelledby="recovery-title">
                <span class="fc-auth-eyebrow fc-auth-form-eyebrow">RECUPERAÇÃO DE ACESSO</span>
                <h1 id="recovery-title">Esqueceu sua senha?</h1>
                <p class="fc-login-intro">Digite o e-mail cadastrado. Se localizarmos a conta, enviaremos um link para redefinir a senha.</p>
                @if (session('status'))
                    <div class="fc-login-success" role="status">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="fc-login-error" role="alert">{{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('password.email') }}" class="fc-login-form">
                    @csrf
                    <label for="email">E-mail</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" placeholder="nome@empresa.com.br" required autofocus>
                    <button class="fc-button fc-button-primary" type="submit">Enviar link de recuperação <span aria-hidden="true">→</span></button>
                </form>
                <a class="fc-auth-back-link" href="{{ route('login') }}"><span aria-hidden="true">←</span> Voltar para entrar</a>
            </section>
        </div>
    </main>
</body>
</html>
