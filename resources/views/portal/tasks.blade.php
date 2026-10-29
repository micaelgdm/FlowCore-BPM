<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Minhas tarefas · FlowCore BPM</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flowcore-page">
    <div class="fc-list-page">
        <header class="fc-header">
            <a class="fc-brand" href="{{ route('portal.processes.index') }}"><span class="fc-brand-mark">F</span><span><strong>FlowCore</strong><small>BPM</small></span></a>
            <div class="fc-process-name"><span class="fc-eyebrow">ÁREA DO USUÁRIO · {{ auth()->user()->role_label }}</span><h1>Minhas tarefas</h1></div>
            <div class="fc-header-actions"><a class="fc-button fc-button-secondary" href="{{ route('portal.requests.index') }}">Minhas solicitações</a><a class="fc-button fc-button-secondary" href="{{ route('portal.processes.index') }}">Meus processos</a>@include('shared.profile-link')<form method="POST" action="{{ route('logout') }}">@csrf<button class="fc-button fc-button-secondary" type="submit">Sair</button></form></div>
        </header>
        <main class="fc-list-content">
            <section class="fc-list-heading"><div><span class="fc-eyebrow">TAREFAS DOS SEUS GRUPOS</span><h2>Tarefas atribuídas a você</h2><p>Revise os dados da solicitação e registre uma justificativa antes de concluir a tarefa.</p></div></section>
            @if (session('status')) <div class="fc-flash-success" role="status">{{ session('status') }}</div> @endif
            @error('flowable') <div class="fc-login-error" role="alert">{{ $message }}</div> @enderror
            @if ($errors->any()) <div class="fc-login-error" role="alert">Revise os campos marcados e tente concluir a tarefa novamente.</div> @endif
            @unless ($unavailable)
                @if ($tasks->isNotEmpty())
                    <div class="fc-task-notification" role="status">
                        <span class="fc-task-notification-count">{{ $tasks->count() }}</span>
                        <span><strong>Você tem {{ $tasks->count() }} {{ $tasks->count() === 1 ? 'tarefa pendente' : 'tarefas pendentes' }}</strong><small>Abra uma tarefa para conferir a solicitação e registrar sua resposta.</small></span>
                    </div>
                @endif
            @endunless
            @if ($unavailable)
                <div class="fc-login-error" role="alert">O motor de processos está indisponível. Tente atualizar esta página quando o serviço estiver ligado.</div>
            @elseif ($tasks->isEmpty())
                <div class="fc-empty-state"><strong>Nenhuma tarefa pendente</strong><span>Novas tarefas dos seus grupos e aprovações atribuídas a você aparecerão aqui.</span></div>
            @else
                <div class="fc-task-list">
                    @foreach ($tasks as $item)
                        @php($task = $item['task'])
                        @php($instance = $item['instance'])
                        @php($schema = $item['schema'])
                        <details class="fc-task-card fc-task-disclosure" @if (old('task_id') === (string) $task['id']) open @endif>
                            <summary class="fc-task-summary">
                                <span class="fc-task-summary-main"><span class="fc-eyebrow">{{ $instance->processDefinition->name }}</span><strong>{{ $task['name'] ?? 'Tarefa de aprovação' }}</strong><small>ID {{ $instance->displayId() }} · Solicitada por {{ $instance->starter->name }} · {{ $instance->created_at->copy()->setTimezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</small>@if ($item['due_at'])<small class="fc-task-deadline @if ($item['is_overdue']) is-overdue @endif">{{ $item['is_overdue'] ? 'Prazo vencido' : 'Prazo' }} · {{ $item['due_at']->format('d/m/Y H:i') }}</small>@endif</span>
                                <span class="fc-status-pill">Pendente</span>
                                <span class="fc-task-toggle"><span class="fc-task-toggle-open">Abrir tarefa</span><span class="fc-task-toggle-close">Recolher</span><span class="fc-task-chevron" aria-hidden="true">⌄</span></span>
                            </summary>
                            <div class="fc-task-details">
                            <input type="hidden" name="task_id" value="{{ $task['id'] }}">
                            <p class="fc-task-meta">ID da solicitação: {{ $instance->displayId() }}</p>
                            <dl class="fc-request-summary">
                                @foreach ($instance->processDefinition->form_schema ?? [] as $field)
                                    @if (array_key_exists($field['key'], $instance->form_data ?? []))
                                        <div><dt>{{ $field['label'] }}</dt><dd>@include('shared.field-value', ['value' => $instance->form_data[$field['key']], 'instance' => $instance])</dd></div>
                                    @endif
                                @endforeach
                            </dl>
                            @if ($item['return_notice'])
                                <aside class="fc-task-return-notice" aria-label="Motivo da devolução">
                                    <span class="fc-eyebrow">DEVOLVIDA PARA CORREÇÃO · {{ $item['return_notice']['actor'] }}</span>
                                    <strong>Motivo informado pela diretoria</strong>
                                    <p>{{ $item['return_notice']['justification'] }}</p>
                                    @if ($item['return_notice']['completed_at']) <small>{{ $item['return_notice']['completed_at']->copy()->setTimezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</small> @endif
                                </aside>
                            @endif
                            @if (filled($schema['instructions'] ?? null))
                                <aside class="fc-task-instructions" aria-label="Instruções da etapa">
                                    <span class="fc-eyebrow">INSTRUÇÕES DA ETAPA</span>
                                    <p>{{ $schema['instructions'] }}</p>
                                </aside>
                            @endif
                            @if (! empty($item['completed_steps']))
                                <section class="fc-completed-process-data" aria-label="Dados preenchidos nas etapas anteriores">
                                    <div class="fc-completed-process-heading"><div><span class="fc-eyebrow">HISTÓRICO DO PROCESSO</span><h4>Respostas das etapas anteriores</h4></div><span>{{ count($item['completed_steps']) }}</span></div>
                                    @foreach ($item['completed_steps'] as $step)
                                        <article class="fc-completed-step">
                                            <div class="fc-completed-step-heading"><div><strong>{{ $step['name'] }}</strong><small>{{ $step['decision'] }} por {{ $step['actor'] }}{{ $step['completed_at'] ? ' · '.$step['completed_at']->copy()->setTimezone(config('app.display_timezone'))->format('d/m/Y H:i') : '' }}</small></div></div>
                                            @if ($step['fields'] !== [])
                                                <dl class="fc-completed-step-fields">
                                                    @foreach ($step['fields'] as $field)
                                                        <div><dt>{{ $field['label'] }}</dt><dd>@include('shared.field-value', ['value' => $field['value'], 'instance' => $instance])</dd></div>
                                                    @endforeach
                                                </dl>
                                            @endif
                                            @if (filled($step['justification']))
                                                <p class="fc-completed-step-comment"><strong>Comentário:</strong> {{ $step['justification'] }}</p>
                                            @endif
                                        </article>
                                    @endforeach
                                </section>
                            @endif
                            <h4 class="fc-task-form-title">{{ ($schema['kind'] ?? 'approval') === 'approval' ? 'Dados para aprovação' : 'Preenchimento desta etapa' }}</h4>
                            <form method="POST" action="{{ route('portal.tasks.approve', $task['id']) }}" class="fc-task-decision-form" enctype="multipart/form-data" data-conditional-form>
                                @csrf
                                @foreach ($schema['fields'] ?? [] as $field)
                                    <label class="fc-start-field" for="task-field-{{ $task['id'] }}-{{ $field['key'] }}" data-conditional-field data-visibility-condition="{{ json_encode($field['visible_when'] ?? []) }}">
                                        <span>{{ $field['label'] }}{{ ($field['required'] ?? false) ? ' *' : '' }}</span>
                                        @switch($field['type'])
                                            @case('textarea')
                                                <textarea id="task-field-{{ $task['id'] }}-{{ $field['key'] }}" name="task_data[{{ $field['key'] }}]" rows="4" data-field-value="{{ $field['key'] }}" @required($field['required'] ?? false)>{{ old('task_data.'.$field['key'], $item['previous_data'][$field['key']] ?? '') }}</textarea>
                                                @break
                                            @case('select')
                                                <select id="task-field-{{ $task['id'] }}-{{ $field['key'] }}" name="task_data[{{ $field['key'] }}]" data-field-value="{{ $field['key'] }}" @required($field['required'] ?? false)>
                                                    @unless ($field['required'] ?? false) <option value="">Selecione</option> @endunless
                                                    @foreach ($field['options'] ?? [] as $option)<option value="{{ $option }}" @selected((string) old('task_data.'.$field['key'], $item['previous_data'][$field['key']] ?? '') === (string) $option)>{{ $option }}</option>@endforeach
                                                </select>
                                                @break
                                            @case('boolean')
                                                <select id="task-field-{{ $task['id'] }}-{{ $field['key'] }}" name="task_data[{{ $field['key'] }}]" data-field-value="{{ $field['key'] }}" @required($field['required'] ?? false)><option value="">Selecione</option><option value="1" @selected(old('task_data.'.$field['key'], isset($item['previous_data'][$field['key']]) ? ((bool) $item['previous_data'][$field['key']] ? '1' : '0') : '') === '1')>Sim</option><option value="0" @selected(old('task_data.'.$field['key'], isset($item['previous_data'][$field['key']]) ? ((bool) $item['previous_data'][$field['key']] ? '1' : '0') : '') === '0')>Não</option></select>
                                                @break
                                            @case('file')
                                                <input id="task-field-{{ $task['id'] }}-{{ $field['key'] }}" name="task_data[{{ $field['key'] }}]" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.webp" data-field-value="{{ $field['key'] }}" @required(($field['required'] ?? false) && empty($item['previous_data'][$field['key']]['attachment_id']))>
                                                @if (! empty($item['previous_data'][$field['key']]['attachment_id']))<small>Anexo atual: <a href="{{ route('portal.attachments.download', [$instance->uuid, $item['previous_data'][$field['key']]['attachment_id']]) }}">{{ $item['previous_data'][$field['key']]['name'] }}</a>. Envie outro arquivo para substituir.</small>@else<small>PDF, Office, CSV, texto ou imagem · até 10 MB</small>@endif
                                                @break
                                            @default
                                                <input id="task-field-{{ $task['id'] }}-{{ $field['key'] }}" name="task_data[{{ $field['key'] }}]" type="{{ $field['type'] === 'number' ? 'number' : ($field['type'] === 'date' ? 'date' : 'text') }}" data-field-value="{{ $field['key'] }}" value="{{ old('task_data.'.$field['key'], $item['previous_data'][$field['key']] ?? '') }}" @required($field['required'] ?? false)>
                                        @endswitch
                                        @error('task_data.'.$field['key']) <small class="fc-form-error">{{ $message }}</small> @enderror
                                    </label>
                                @endforeach
                                @if (($schema['kind'] ?? 'approval') === 'approval' && ! empty($schema['return_targets']))
                                    @if (($schema['allow_multiple_returns'] ?? false) === true)
                                        <fieldset class="fc-return-target-picker">
                                            <legend>Setores que devem corrigir <span>(marque todos os necessários)</span></legend>
                                            @foreach ($schema['return_targets'] as $targetId => $targetName)
                                                <label><input type="checkbox" name="return_to[]" value="{{ $targetId }}" @checked(in_array($targetId, (array) old('return_to', []), true))><span>{{ $targetName }}</span></label>
                                            @endforeach
                                            @error('return_to') <small class="fc-form-error">{{ $message }}</small> @enderror
                                            @error('return_to.*') <small class="fc-form-error">{{ $message }}</small> @enderror
                                        </fieldset>
                                    @else
                                        <label class="fc-start-field" for="return-to-{{ $task['id'] }}">
                                            <span>Setor que deve corrigir (obrigatório ao devolver)</span>
                                            <select id="return-to-{{ $task['id'] }}" name="return_to">
                                                <option value="">Selecione o setor responsável</option>
                                                @foreach ($schema['return_targets'] as $targetId => $targetName)
                                                    <option value="{{ $targetId }}" @selected(old('return_to') === $targetId)>{{ $targetName }}</option>
                                                @endforeach
                                            </select>
                                            @error('return_to') <small class="fc-form-error">{{ $message }}</small> @enderror
                                        </label>
                                    @endif
                                @endif
                                <label class="fc-form-field"><span>{{ ($schema['kind'] ?? 'approval') === 'approval' ? 'Justificativa da aprovação (obrigatória)' : 'Comentário de conclusão (opcional)' }}</span><textarea name="justification" rows="3" maxlength="2000" @required(($schema['kind'] ?? 'approval') === 'approval')>{{ old('justification') }}</textarea></label>
                                @error('justification') <small class="fc-form-error">{{ $message }}</small> @enderror
                                @error('decision') <small class="fc-form-error">{{ $message }}</small> @enderror
                                @error('task_data') <small class="fc-form-error">{{ $message }}</small> @enderror
                                @if (($schema['kind'] ?? 'approval') === 'approval')
                                    <div class="fc-task-approval-actions"><button class="fc-button fc-button-primary" name="decision" value="approved" type="submit">Aprovar tarefa</button><button class="fc-button fc-button-danger" name="decision" value="rejected" type="submit">{{ ! empty($schema['return_targets']) ? 'Devolver para correção' : 'Rejeitar' }}</button></div>
                                @else
                                    <button class="fc-button fc-button-primary" type="submit">Salvar e concluir etapa</button>
                                @endif
                            </form>
                            </div>
                        </details>
                    @endforeach
                </div>
            @endif
        </main>
    </div>
</body>
</html>
