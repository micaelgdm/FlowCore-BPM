<a class="fc-button fc-button-secondary" href="{{ route('master.processes.index') }}">@include('shared.nav-icon', ['icon' => 'processes'])Processos</a>
<details class="fc-nav-dropdown">
    <summary class="fc-button fc-button-secondary">@include('shared.nav-icon', ['icon' => 'users'])Administração<span class="fc-nav-chevron" aria-hidden="true"></span></summary>
    <div class="fc-nav-dropdown-menu">
        <a href="{{ route('master.users.index') }}">@include('shared.nav-icon', ['icon' => 'users'])Usuários</a>
        <a href="{{ route('master.groups.index') }}">@include('shared.nav-icon', ['icon' => 'groups'])Grupos</a>
    </div>
</details>
<details class="fc-nav-dropdown">
    <summary class="fc-button fc-button-secondary">@include('shared.nav-icon', ['icon' => 'notifications'])Sistema<span class="fc-nav-chevron" aria-hidden="true"></span></summary>
    <div class="fc-nav-dropdown-menu">
        <a href="{{ route('master.notifications.index') }}">@include('shared.nav-icon', ['icon' => 'notifications'])Notificações</a>
        <a href="{{ route('master.audit.index') }}">@include('shared.nav-icon', ['icon' => 'audit'])Auditoria</a>
    </div>
</details>
@include('shared.profile-link')
<form method="POST" action="{{ route('logout') }}">
    @csrf
    <button class="fc-button fc-button-secondary" type="submit">@include('shared.nav-icon', ['icon' => 'logout'])Sair</button>
</form>
