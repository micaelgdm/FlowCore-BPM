<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('shared.favicon')
    @include('shared.favicon')
    <title>Nova solicitação · {{ $process->name }} · FlowCore BPM</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flowcore-page">
    <div class="fc-list-page">
        <header class="fc-header">
            <a class="fc-brand" href="{{ route('portal.processes.index') }}">@include('shared.brand-logo')</a>
            <div class="fc-process-name"><span class="fc-eyebrow">NOVA SOLICITAÇÃO</span><h1>{{ $process->name }}</h1></div>
            <div class="fc-header-actions"><a class="fc-button fc-button-secondary" href="{{ route('portal.tasks.index') }}">@include('shared.nav-icon', ['icon' => 'tasks'])Minhas tarefas</a><a class="fc-button fc-button-secondary" href="{{ route('portal.requests.index') }}">@include('shared.nav-icon', ['icon' => 'requests'])Minhas solicitações</a><a class="fc-button fc-button-secondary" href="{{ route('portal.processes.index') }}">@include('shared.nav-icon', ['icon' => 'processes'])Meus processos</a>@include('shared.profile-link')<form method="POST" action="{{ route('logout') }}">@csrf<button class="fc-button fc-button-secondary" type="submit">@include('shared.nav-icon', ['icon' => 'logout'])Sair</button></form></div>
        </header>
        <main class="fc-list-content">
            <section class="fc-list-heading"><div><span class="fc-eyebrow">PREENCHIMENTO</span><h2>{{ $process->name }}</h2><p>{{ $process->description ?: 'Preencha os dados para iniciar esta solicitação.' }}</p></div></section>
            @error('flowable') <div class="fc-login-error" role="alert">{{ $message }}</div> @enderror
            <form method="POST" action="{{ route('portal.processes.start.submit', $process) }}" class="fc-start-form" enctype="multipart/form-data" data-conditional-form>
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
                @foreach ($process->form_schema as $field)
                    <div class="fc-start-field" data-conditional-field data-visibility-condition="{{ json_encode($field['visible_when'] ?? []) }}">
                        <label for="field-{{ $field['key'] }}">{{ $field['label'] }}{{ ($field['required'] ?? false) ? ' *' : '' }}</label>
                        @switch($field['type'])
                            @case('textarea')
                                <textarea id="field-{{ $field['key'] }}" name="{{ $field['key'] }}" rows="4" data-field-value="{{ $field['key'] }}" @required($field['required'] ?? false)>{{ old($field['key']) }}</textarea>
                                @break
                            @case('select')
                                <select id="field-{{ $field['key'] }}" name="{{ $field['key'] }}" data-field-value="{{ $field['key'] }}" @required($field['required'] ?? false)>
                                    @unless ($field['required'] ?? false) <option value="">Selecione</option> @endunless
                                    @foreach ($field['options'] ?? [] as $option)
                                        <option value="{{ $option }}" @selected(old($field['key']) === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                                @break
                            @case('multiselect')
                                @php($selectedValues = (array) old($field['key'], []))
                                <select id="field-{{ $field['key'] }}" name="{{ $field['key'] }}[]" multiple size="{{ min(6, count($field['options'] ?? [])) }}" data-field-value="{{ $field['key'] }}" @required($field['required'] ?? false)>
                                    @foreach ($field['options'] ?? [] as $option)<option value="{{ $option }}" @selected(in_array($option, $selectedValues, true))>{{ $option }}</option>@endforeach
                                </select>
                                <small>Use Ctrl (Windows) ou Command (Mac) para escolher mais de uma opção.</small>
                                @break
                            @case('user')
                                <select id="field-{{ $field['key'] }}" name="{{ $field['key'] }}" data-field-value="{{ $field['key'] }}" @required($field['required'] ?? false)><option value="">Selecione uma pessoa</option>@foreach ($formUsers as $formUser)<option value="{{ $formUser->id }}" @selected((string) old($field['key']) === (string) $formUser->id)>{{ $formUser->name }} · {{ $formUser->email }}</option>@endforeach</select>
                                @break
                            @case('group')
                                <select id="field-{{ $field['key'] }}" name="{{ $field['key'] }}" data-field-value="{{ $field['key'] }}" @required($field['required'] ?? false)><option value="">Selecione um grupo</option>@foreach ($formGroups as $formGroup)<option value="{{ $formGroup->id }}" @selected((string) old($field['key']) === (string) $formGroup->id)>{{ $formGroup->name }}</option>@endforeach</select>
                                @break
                            @case('calculated')
                                <input id="field-{{ $field['key'] }}" name="{{ $field['key'] }}" type="number" step="any" readonly data-field-value="{{ $field['key'] }}" data-calculated-field data-calculation-left="{{ $field['calculation']['left'] ?? '' }}" data-calculation-operation="{{ $field['calculation']['operation'] ?? '' }}" data-calculation-right="{{ $field['calculation']['right'] ?? '' }}" value="{{ old($field['key']) }}" @required($field['required'] ?? false)>
                                @break
                            @case('boolean')
                                <select id="field-{{ $field['key'] }}" name="{{ $field['key'] }}" data-field-value="{{ $field['key'] }}" @required($field['required'] ?? false)>
                                    @unless ($field['required'] ?? false) <option value="">Selecione</option> @endunless
                                    <option value="1" @selected(old($field['key']) === '1')>Sim</option><option value="0" @selected(old($field['key']) === '0')>Não</option>
                                </select>
                                @break
                            @case('file')
                                <input id="field-{{ $field['key'] }}" name="{{ $field['key'] }}" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.webp" data-field-value="{{ $field['key'] }}" @required($field['required'] ?? false)>
                                <small>PDF, Office, CSV, texto ou imagem · até 10 MB</small>
                                @break
                            @default
                                <input id="field-{{ $field['key'] }}" name="{{ $field['key'] }}" type="{{ $field['type'] === 'number' ? 'number' : ($field['type'] === 'date' ? 'date' : 'text') }}" data-field-value="{{ $field['key'] }}" @if ($field['type'] === 'number') step="any" @endif value="{{ old($field['key']) }}" @required($field['required'] ?? false)>
                        @endswitch
                        @error($field['key']) <small class="fc-form-error">{{ $message }}</small> @enderror
                    </div>
                @endforeach
                <div class="fc-form-builder-actions"><a class="fc-button fc-button-secondary" href="{{ route('portal.processes.index') }}">Cancelar</a><button class="fc-button fc-button-primary" type="submit">Enviar solicitação</button></div>
            </form>
        </main>
    </div>
</body>
</html>
