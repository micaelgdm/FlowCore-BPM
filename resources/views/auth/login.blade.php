<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Entrar · FlowCore BPM</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flowcore-page">
    <main class="fc-login-page">
        <section class="fc-login-card">
            <div class="fc-brand fc-login-brand">
                <span class="fc-brand-mark">F</span>
                <span><strong>FlowCore</strong><small>BPM</small></span>
            </div>
            <span class="fc-eyebrow">ACESSO À PLATAFORMA</span>
            <h1>Acesse sua conta</h1>
            <p class="fc-login-intro">Entre com a conta fornecida pelo administrador da plataforma.</p>
            @if ($errors->any())
                <div class="fc-login-error" role="alert">{{ $errors->first() }}</div>
            @endif
            <form method="POST" action="{{ route('login.store') }}" class="fc-login-form">
                @csrf
                <label for="email">E-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
                <label for="password">Senha</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
                <label class="fc-remember"><input type="checkbox" name="remember" value="1"> Manter conectado</label>
                <button class="fc-button fc-button-primary" type="submit">Entrar</button>
            </form>
            <p class="fc-login-footnote">Seu acesso aos processos e às tarefas depende das permissões e dos grupos associados à sua conta.</p>
        </section>
    </main>
</body>
</html>
