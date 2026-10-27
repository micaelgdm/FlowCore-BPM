<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Responsáveis das tarefas · {{ $process->name }} · FlowCore BPM</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flowcore-page">
    <div class="fc-list-page">
        <header class="fc-header">
            <a class="fc-brand" href="{{ route('master.processes.index') }}"><span class="fc-brand-mark">F</span><span><strong>FlowCore</strong><small>BPM</small></span></a>
            <div class="fc-process-name"><span class="fc-eyebrow">RASCUNHO · V{{ $process->version }}</span><h1>{{ $process->name }}</h1></div>
            <div class="fc-header-actions"><a class="fc-button fc-button-secondary" href="{{ route('master.processes.editor', $process) }}">Editor</a><a class="fc-button fc-button-secondary" href="{{ route('master.processes.form.edit', $process) }}">Formulário</a><form method="POST" action="{{ route('logout') }}">@csrf<button class="fc-button fc-button-secondary" type="submit">Sair</button></form></div>
        </header>
        <main class="fc-list-content">
            <section class="fc-list-heading"><div><span class="fc-eyebrow">ROTEAMENTO DAS TAREFAS</span><h2>Responsável por etapa</h2><p>Direcione cada tarefa humana para um grupo ou para uma pessoa específica. A configuração será gravada no BPMN ao publicar.</p></div></section>
            @if (session('status')) <div class="fc-flash-success" role="status">{{ session('status') }}</div> @endif
            @if ($errors->any()) <div class="fc-login-error" role="alert">{{ $errors->first() }}</div> @endif
            @if (count($userTasks) === 0)
                <div class="fc-empty-state"><strong>Nenhuma tarefa humana encontrada</strong><span>Adicione ao menos uma Tarefa de Usuário ao diagrama e salve o rascunho antes de configurar equipes.</span><a class="fc-button fc-button-secondary" href="{{ route('master.processes.editor', $process) }}">Voltar ao editor</a></div>
            @elseif (count($groups) === 0 && count($users) === 0)
                <div class="fc-empty-state"><strong>Nenhum responsável disponível</strong><span>Cadastre um grupo ou ative ao menos um usuário antes de configurar o roteamento.</span><a class="fc-button fc-button-primary" href="{{ route('master.groups.index') }}">Abrir grupos</a></div>
            @else
                <form method="POST" action="{{ route('master.processes.tasks.update', $process) }}" class="fc-task-assignment-form">
                    @csrf @method('PUT')
                    @foreach ($userTasks as $taskId => $task)
                    @php($taskIndex = $loop->index)
                    @php($taskForm = $taskForms[$taskId] ?? ['kind' => 'work', 'fields' => []])
                    @php($assignment = $assignments[$taskId] ?? ['type' => 'group', 'group_id' => 0, 'user_id' => 0])
                    @php($assignmentType = $assignment['type'] ?? 'group')
                    @php($assignedGroup = $groups->firstWhere('id', (int) ($assignment['group_id'] ?? 0)))
                    @php($assignedUser = $users->firstWhere('id', (int) ($assignment['user_id'] ?? 0)))
                    @php($assignmentLabel = $assignmentType === 'user' ? ($assignedUser?->name ?? 'Usuário não definido') : ($assignedGroup?->name ?? 'Grupo não definido'))
                    @php($taskKindLabel = ($taskForm['kind'] ?? 'work') === 'approval' ? 'Aprovação' : 'Preenchimento de dados')
                    <details class="fc-task-assignment-card fc-task-config-card fc-task-disclosure">
                        <summary class="fc-task-disclosure-summary">
                            <span class="fc-task-disclosure-title"><span class="fc-eyebrow">TAREFA HUMANA · {{ $taskKindLabel }}</span><strong>{{ $task['name'] }}</strong><code>{{ $taskId }}</code></span>
                            <span class="fc-task-disclosure-group">{{ $assignmentLabel }}</span>
                            <span class="fc-group-summary-toggle" aria-hidden="true"></span>
                        </summary>
                        <div class="fc-task-disclosure-content">
                        <input type="hidden" name="assignments[{{ $taskIndex }}][task_id]" value="{{ $taskId }}">
                        <input type="hidden" name="task_forms[{{ $taskIndex }}][task_id]" value="{{ $taskId }}">
                        <div class="fc-task-config-fields">
                            <label class="fc-form-field"><span>Tipo de responsável</span><select name="assignments[{{ $taskIndex }}][type]" data-assignment-type><option value="group" @selected($assignmentType === 'group')>Grupo</option><option value="user" @selected($assignmentType === 'user')>Usuário específico</option></select></label>
                            <label class="fc-form-field" data-assignment-group @if ($assignmentType !== 'group') hidden @endif><span>Grupo responsável</span><select name="assignments[{{ $taskIndex }}][group_id]" data-assignment-value><option value="">Escolha um grupo</option>@foreach ($groups as $group)<option value="{{ $group->id }}" @selected((int) ($assignment['group_id'] ?? 0) === $group->id)>{{ $group->name }}</option>@endforeach</select></label>
                            <label class="fc-form-field" data-assignment-user @if ($assignmentType !== 'user') hidden @endif><span>Usuário responsável</span><select name="assignments[{{ $taskIndex }}][user_id]" data-assignment-value><option value="">Escolha uma pessoa</option>@foreach ($users as $user)<option value="{{ $user->id }}" @selected((int) ($assignment['user_id'] ?? 0) === $user->id)>{{ $user->name }} · {{ $user->email }}</option>@endforeach</select></label>
                            <label class="fc-form-field"><span>Tipo de etapa</span><select name="task_forms[{{ $taskIndex }}][kind]" required><option value="work" @selected(($taskForm['kind'] ?? 'work') === 'work')>Preenchimento de dados</option><option value="approval" @selected(($taskForm['kind'] ?? 'work') === 'approval')>Aprovação</option></select><small>A aprovação grava a variável BPMN fc_decision para uso em gateways exclusivos.</small></label>
                            <label class="fc-form-field"><span>Prazo da tarefa (horas corridas)</span><input type="number" name="task_forms[{{ $taskIndex }}][sla_hours]" min="1" max="8760" step="1" value="{{ $taskForm['sla_hours'] ?? '' }}" placeholder="Sem prazo"><small>O prazo começa quando a tarefa chega ao grupo. Deixe vazio para não controlar vencimento.</small></label>
                        </div>
                        <label class="fc-process-data-toggle">
                            <input type="hidden" name="task_forms[{{ $taskIndex }}][show_process_data]" value="0">
                            <input type="checkbox" name="task_forms[{{ $taskIndex }}][show_process_data]" value="1" @checked($taskForm['show_process_data'] ?? (($taskForm['kind'] ?? 'work') === 'approval'))>
                            <span><strong>Exibir dados anteriores nesta tarefa</strong><small>Mostra ao responsável os campos da solicitação e as respostas já preenchidas nas etapas concluídas.</small></span>
                        </label>
                        <div class="fc-task-form-builder" data-field-builder>
                            <div class="fc-task-form-heading"><div><strong>Campos desta etapa</strong><small>Esses campos são exibidos somente ao responsável configurado.</small></div><button type="button" data-add-form-field class="fc-button fc-button-secondary">Adicionar campo</button></div>
                            <div id="task-form-fields-{{ $taskIndex }}" data-form-fields>
                                @foreach ($taskForm['fields'] ?? [] as $fieldIndex => $field)
                                    @include('processes.partials.form-field', ['field' => $field, 'index' => $fieldIndex, 'namePrefix' => "task_forms[{$taskIndex}][fields]"])
                                @endforeach
                            </div>
                            <template data-form-field-template>@include('processes.partials.form-field', ['field' => [], 'index' => '__INDEX__', 'namePrefix' => "task_forms[{$taskIndex}][fields]"])</template>
                        </div>
                        </div>
                    </details>
                    @endforeach
                    @php($hasApprovalTask = collect($userTasks)->contains(fn ($task, $taskId) => ($taskForms[$taskId]['kind'] ?? 'work') === 'approval') )
                    @if ($hasApprovalTask && count($gatewayBranches) > 0)
                        <section class="fc-gateway-routes">
                            <div><span class="fc-eyebrow">RESULTADO DA APROVAÇÃO</span><h3>Saídas dos gateways exclusivos</h3><p>Associe cada saída à aprovação, rejeição ou tarefa para correção. Nas devoluções, o diretor escolhe a etapa no formulário da tarefa.</p></div>
                            @foreach ($gatewayBranches as $gatewayId => $gateway)
                                @php($gatewayIndex = $loop->index)
                                <article class="fc-gateway-card">
                                    <h4>{{ $gateway['name'] }}</h4><code>{{ $gatewayId }}</code>
                                    @foreach ($gateway['flows'] as $flowIndex => $flow)
                                        <div class="fc-gateway-flow">
                                            <span>{{ $flow['name'] }} <small>({{ $flow['id'] }})</small></span>
                                            <input type="hidden" name="gateway_routes[{{ $gatewayIndex }}_{{ $flowIndex }}][gateway_id]" value="{{ $gatewayId }}">
                                            <input type="hidden" name="gateway_routes[{{ $gatewayIndex }}_{{ $flowIndex }}][flow_id]" value="{{ $flow['id'] }}">
                            <select name="gateway_routes[{{ $gatewayIndex }}_{{ $flowIndex }}][outcome]" required>
                                                <option value="">Escolha o resultado</option>
                                                <option value="approved" @selected(($gatewayRoutes[$gatewayId][$flow['id']] ?? '') === 'approved')>Aprovado</option>
                                                <option value="rejected" @selected(($gatewayRoutes[$gatewayId][$flow['id']] ?? '') === 'rejected')>Rejeitado</option>
                                @foreach ($userTasks as $targetId => $targetTask)
                                    @if (($taskForms[$targetId]['kind'] ?? 'work') === 'work')
                                        <option value="return:{{ $targetId }}" @selected(($gatewayRoutes[$gatewayId][$flow['id']] ?? '') === "return:{$targetId}")>Devolver para correção · {{ $targetTask['name'] }}</option>
                                    @endif
                                @endforeach
                                                <option value="default" @selected(($gatewayRoutes[$gatewayId][$flow['id']] ?? '') === 'default')>Padrão</option>
                                            </select>
                                        </div>
                                    @endforeach
                                </article>
                            @endforeach
                        </section>
                    @elseif ($hasApprovalTask)
                        <div class="fc-note"><strong>Decisão sem desvio no diagrama</strong><span>Para seguir caminhos diferentes em caso de aprovação e rejeição, adicione um Gateway Exclusivo com pelo menos duas saídas e volte a esta configuração.</span></div>
                    @endif
                    <div class="fc-form-builder-actions"><a class="fc-button fc-button-secondary" href="{{ route('master.processes.editor', $process) }}">Voltar ao editor</a><button class="fc-button fc-button-primary" type="submit">Salvar equipes</button></div>
                </form>
            @endif
        </main>
    </div>
</body>
</html>
