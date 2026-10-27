<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\ProcessDefinition;
use App\Models\ProcessInstance;
use App\Models\ProcessGroup;
use App\Services\FlowableClient;
use DOMDocument;
use DOMElement;
use DOMXPath;
use App\Exceptions\FlowableDeploymentConflict;
use App\Exceptions\FlowablePublicationRejected;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class ProcessDefinitionController extends Controller
{
    public function index(): View
    {
        $processes = ProcessDefinition::query()->latest('updated_at')->get();
        $currentPublishedByFamily = $processes
            ->where('is_active', true)
            ->groupBy('family_uuid')
            ->map(fn ($versions) => $versions->first()->id)
            ->all();
        $requestMetrics = ProcessInstance::query()
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as running_count', ['running'])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_count', ['completed'])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed_count', ['failed'])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_start_count', ['starting'])
            ->first();
        $processMetrics = DB::table('process_definitions as definitions')
            ->leftJoin('process_instances as instances', 'instances.process_definition_id', '=', 'definitions.id')
            ->selectRaw('definitions.family_uuid, MAX(definitions.name) as process_name, MAX(CASE WHEN definitions.status = ? AND definitions.is_active = 1 THEN definitions.version ELSE NULL END) as active_version', ['published'])
            ->selectRaw('COUNT(instances.id) as total_count')
            ->selectRaw('SUM(CASE WHEN instances.status = ? THEN 1 ELSE 0 END) as running_count', ['running'])
            ->selectRaw('SUM(CASE WHEN instances.status = ? THEN 1 ELSE 0 END) as completed_count', ['completed'])
            ->selectRaw('SUM(CASE WHEN instances.status = ? THEN 1 ELSE 0 END) as failed_count', ['failed'])
            ->groupBy('definitions.family_uuid')
            ->orderBy('process_name')
            ->get();

        return view('processes.index', [
            'processes' => $processes,
            'currentPublishedByFamily' => $currentPublishedByFamily,
            'requestMetrics' => $requestMetrics,
            'processMetrics' => $processMetrics,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $process = ProcessDefinition::create([
            ...$data,
            'status' => 'draft',
            'family_uuid' => (string) Str::uuid(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('master.processes.editor', $process);
    }

    public function edit(ProcessDefinition $process): View
    {
        return view('processes.editor', compact('process'));
    }

    public function newVersion(Request $request, ProcessDefinition $process): RedirectResponse
    {
        abort_unless($process->status === 'published' && $process->is_active, 409, 'Crie uma nova versão a partir da versão que está em uso.');

        $draft = DB::transaction(function () use ($request, $process): ProcessDefinition {
            $versions = ProcessDefinition::query()
                ->where('family_uuid', $process->family_uuid)
                ->orderByDesc('version')
                ->lockForUpdate()
                ->get();
            $existingDraft = $versions->firstWhere('status', 'draft');
            if ($existingDraft) {
                return $existingDraft;
            }

            $source = $versions->firstWhere('id', $process->id);
            abort_unless($source && $source->status === 'published' && $source->is_active, 409, 'Abra a versão em uso para iniciar outra versão.');

            $newVersion = ProcessDefinition::query()->create([
                'name' => $source->name,
                'description' => $source->description,
                'status' => 'draft',
                'version' => $versions->max('version') + 1,
                'family_uuid' => $source->family_uuid,
                'bpmn_xml' => $source->bpmn_xml,
                'form_schema' => $source->form_schema,
                'task_group_assignments' => $source->task_group_assignments,
                'task_form_schemas' => $source->task_form_schemas,
                'gateway_routes' => $source->gateway_routes,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $permissions = $source->authorizedUsers()->get()->mapWithKeys(fn ($user): array => [$user->id => [
                'can_view' => (bool) $user->pivot->can_view,
                'can_start' => (bool) $user->pivot->can_start,
                'can_approve' => (bool) $user->pivot->can_approve,
            ]])->all();
            $newVersion->authorizedUsers()->sync($permissions);

            return $newVersion;
        });

        return redirect()->route('master.processes.editor', $draft)->with('status', "Rascunho da versão V{$draft->version} criado. A versão publicada e suas solicitações continuam preservadas.");
    }

    public function activate(Request $request, ProcessDefinition $process): RedirectResponse
    {
        abort_unless($process->status === 'published', 409, 'Somente uma versão publicada pode ser ativada.');

        DB::transaction(function () use ($request, $process): void {
            $versions = ProcessDefinition::query()
                ->where('family_uuid', $process->family_uuid)
                ->orderBy('version')
                ->lockForUpdate()
                ->get();
            $target = $versions->firstWhere('id', $process->id);

            abort_unless($target && $target->status === 'published', 409, 'Esta versão não está publicada.');

            ProcessDefinition::query()
                ->where('family_uuid', $process->family_uuid)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $target->forceFill(['is_active' => true])->save();

            $previousVersion = $versions->first(fn (ProcessDefinition $version): bool => $version->is_active && $version->id !== $target->id)?->version;
            AuditEvent::query()->create([
                'actor_id' => $request->user()->id,
                'actor_name' => $request->user()->name,
                'action' => 'process.version.activated',
                'subject' => "{$target->name} · V{$target->version}",
                'details' => ['process_id' => $target->id, 'family_uuid' => $target->family_uuid, 'previous_version' => $previousVersion],
            ]);
        });

        return redirect()->route('master.processes.index')->with(
            'status',
            "V{$process->version} está em uso para novas solicitações. As solicitações existentes continuam na versão em que começaram.",
        );
    }

    public function discardVersion(Request $request, ProcessDefinition $process): RedirectResponse
    {
        abort_unless($process->status === 'draft' && $process->version > 1, 409, 'Somente um rascunho de nova versão pode ser descartado.');

        $publishedVersion = DB::transaction(function () use ($request, $process): ProcessDefinition {
            $versions = ProcessDefinition::query()
                ->where('family_uuid', $process->family_uuid)
                ->orderByDesc('version')
                ->lockForUpdate()
                ->get();
            $current = $versions->firstWhere('id', $process->id);
            abort_unless($current && $current->status === 'draft' && $current->version > 1, 409, 'Esta versão não pode ser descartada.');
            abort_unless($versions->first()?->id === $current->id, 409, 'Só é possível descartar o rascunho mais recente.');
            abort_unless($versions->contains(fn (ProcessDefinition $version): bool => $version->status === 'published' && $version->version < $current->version), 409, 'Não há uma versão publicada anterior para preservar.');
            abort_unless(! $current->instances()->exists(), 409, 'Esta versão tem solicitações vinculadas e não pode ser removida.');

            AuditEvent::query()->create([
                'actor_id' => $request->user()->id,
                'actor_name' => $request->user()->name,
                'action' => 'process.version.discarded',
                'subject' => "{$current->name} · V{$current->version}",
                'details' => ['process_id' => $current->id, 'family_uuid' => $current->family_uuid, 'version' => $current->version],
            ]);
            $current->authorizedUsers()->detach();
            $current->delete();

            return $versions->first(fn (ProcessDefinition $version): bool => $version->status === 'published' && $version->version < $current->version);
        });

        return redirect()->route('master.processes.editor', $publishedVersion)
            ->with('status', "Rascunho V{$process->version} descartado. A versão publicada e as solicitações existentes foram preservadas.");
    }

    public function destroy(Request $request, ProcessDefinition $process, FlowableClient $flowable): RedirectResponse
    {
        $familyUuid = $process->family_uuid;
        $versions = ProcessDefinition::query()
            ->where('family_uuid', $familyUuid)
            ->orderBy('version')
            ->get();

        abort_unless($versions->contains('id', $process->id), 404);

        if (ProcessInstance::query()->whereIn('process_definition_id', $versions->pluck('id'))->exists()) {
            return back()->withErrors(['process' => 'Este processo tem solicitações registradas e não foi excluído para preservar o histórico.']);
        }

        try {
            // Remove older deployments first. If Flowable becomes unavailable partway through,
            // retrying is safe because already removed deployments return 404 and are ignored.
            foreach ($versions as $version) {
                if (filled($version->flowable_deployment_id)) {
                    $flowable->deleteDeployment($version->flowable_deployment_id);
                }
            }
        } catch (RequestException $exception) {
            report($exception);

            return back()->withErrors(['process' => 'O Flowable recusou a exclusão. Verifique se alguma versão tem solicitações ativas ou histórico antes de tentar novamente.']);
        } catch (ConnectionException|RuntimeException $exception) {
            report($exception);

            return back()->withErrors(['process' => 'Não foi possível confirmar a exclusão no Flowable. Os registros locais foram mantidos; verifique o motor e tente novamente.']);
        }

        $deleted = DB::transaction(function () use ($request, $familyUuid, $process): bool {
            $lockedVersions = ProcessDefinition::query()
                ->where('family_uuid', $familyUuid)
                ->orderBy('version')
                ->lockForUpdate()
                ->get();

            abort_unless($lockedVersions->contains('id', $process->id), 404);

            if (ProcessInstance::query()->whereIn('process_definition_id', $lockedVersions->pluck('id'))->exists()) {
                return false;
            }

            $deletedVersions = $lockedVersions->pluck('version')->values()->all();
            AuditEvent::query()->create([
                'actor_id' => $request->user()->id,
                'actor_name' => $request->user()->name,
                'action' => 'process.family.deleted',
                'subject' => "{$process->name} · processo e versões excluídos",
                'details' => ['process_id' => $process->id, 'family_uuid' => $familyUuid, 'versions' => $deletedVersions],
            ]);

            foreach ($lockedVersions as $version) {
                $version->authorizedUsers()->detach();
                $version->delete();
            }

            return true;
        });

        if (! $deleted) {
            return back()->withErrors(['process' => 'Uma solicitação foi iniciada durante a exclusão. O histórico foi preservado; atualize a página e tente novamente.']);
        }

        return redirect()->route('master.processes.index')->with('status', 'Processo e versões excluídos.');
    }

    public function publish(Request $request, ProcessDefinition $process, FlowableClient $flowable): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request, $process, $flowable): JsonResponse {
                $locked = ProcessDefinition::query()->lockForUpdate()->findOrFail($process->id);
                $taskGroupAssignments = $locked->task_group_assignments ?? [];
                $taskFormSchemas = $locked->task_form_schemas ?? [];
                $groupsById = ProcessGroup::query()->whereIn('id', array_values($taskGroupAssignments))->get()->keyBy('id');
                $gatewayRoutes = $locked->gateway_routes ?? [];
                $preparedXml = $this->prepareBpmnForPublication($locked->bpmn_xml ?? '', $locked->id, $taskGroupAssignments, $groupsById->all(), $taskFormSchemas, $gatewayRoutes);

                if ($locked->status === 'published') {
                    if ($locked->published_bpmn_hash === hash('sha256', $preparedXml)) {
                        return response()->json([
                            'message' => 'Esta versão já está publicada.',
                            'status' => 'published',
                            'deployment_id' => $locked->flowable_deployment_id,
                            'process_definition_id' => $locked->flowable_process_definition_id,
                        ]);
                    }

                    return response()->json([
                        'message' => 'Esta versão já foi publicada e não pode ser alterada. Crie uma nova versão para publicar mudanças.',
                    ], 409);
                }

                abort_unless($locked->status === 'draft', 409, 'Somente rascunhos podem ser publicados.');

                $deploymentName = "flowcore-process-{$locked->id}-v{$locked->version}";
                $deployment = $flowable->deployBpmn($deploymentName, $preparedXml);
                $hash = hash('sha256', $preparedXml);

                ProcessDefinition::query()
                    ->where('family_uuid', $locked->family_uuid)
                    ->where('id', '!=', $locked->id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);

                $locked->forceFill([
                    'bpmn_xml' => $preparedXml,
                    'status' => 'published',
                    'is_active' => true,
                    'flowable_deployment_id' => $deployment['deployment_id'],
                    'flowable_process_definition_id' => $deployment['process_definition_id'],
                    'published_bpmn_hash' => $hash,
                    'published_at' => now(),
                    'updated_by' => $request->user()->id,
                ])->save();

                AuditEvent::query()->create([
                    'actor_id' => $request->user()->id,
                    'actor_name' => $request->user()->name,
                    'action' => 'process.version.published',
                    'subject' => "{$locked->name} · V{$locked->version}",
                    'details' => ['process_id' => $locked->id, 'family_uuid' => $locked->family_uuid],
                ]);

                return response()->json([
                    'message' => 'Versão publicada no motor de processos.',
                    'status' => 'published',
                    'deployment_id' => $deployment['deployment_id'],
                    'process_definition_id' => $deployment['process_definition_id'],
                ], 201);
            });
        } catch (FlowableDeploymentConflict $exception) {
            report($exception);

            return response()->json([
                'message' => 'Já existe uma publicação desta versão com conteúdo diferente. A publicação foi interrompida para evitar duplicidade.',
            ], 409);
        } catch (FlowablePublicationRejected|RequestException $exception) {
            report($exception);
            $status = $exception instanceof FlowablePublicationRejected || $this->isBpmnRejection($exception) ? 422 : 503;

            return response()->json([
                'message' => $status === 422
                    ? 'O Flowable recusou o BPMN. Confira se o modelo contém exatamente um processo executável com elementos suportados.'
                    : 'O motor de processos está indisponível. O rascunho continua salvo e pode ser publicado novamente.',
            ], $status);
        } catch (ConnectionException $exception) {
            report($exception);

            return response()->json([
                'message' => 'O motor de processos está indisponível. O rascunho continua salvo e pode ser publicado novamente.',
            ], 503);
        } catch (QueryException $exception) {
            report($exception);

            return response()->json(['message' => 'Não foi possível registrar a publicação. Tente consultar o estado antes de repetir a operação.'], 503);
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(['message' => 'Não foi possível validar a resposta do motor de processos.'], 502);
        }
    }

    public function updateDiagram(Request $request, ProcessDefinition $process): JsonResponse
    {
        abort_unless($process->status === 'draft', 409, 'Somente rascunhos podem ser alterados.');

        $data = $request->validate([
            'bpmn_xml' => ['required', 'string', 'max:2000000'],
        ]);

        $xml = $data['bpmn_xml'];
        $document = new DOMDocument();
        $document->resolveExternals = false;
        $document->substituteEntities = false;

        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)
            || ! @$document->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)
            || $document->documentElement?->localName !== 'definitions'
            || $document->documentElement?->namespaceURI !== 'http://www.omg.org/spec/BPMN/20100524/MODEL'
            || $document->getElementsByTagNameNS('http://www.omg.org/spec/BPMN/20100524/MODEL', 'process')->length === 0) {
            return response()->json(['message' => 'O arquivo não contém um diagrama BPMN 2.0 válido.'], 422);
        }

        $process->forceFill([
            'bpmn_xml' => $xml,
            'updated_by' => $request->user()->id,
        ])->save();

        return response()->json([
            'message' => 'Rascunho salvo.',
            'updated_at' => $process->fresh()->updated_at?->toIso8601String(),
        ]);
    }

    private function prepareBpmnForPublication(string $xml, int $processDefinitionId, array $taskGroupAssignments = [], array $groupsById = [], array $taskFormSchemas = [], array $gatewayRoutes = []): string
    {
        $document = new DOMDocument();
        $document->resolveExternals = false;
        $document->substituteEntities = false;
        $namespace = 'http://www.omg.org/spec/BPMN/20100524/MODEL';

        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)
            || ! @$document->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)
            || $document->documentElement?->localName !== 'definitions'
            || $document->documentElement?->namespaceURI !== $namespace) {
            throw new FlowablePublicationRejected('Invalid BPMN XML.');
        }

        if (! $document->documentElement->hasAttribute('targetNamespace')) {
            $document->documentElement->setAttribute('targetNamespace', 'https://flowcore.local/bpmn');
        }

        $processes = $document->getElementsByTagNameNS($namespace, 'process');

        if ($processes->length !== 1 || ! $processes->item(0) instanceof DOMElement) {
            throw new FlowablePublicationRejected('A publicação inicial requer exatamente um processo BPMN.');
        }

        $process = $processes->item(0);
        $userTasks = $document->getElementsByTagNameNS($namespace, 'userTask');

        if ($taskGroupAssignments !== []) {
            if ($userTasks->length !== count($taskGroupAssignments)) {
                throw new FlowablePublicationRejected('Every human task must have a configured group.');
            }

            if ($userTasks->length !== count($taskFormSchemas)) {
                throw new FlowablePublicationRejected('Every human task must have a configured form.');
            }

            $flowableNamespace = 'http://flowable.org/bpmn';
            $document->documentElement->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:flowable', $flowableNamespace);

            foreach ($userTasks as $task) {
                if (! $task instanceof DOMElement) {
                    continue;
                }

                $groupId = $taskGroupAssignments[$task->getAttribute('id')] ?? null;
                $group = is_numeric($groupId) ? ($groupsById[(int) $groupId] ?? null) : null;

                if (! $group) {
                    throw new FlowablePublicationRejected('A human task refers to a missing group.');
                }

                if (! isset($taskFormSchemas[$task->getAttribute('id')]['kind'])) {
                    throw new FlowablePublicationRejected('A human task has no configured form type.');
                }

                $task->removeAttributeNS($flowableNamespace, 'assignee');
                $task->setAttributeNS($flowableNamespace, 'flowable:candidateGroups', $group->flowable_group_id);

                foreach (['humanPerformer', 'potentialOwner'] as $assignmentElement) {
                    foreach (iterator_to_array($task->getElementsByTagNameNS($namespace, $assignmentElement)) as $element) {
                        $element->parentNode?->removeChild($element);
                    }
                }
            }

            $hasApprovalTask = collect($taskFormSchemas)->contains(fn ($schema): bool => is_array($schema) && ($schema['kind'] ?? null) === 'approval');
            $approvalGateways = ProcessTaskAssignmentController::exclusiveGateways($xml);
            if ($hasApprovalTask && $approvalGateways !== []) {
                foreach ($approvalGateways as $gatewayId => $gateway) {
                    $routes = $gatewayRoutes[$gatewayId] ?? [];
                    if (! is_array($routes) || count($routes) !== count($gateway['flows'])) {
                        throw new FlowablePublicationRejected('Every approval gateway output must have a decision route.');
                    }

                    $configuredFlowIds = array_keys($routes);
                    $actualFlowIds = array_column($gateway['flows'], 'id');
                    if (array_diff($actualFlowIds, $configuredFlowIds) !== [] || array_diff($configuredFlowIds, $actualFlowIds) !== []) {
                        throw new FlowablePublicationRejected('An approval gateway route does not match the BPMN model.');
                    }
                }
            }
        }

        $configuredUserTasks = ProcessTaskAssignmentController::userTasks($xml);
        if ($gatewayRoutes !== []) {
            $xsiNamespace = 'http://www.w3.org/2001/XMLSchema-instance';
            $document->documentElement->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', $xsiNamespace);
            $flowsById = [];
            foreach ($document->getElementsByTagNameNS($namespace, 'sequenceFlow') as $flow) {
                if ($flow instanceof DOMElement) {
                    $flowsById[$flow->getAttribute('id')] = $flow;
                }
            }

            foreach ($gatewayRoutes as $gatewayId => $routes) {
                $gateway = null;
                foreach ($document->getElementsByTagNameNS($namespace, 'exclusiveGateway') as $candidate) {
                    if ($candidate instanceof DOMElement && $candidate->getAttribute('id') === (string) $gatewayId) {
                        $gateway = $candidate;
                        break;
                    }
                }

                if (! $gateway || ! is_array($routes) || $routes === []) {
                    throw new FlowablePublicationRejected('A gateway decision route is missing from the BPMN model.');
                }

                $defaultFlowId = null;
                foreach ($routes as $flowId => $outcome) {
                    $flow = $flowsById[$flowId] ?? null;
                    $returnTaskId = is_string($outcome) && str_starts_with($outcome, 'return:') ? substr($outcome, 7) : null;
                    $isValidOutcome = in_array($outcome, ['approved', 'rejected', 'default'], true)
                        || (is_string($returnTaskId)
                            && isset($taskFormSchemas[$returnTaskId])
                            && ($taskFormSchemas[$returnTaskId]['kind'] ?? null) === 'work'
                            && isset($configuredUserTasks[$returnTaskId]));
                    if (! $flow instanceof DOMElement || ! $isValidOutcome) {
                        throw new FlowablePublicationRejected('A gateway decision route is invalid.');
                    }

                    foreach (iterator_to_array($flow->childNodes) as $child) {
                        if ($child instanceof DOMElement && $child->namespaceURI === $namespace && $child->localName === 'conditionExpression') {
                            $flow->removeChild($child);
                        }
                    }

                    if ($outcome === 'default') {
                        $defaultFlowId = (string) $flowId;
                        continue;
                    }

                    $condition = $document->createElementNS($namespace, 'bpmn:conditionExpression');
                    $condition->setAttributeNS($xsiNamespace, 'xsi:type', 'bpmn:tFormalExpression');
                    $expression = is_string($returnTaskId)
                        ? '${fc_decision == ' . "'rejected' && fc_return_to == '" . $returnTaskId . "'" . '}'
                        : '${fc_decision == ' . "'" . $outcome . "'" . '}';
                    $condition->appendChild($document->createTextNode($expression));
                    $flow->appendChild($condition);
                }

                if ($defaultFlowId !== null) {
                    $gateway->setAttribute('default', $defaultFlowId);
                } else {
                    $gateway->removeAttribute('default');
                }
            }
        }

        $oldId = $process->getAttribute('id');
        $processId = "flowcore_process_{$processDefinitionId}";
        $process->setAttribute('id', $processId);
        $process->setAttribute('isExecutable', 'true');

        if ($oldId !== '') {
            $xpath = new DOMXPath($document);

            foreach ($xpath->query('//*[@bpmnElement or @processRef]') ?: [] as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }

                foreach (['bpmnElement', 'processRef'] as $attribute) {
                    if ($node->getAttribute($attribute) === $oldId) {
                        $node->setAttribute($attribute, $processId);
                    }
                }
            }
        }

        return $document->saveXML() ?: throw new FlowablePublicationRejected('Could not serialize BPMN XML.');
    }

    private function isBpmnRejection(RequestException $exception): bool
    {
        if ($exception->response->status() === 400) {
            return true;
        }

        $details = $exception->response->body();

        return str_contains($details, 'XMLException')
            || str_contains($details, 'BpmnParseException')
            || str_contains($details, 'SAXParseException');
    }
}
