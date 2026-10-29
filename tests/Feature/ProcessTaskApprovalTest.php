<?php

namespace Tests\Feature;

use App\Http\Controllers\PortalProcessController;
use App\Models\ProcessDefinition;
use App\Models\ProcessInstance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessTaskApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_approver_sees_only_their_assigned_tasks_for_processes_they_can_approve(): void
    {
        $approver = User::factory()->create(['role' => 'approver', 'is_active' => true]);
        $instance = $this->runningInstance($approver);
        $this->configureFlowable();
        Http::fake(fn (Request $request) => Http::response([
            'total' => 1,
            'data' => [[
                'id' => 'task-501',
                'name' => 'Aprovar solicitação',
                'assignee' => PortalProcessController::flowableAssignee($approver->id),
                'processInstanceId' => $instance->flowable_process_instance_id,
            ]],
        ]));

        $this->actingAs($approver)
            ->get(route('portal.tasks.index'))
            ->assertOk()
            ->assertSee('Tarefas atribuídas a você')
            ->assertSee('Aprovar solicitação')
            ->assertSee('Compra para a equipe')
            ->assertSee(route('portal.tasks.approve', 'task-501'));
    }

    public function test_a_new_user_task_is_assigned_to_the_process_approver(): void
    {
        $requester = User::factory()->create(['role' => 'requester', 'is_active' => true]);
        $process = $this->runningInstance(User::factory()->create(['role' => 'approver', 'is_active' => true]))->processDefinition;
        $process->forceFill([
            'bpmn_xml' => '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"><bpmn:process id="Process_1"><bpmn:userTask id="Task_Approval" name="Aprovar solicitação"/></bpmn:process></bpmn:definitions>',
        ])->save();
        $process->authorizedUsers()->attach($requester->id, ['can_view' => true, 'can_start' => true, 'can_approve' => false]);
        $approver = $process->authorizedUsers()->wherePivot('can_approve', true)->firstOrFail();
        $assignee = PortalProcessController::flowableAssignee($approver->id);
        $this->configureFlowable();

        Http::fake(function (Request $request) use ($assignee) {
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/runtime/process-instances')) {
                return Http::response(['id' => 'flowable-new-instance'], 201);
            }

            if ($request->method() === 'GET' && str_contains($request->url(), 'runtime/tasks?processInstanceId=')) {
                return Http::response(['total' => 1, 'data' => [['id' => 'task-new', 'assignee' => null]]]);
            }

            if ($request->method() === 'GET' && str_contains($request->url(), 'runtime/process-instances?id=')) {
                return Http::response(['total' => 1, 'data' => [['id' => 'flowable-new-instance']]]);
            }

            return Http::response(['total' => 0, 'data' => []]);
        });

        $this->actingAs($requester)
            ->post(route('portal.processes.start.submit', $process), [
                'idempotency_key' => '123e4567-e89b-42d3-a456-426614174092',
                'reason' => 'Compra para a equipe',
            ])
            ->assertRedirect();

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && str_ends_with($request->url(), '/runtime/tasks/task-new')
            && $request['assignee'] === $assignee);
    }

    public function test_approver_can_complete_a_task_and_the_decision_is_preserved_for_the_requester(): void
    {
        $approver = User::factory()->create(['role' => 'approver', 'is_active' => true]);
        $requester = User::factory()->create(['role' => 'requester', 'is_active' => true]);
        $instance = $this->runningInstance($approver, $requester);
        $this->configureReturnRoutes($instance, 'exclusive');
        $this->configureFlowable();
        $assignee = PortalProcessController::flowableAssignee($approver->id);

        Http::fake(function (Request $request) use ($assignee, $instance) {
            if ($request->method() === 'GET' && str_ends_with($request->url(), '/runtime/tasks/task-501')) {
                return Http::response([
                    'id' => 'task-501',
                    'name' => 'Aprovar solicitação',
                    'assignee' => $assignee,
                    'processInstanceId' => $instance->flowable_process_instance_id,
                ]);
            }

            if ($request->method() === 'GET' && str_contains($request->url(), 'runtime/tasks?processInstanceId=')) {
                return Http::response(['total' => 0, 'data' => []]);
            }

            if ($request->method() === 'GET' && str_contains($request->url(), 'runtime/process-instances?id=')) {
                return Http::response(['total' => 0, 'data' => []]);
            }

            return Http::response([]);
        });

        $this->actingAs($approver)
            ->post(route('portal.tasks.approve', 'task-501'), [
                'decision' => 'approved',
                'return_to' => '',
                'justification' => 'Documentação conferida.',
            ])
            ->assertRedirect(route('portal.tasks.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('process_task_decisions', [
            'process_instance_id' => $instance->id,
            'flowable_task_id' => 'task-501',
            'decided_by' => $approver->id,
            'decision' => 'approved',
            'justification' => 'Documentação conferida.',
        ]);
        $this->assertSame('completed', $instance->fresh()->status);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/runtime/tasks/task-501')
            && $request['action'] === 'complete'
            && collect($request['variables'])->contains(fn (array $variable): bool => $variable['name'] === 'fc_decision' && $variable['value'] === 'approved')
            && collect($request['variables'])->contains(fn (array $variable): bool => $variable['name'] === 'fc_return_to' && $variable['value'] === ''));

        $this->actingAs($requester)
            ->get(route('portal.requests.show', $instance->uuid))
            ->assertOk()
            ->assertSee('Histórico de tarefas')
            ->assertSee('Documentação conferida.')
            ->assertSee($approver->name);
    }

    public function test_rejection_requires_at_least_one_return_target(): void
    {
        $approver = User::factory()->create(['role' => 'approver', 'is_active' => true]);
        $instance = $this->runningInstance($approver);
        $this->configureReturnRoutes($instance, 'exclusive');
        $this->configureFlowable();
        $assignee = PortalProcessController::flowableAssignee($approver->id);
        Http::fake(fn (Request $request) => str_ends_with($request->url(), '/runtime/tasks/task-501')
            ? Http::response([
                'id' => 'task-501',
                'name' => 'Aprovar solicitação',
                'taskDefinitionKey' => 'Task_Approval',
                'assignee' => $assignee,
                'processInstanceId' => $instance->flowable_process_instance_id,
            ])
            : Http::response([]));

        $this->actingAs($approver)
            ->from(route('portal.tasks.index'))
            ->post(route('portal.tasks.approve', 'task-501'), [
                'decision' => 'rejected',
                'return_to' => '',
                'justification' => 'Falta informação.',
            ])
            ->assertRedirect(route('portal.tasks.index'))
            ->assertSessionHasErrors('return_to');

        $this->assertDatabaseMissing('process_task_decisions', ['flowable_task_id' => 'task-501']);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/runtime/tasks/task-501'));
    }

    public function test_rejection_can_return_to_multiple_areas_for_an_inclusive_gateway(): void
    {
        $approver = User::factory()->create(['role' => 'approver', 'is_active' => true]);
        $instance = $this->runningInstance($approver);
        $this->configureReturnRoutes($instance, 'inclusive');
        $this->configureFlowable();
        $assignee = PortalProcessController::flowableAssignee($approver->id);
        Http::fake(function (Request $request) use ($assignee, $instance) {
            if ($request->method() === 'GET' && str_ends_with($request->url(), '/runtime/tasks/task-501')) {
                return Http::response([
                    'id' => 'task-501',
                    'name' => 'Aprovar solicitação',
                    'taskDefinitionKey' => 'Task_Approval',
                    'assignee' => $assignee,
                    'processInstanceId' => $instance->flowable_process_instance_id,
                ]);
            }

            if ($request->method() === 'GET' && str_contains($request->url(), 'runtime/tasks?processInstanceId=')) {
                return Http::response(['total' => 0, 'data' => []]);
            }

            if ($request->method() === 'GET' && str_contains($request->url(), 'runtime/process-instances?id=')) {
                return Http::response(['total' => 0, 'data' => []]);
            }

            return Http::response([]);
        });

        $this->actingAs($approver)
            ->post(route('portal.tasks.approve', 'task-501'), [
                'decision' => 'rejected',
                'return_to' => ['Task_Fiscal', 'Task_Compras'],
                'justification' => 'Ajustes necessários em mais de uma área.',
            ])
            ->assertRedirect(route('portal.tasks.index'))
            ->assertSessionHas('status');

        $decision = $instance->decisions()->where('flowable_task_id', 'task-501')->firstOrFail();
        $this->assertSame(['Task_Fiscal', 'Task_Compras'], $decision->form_data['return_to']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/runtime/tasks/task-501')
            && collect($request['variables'])->contains(fn (array $variable): bool => $variable['name'] === 'fc_return_to' && $variable['value'] === '|Task_Fiscal|Task_Compras|'));
    }

    public function test_work_task_can_submit_a_private_attachment_with_a_hidden_required_field(): void
    {
        Storage::fake('local');
        $approver = User::factory()->create(['role' => 'approver', 'is_active' => true]);
        $instance = $this->runningInstance($approver);
        $instance->processDefinition->forceFill([
            'task_form_schemas' => [
                'Task_Work' => [
                    'kind' => 'work',
                    'fields' => [
                        ['key' => 'category', 'label' => 'Categoria', 'type' => 'select', 'required' => true, 'options' => ['Produto', 'Outro']],
                        ['key' => 'tax_code', 'label' => 'NCM', 'type' => 'text', 'required' => true, 'visible_when' => ['field' => 'category', 'equals' => 'Produto']],
                        ['key' => 'document', 'label' => 'Documento', 'type' => 'file', 'required' => false],
                    ],
                ],
            ],
        ])->save();
        $this->configureFlowable();
        $assignee = PortalProcessController::flowableAssignee($approver->id);
        Http::fake(function (Request $request) use ($assignee, $instance) {
            if ($request->method() === 'GET' && str_ends_with($request->url(), '/runtime/tasks/task-work-501')) {
                return Http::response([
                    'id' => 'task-work-501',
                    'name' => 'Cadastro no sistema externo',
                    'taskDefinitionKey' => 'Task_Work',
                    'assignee' => $assignee,
                    'processInstanceId' => $instance->flowable_process_instance_id,
                    'createTime' => now()->subMinutes(35)->toIso8601String(),
                ]);
            }
            if ($request->method() === 'GET' && str_contains($request->url(), 'runtime/tasks?processInstanceId=')) {
                return Http::response(['total' => 0, 'data' => []]);
            }
            if ($request->method() === 'GET' && str_contains($request->url(), 'runtime/process-instances?id=')) {
                return Http::response(['total' => 1, 'data' => [['id' => $instance->flowable_process_instance_id]]]);
            }
            return Http::response([]);
        });

        $this->actingAs($approver)
            ->post(route('portal.tasks.approve', 'task-work-501'), [
                'task_data' => [
                    'category' => 'Outro',
                    'document' => UploadedFile::fake()->create('comprovante.pdf', 32, 'application/pdf'),
                ],
            ])
            ->assertRedirect(route('portal.tasks.index'))
            ->assertSessionHas('status');

        $decision = $instance->decisions()->where('flowable_task_id', 'task-work-501')->firstOrFail();
        $attachment = $decision->form_data['document'];
        $this->assertArrayNotHasKey('tax_code', $decision->form_data);
        Storage::disk('local')->assertExists($attachment['path']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/runtime/tasks/task-work-501')
            && ! collect($request['variables'])->contains(fn (array $variable): bool => str_contains($variable['name'], 'document')));
    }

    public function test_task_completion_requires_a_justification(): void
    {
        $approver = User::factory()->create(['role' => 'approver', 'is_active' => true]);
        $instance = $this->runningInstance($approver);
        $this->configureFlowable();
        $assignee = PortalProcessController::flowableAssignee($approver->id);
        Http::fake(fn (Request $request) => str_ends_with($request->url(), '/runtime/tasks/task-501')
            ? Http::response([
                'id' => 'task-501',
                'name' => 'Aprovar solicitação',
                'assignee' => $assignee,
                'processInstanceId' => $instance->flowable_process_instance_id,
            ])
            : Http::response([]));

        $this->actingAs($approver)
            ->from(route('portal.tasks.index'))
            ->post(route('portal.tasks.approve', 'task-501'), [])
            ->assertSessionHasErrors('justification');
    }

    private function runningInstance(User $approver, ?User $requester = null): ProcessInstance
    {
        $master = User::factory()->create(['role' => 'master', 'is_active' => true]);
        $requester ??= User::factory()->create(['role' => 'requester', 'is_active' => true]);
        $process = ProcessDefinition::query()->create([
            'name' => 'Compra de equipamentos',
            'status' => 'published',
            'is_active' => true,
            'form_schema' => [['key' => 'reason', 'label' => 'Justificativa', 'type' => 'text', 'required' => true]],
            'flowable_process_definition_id' => 'flowcore_process_31:1:deployment',
            'published_bpmn_hash' => hash('sha256', 'task-test'),
            'published_at' => now(),
            'created_by' => $master->id,
            'updated_by' => $master->id,
        ]);
        $process->authorizedUsers()->attach($approver->id, ['can_view' => true, 'can_start' => false, 'can_approve' => true]);

        return ProcessInstance::query()->create([
            'uuid' => '123e4567-e89b-42d3-a456-426614174090',
            'idempotency_key' => '123e4567-e89b-42d3-a456-426614174091',
            'process_definition_id' => $process->id,
            'started_by' => $requester->id,
            'flowable_process_instance_id' => 'flowable-instance-501',
            'status' => 'running',
            'form_data' => ['reason' => 'Compra para a equipe'],
        ]);
    }

    private function configureFlowable(): void
    {
        config([
            'services.flowable.url' => 'http://127.0.0.1:8091/process-api',
            'services.flowable.username' => 'test-user',
            'services.flowable.password' => 'test-password',
        ]);
    }

    private function configureReturnRoutes(ProcessInstance $instance, string $gatewayType): void
    {
        $gateway = $gatewayType === 'inclusive' ? 'inclusiveGateway' : 'exclusiveGateway';
        $condition = $gatewayType === 'inclusive'
            ? '<bpmn:conditionExpression>${fc_return_to.contains(\'|Task_Fiscal|\')}</bpmn:conditionExpression>'
            : '';
        $instance->processDefinition->forceFill([
            'bpmn_xml' => '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"><bpmn:process id="Process_1">'
                .'<bpmn:userTask id="Task_Approval" name="Aprovar solicitação"/>'
                .'<bpmn:userTask id="Task_Fiscal" name="Correção Fiscal"/>'
                .'<bpmn:userTask id="Task_Compras" name="Correção de Compras"/>'
                ."<bpmn:{$gateway} id=\"Gateway_Diretoria\" name=\"Diretoria\"/>"
                .'<bpmn:sequenceFlow id="Flow_Fiscal" sourceRef="Gateway_Diretoria" targetRef="Task_Fiscal">'.$condition.'</bpmn:sequenceFlow>'
                .'<bpmn:sequenceFlow id="Flow_Compras" sourceRef="Gateway_Diretoria" targetRef="Task_Compras"/>'
                .'<bpmn:sequenceFlow id="Flow_Approved" sourceRef="Gateway_Diretoria" targetRef="End_Approved"/>'
                .'<bpmn:endEvent id="End_Approved" name="Concluído"/>'
                .'</bpmn:process></bpmn:definitions>',
            'task_form_schemas' => [
                'Task_Approval' => ['kind' => 'approval', 'fields' => []],
                'Task_Fiscal' => ['kind' => 'work', 'fields' => []],
                'Task_Compras' => ['kind' => 'work', 'fields' => []],
            ],
            'gateway_routes' => [
                'Gateway_Diretoria' => [
                    'Flow_Fiscal' => 'return:Task_Fiscal',
                    'Flow_Compras' => 'return:Task_Compras',
                    'Flow_Approved' => 'approved',
                ],
            ],
        ])->save();
    }
}
