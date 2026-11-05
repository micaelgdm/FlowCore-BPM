@include('shared.password-expiry-warning')
@php($navPendingTaskCount = $pendingTaskCount ?? $portalPendingTaskCount ?? (isset($tasks) && ! ($unavailable ?? false) ? $tasks->count() : null))
<a class="fc-button fc-button-secondary fc-task-nav-link" href="{{ route('portal.tasks.index') }}" @if (is_numeric($navPendingTaskCount) && $navPendingTaskCount > 0) aria-label="Minhas tarefas, {{ $navPendingTaskCount }} pendentes" @endif>
    @include('shared.nav-icon', ['icon' => 'tasks'])Minhas tarefas
    @if (is_numeric($navPendingTaskCount) && $navPendingTaskCount > 0)<span class="fc-task-nav-count" aria-hidden="true">{{ $navPendingTaskCount > 99 ? '99+' : $navPendingTaskCount }}</span>@endif
</a>
<a class="fc-button fc-button-secondary" href="{{ route('portal.requests.index') }}">@include('shared.nav-icon', ['icon' => 'requests'])Minhas solicitações</a>
<a class="fc-button fc-button-secondary" href="{{ route('portal.processes.index') }}">@include('shared.nav-icon', ['icon' => 'processes'])Meus processos</a>
@include('shared.profile-link')
<form method="POST" action="{{ route('logout') }}">
    @csrf
    <button class="fc-button fc-button-secondary" type="submit">@include('shared.nav-icon', ['icon' => 'logout'])Sair</button>
</form>
