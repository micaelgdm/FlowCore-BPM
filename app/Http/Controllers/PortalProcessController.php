<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Models\ProcessDefinition;
use App\Models\ProcessInstance;
use App\Services\FlowableClient;
use RuntimeException;

class PortalProcessController extends Controller
{
    public function index(Request $request, FlowableClient $flowable): View
    {
        $pendingTaskCount = null;

        try {
            $pendingTaskCount = ProcessTaskController::pendingCountFor($request->user(), $flowable);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            report($exception);
        }

        return view('portal.processes', [
            'pendingTaskCount' => $pendingTaskCount,
            'processes' => $request->user()
                ->accessibleProcesses()
                ->with('authorizedUsers')
                ->wherePivot('can_view', true)
                ->where('status', 'published')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'instances' => $request->user()
                ->initiatedProcessInstances()
                ->with('processDefinition')
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    public function startForm(Request $request, ProcessDefinition $process): View
    {
        $this->ensureCanStart($request, $process);
        abort_unless(is_array($process->form_schema) && count($process->form_schema) > 0, 409, 'Este processo ainda não tem um formulário publicado.');
        abort_unless($this->hasTaskGroupAssignments($process) || $process->authorizedUsers()->wherePivot('can_approve', true)->exists(), 409, 'O processo ainda não tem um grupo responsável configurado.');

        return view('portal.start-process', [
            'process' => $process,
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function start(Request $request, ProcessDefinition $process, FlowableClient $flowable): RedirectResponse
    {
        $this->ensureCanStart($request, $process);
        abort_unless(is_array($process->form_schema) && count($process->form_schema) > 0, 409, 'Este processo ainda não tem um formulário publicado.');
        abort_unless(is_string($process->flowable_process_definition_id) && $process->flowable_process_definition_id !== '', 409, 'A definição publicada não está disponível no motor de processos.');

        $legacyApprover = $process->authorizedUsers()->wherePivot('can_approve', true)->first();
        $hasTaskGroups = $this->hasTaskGroupAssignments($process);
        abort_unless($legacyApprover || $hasTaskGroups, 409, 'O processo ainda não tem um grupo responsável configurado.');

        $rules = ['idempotency_key' => ['required', 'uuid']];

        foreach ($process->form_schema as $field) {
            $presence = ($field['required'] ?? false) ? 'required' : 'nullable';
            $typeRules = match ($field['type']) {
                'text' => ['string', 'max:2000'],
                'textarea' => ['string', 'max:10000'],
                'number' => ['numeric', 'between:-999999999999,999999999999'],
                'date' => ['date_format:Y-m-d'],
                'select' => [Rule::in($field['options'] ?? [])],
                'boolean' => ['boolean'],
                default => ['prohibited'],
            };
            $rules[$field['key']] = [$presence, ...$typeRules];
        }

        $data = $request->validate($rules);
        $formData = [];

        foreach ($process->form_schema as $field) {
            $value = $data[$field['key']] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $formData[$field['key']] = match ($field['type']) {
                'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'number' => str_contains((string) $value, '.') ? (float) $value : (int) $value,
                default => $value,
            };
        }

        $idempotencyKey = (string) $data['idempotency_key'];

        try {
            $instance = DB::transaction(function () use ($request, $process, $flowable, $legacyApprover, $hasTaskGroups, $idempotencyKey, $formData): ProcessInstance {
                $instance = ProcessInstance::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($instance) {
                    abort_unless($instance->started_by === $request->user()->id && $instance->process_definition_id === $process->id, 409);
                    abort_unless($instance->form_data === $formData, 409, 'Esta chave já foi usada para outra resposta. Reabra o formulário para criar uma nova solicitação.');

                    if (in_array($instance->status, ['running', 'completed'], true)) {
                        return $instance;
                    }
                } else {
                    $instance = ProcessInstance::query()->create([
                        'uuid' => (string) Str::uuid(),
                        'idempotency_key' => $idempotencyKey,
                        'process_definition_id' => $process->id,
                        'started_by' => $request->user()->id,
                        'status' => 'starting',
                        'form_data' => $formData,
                    ]);
                }

                try {
                    $started = $flowable->startProcessInstance(
                        $process->flowable_process_definition_id,
                        $idempotencyKey,
                        $formData,
                    );
                    if (! $hasTaskGroups && $legacyApprover) {
                        $flowable->assignUnassignedTasks($started['process_instance_id'], self::flowableAssignee($legacyApprover->id));
                    }
                    $instance->forceFill([
                        'flowable_process_instance_id' => $started['process_instance_id'],
                        'status' => $flowable->processInstanceIsActive($started['process_instance_id']) ? 'running' : 'completed',
                    ])->save();
                } catch (ConnectionException|RequestException|RuntimeException $exception) {
                    report($exception);
                    $instance->forceFill(['status' => 'failed'])->save();
                }

                return $instance;
            });
        } catch (QueryException $exception) {
            $instance = ProcessInstance::query()->where('idempotency_key', $idempotencyKey)->first();

            if (! $instance) {
                throw $exception;
            }
        }

        if ($instance->status !== 'running') {
            return back()->withInput()->withErrors([
                'flowable' => 'Não foi possível iniciar o processo. O formulário ficou salvo nesta página; confira se o motor está ligado e tente novamente.',
            ]);
        }

        return redirect()->route('portal.requests.show', $instance->uuid)->with('status', 'Solicitação enviada ao fluxo de aprovação.');
    }

    public function show(Request $request, string $uuid): View
    {
        $instance = ProcessInstance::query()
            ->with(['processDefinition', 'decisions.actor'])
            ->where('uuid', $uuid)
            ->where('started_by', $request->user()->id)
            ->firstOrFail();

        return view('portal.request', compact('instance'));
    }

    private function ensureAccessible(Request $request, ProcessDefinition $process): void
    {
        abort_unless($process->status === 'published'
            && $request->user()->accessibleProcesses()->wherePivot('can_view', true)->whereKey($process->id)->exists(), 404);
    }

    private function ensureCanStart(Request $request, ProcessDefinition $process): void
    {
        abort_unless($process->status === 'published'
            && $process->is_active
            && $request->user()->role !== 'master'
            && $request->user()->accessibleProcesses()->wherePivot('can_start', true)->whereKey($process->id)->exists(), 404);
    }

    public static function flowableAssignee(int $userId): string
    {
        return "flowcore_user_{$userId}";
    }

    private function hasTaskGroupAssignments(ProcessDefinition $process): bool
    {
        $userTasks = ProcessTaskAssignmentController::userTasks($process->bpmn_xml ?? '');
        $assignments = $process->task_group_assignments ?? [];

        if ($userTasks === []) {
            return true;
        }

        if (count($userTasks) !== count($assignments)) {
            return false;
        }

        foreach (array_keys($userTasks) as $taskId) {
            if (! isset($assignments[$taskId])) {
                return false;
            }
        }

        return true;
    }
}
