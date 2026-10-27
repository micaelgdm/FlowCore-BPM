<?php

namespace Tests\Feature;

use App\Http\Controllers\PortalProcessController;
use App\Models\ProcessDefinition;
use App\Models\ProcessInstance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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
            ->post(route('portal.tasks.approve', 'task-501'), ['justification' => 'Documentação conferida.'])
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
            && collect($request['variables'])->contains(fn (array $variable): bool => $variable['name'] === 'fc_decision' && $variable['value'] === 'approved'));

        $this->actingAs($requester)
            ->get(route('portal.requests.show', $instance->uuid))
            ->assertOk()
            ->assertSee('Histórico de aprovações')
            ->assertSee('Documentação conferida.')
            ->assertSee($approver->name);
    }

    public function test_task_completion_requires_a_justification(): void
    {
        $approver = User::factory()->create(['role' => 'approver', 'is_active' => true]);
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
}
