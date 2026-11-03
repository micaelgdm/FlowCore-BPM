<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('shared.favicon')
    @include('shared.favicon')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Entrar · FlowCore BPM</title>
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
                    <h2>Seu trabalho, conectado do início ao fim.</h2>
                    <p>Acompanhe solicitações, tarefas e aprovações em um só lugar.</p>
                </div>
                <div class="fc-auth-brand-footer"><span class="fc-auth-brand-indicator"></span>Qualidade e confiança em cada etapa</div>
            </section>
            <section class="fc-login-card" aria-labelledby="login-title">
                <span class="fc-auth-eyebrow fc-auth-form-eyebrow">BEM-VINDO DE VOLTA</span>
                <h1 id="login-title">Acesse sua conta</h1>
                <p class="fc-login-intro">Entre com seu e-mail corporativo e senha.</p>
                @if (session('status'))
                    <div class="fc-login-success" role="status">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="fc-login-error" role="alert">{{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('login.store') }}" class="fc-login-form">
                    @csrf
                    <label for="email">E-mail</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" placeholder="nome@empresa.com.br" required autofocus>
                    <div class="fc-login-password-heading"><label for="password">Senha</label><a href="{{ route('password.request') }}">Esqueci minha senha</a></div>
                    <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Digite sua senha" required>
                    <label class="fc-remember"><input type="checkbox" name="remember" value="1"> Manter conectado</label>
                    <button class="fc-button fc-button-primary" type="submit">Entrar na plataforma <svg class="fc-login-submit-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></button>
                </form>
                <p class="fc-login-footnote">Seu acesso a processos e tarefas depende das permissões associadas à sua conta.</p>
            </section>
        </div>
    </main>
</body>
</html>
