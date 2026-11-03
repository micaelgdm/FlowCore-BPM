<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('shared.favicon')
    <title>Meu perfil · FlowCore BPM</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flowcore-page">
    <div class="fc-list-page">
        <header class="fc-header">
            <a class="fc-brand" href="{{ $user->role === 'master' ? route('master.processes.index') : route('portal.processes.index') }}">@include('shared.brand-logo')</a>
            <div class="fc-process-name"><span class="fc-eyebrow">CONTA DO USUÁRIO</span><h1>Meu perfil</h1></div>
            <div class="fc-header-actions">
                @if ($user->role === 'master')
                    @include('shared.master-navigation')
                @else
                    <a class="fc-button fc-button-secondary" href="{{ route('portal.processes.index') }}">@include('shared.nav-icon', ['icon' => 'back'])Voltar</a>
                    @include('shared.theme-toggle')
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="fc-button fc-button-secondary" type="submit">@include('shared.nav-icon', ['icon' => 'logout'])Sair</button></form>
                @endif
            </div>
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
            <section class="fc-process-list fc-profile-card">
                <div class="fc-list-title"><h3>Delegação de tarefas</h3></div>
                <div class="fc-profile-delegation-content">
                    <p class="fc-profile-note">Programe uma pessoa substituta para receber as tarefas individuais destinadas a você durante suas férias ou ausência. Tarefas do grupo continuam seguindo o fluxo normal da equipe. Tarefas já encaminhadas à substituta permanecem com ela até a conclusão, mesmo após o fim da delegação.</p>
                    <p class="fc-profile-note">Tarefas de grupo que já foram assumidas por você também serão encaminhadas; tarefas ainda não assumidas permanecem disponíveis para a equipe.</p>
                    @if ($delegateOptions->isNotEmpty())
                        <form method="POST" action="{{ route('profile.task-delegations.store') }}" class="fc-delegation-form">
                            @csrf
                            <label class="fc-form-field"><span>Pessoa substituta</span><select name="delegate_user_id" required><option value="">Selecione uma pessoa</option>@foreach ($delegateOptions as $option)<option value="{{ $option->id }}" @selected((string) old('delegate_user_id') === (string) $option->id)>{{ $option->name }} · {{ $option->email }}</option>@endforeach</select>@error('delegate_user_id')<small class="fc-form-error">{{ $message }}</small>@enderror</label>
                            <label class="fc-form-field"><span>Início da delegação</span><input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" required>@error('starts_at')<small class="fc-form-error">{{ $message }}</small>@enderror</label>
                            <label class="fc-form-field"><span>Fim da delegação</span><input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" required>@error('ends_at')<small class="fc-form-error">{{ $message }}</small>@enderror</label>
                            <div class="fc-delegation-actions"><button class="fc-button fc-button-primary" type="submit">Programar substituição</button></div>
                        </form>
                    @else
                        <p class="fc-delegation-empty">Não há outras contas ativas disponíveis para atuar como substitutas.</p>
                    @endif
                    @if ($taskDelegations->isNotEmpty())
                        <div class="fc-delegation-list">
                            @foreach ($taskDelegations as $delegation)
                                @php($delegationStatus = $delegation->cancelled_at ? 'Cancelada' : ($delegation->starts_at->isFuture() ? 'Programada' : ($delegation->ends_at->isPast() ? 'Encerrada' : 'Ativa')))
                                <article class="fc-delegation-item">
                                    <div><strong>{{ $delegation->delegate->name }}</strong><small>{{ $delegation->delegate->email }}</small><small>{{ $delegation->starts_at->setTimezone(config('app.display_timezone'))->format('d/m/Y H:i') }} – {{ $delegation->ends_at->setTimezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</small></div>
                                    <span class="fc-status-pill {{ $delegationStatus === 'Ativa' ? 'is-published' : '' }}">{{ $delegationStatus }}</span>
                                    @if (! $delegation->cancelled_at && $delegation->ends_at->isFuture())
                                        <form method="POST" action="{{ route('profile.task-delegations.cancel', $delegation) }}" data-confirm="Encerrar esta delegação? Tarefas já encaminhadas continuam com a substituta até a conclusão.">
                                            @csrf @method('DELETE')
                                            <button class="fc-button fc-button-secondary" type="submit">Encerrar</button>
                                        </form>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        </main>
    </div>
</body>
</html>
