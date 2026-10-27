<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Formulário · {{ $process->name }} · FlowCore BPM</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flowcore-page">
    <div class="fc-list-page">
        <header class="fc-header">
            <a class="fc-brand" href="{{ route('master.processes.index') }}"><span class="fc-brand-mark">F</span><span><strong>FlowCore</strong><small>BPM</small></span></a>
            <div class="fc-process-name"><span class="fc-eyebrow">RASCUNHO · V{{ $process->version }}</span><h1>{{ $process->name }}</h1></div>
            <div class="fc-header-actions">
                <a class="fc-button fc-button-secondary" href="{{ route('master.processes.editor', $process) }}">Editor</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="fc-button fc-button-secondary" type="submit">Sair</button></form>
            </div>
        </header>
        <main class="fc-list-content">
            <section class="fc-list-heading"><div><span class="fc-eyebrow">CONFIGURAÇÃO</span><h2>Formulário da solicitação</h2><p>Os campos abaixo serão exibidos a quem iniciar este processo. A definição fica imutável ao publicar a versão.</p></div></section>
            @if (session('status')) <div class="fc-flash-success" role="status">{{ session('status') }}</div> @endif
            @if ($errors->any()) <div class="fc-login-error" role="alert">Revise os campos destacados e tente salvar novamente.</div> @endif
            <form method="POST" action="{{ route('master.processes.form.update', $process) }}" class="fc-form-builder">
                @csrf @method('PUT')
                <div id="form-fields" data-form-fields>
                    @foreach (old('fields', $fields ?: [[]]) as $index => $field)
                        @include('processes.partials.form-field', ['field' => $field, 'index' => $index])
                    @endforeach
                </div>
                <div class="fc-form-builder-actions">
                    <button type="button" id="add-form-field" data-add-form-field class="fc-button fc-button-secondary">Adicionar campo</button>
                    <button type="submit" class="fc-button fc-button-primary">Salvar formulário</button>
                </div>
            </form>
                <template id="form-field-template" data-form-field-template>
                @include('processes.partials.form-field', ['field' => [], 'index' => '__INDEX__'])
            </template>
        </main>
    </div>
</body>
</html>
