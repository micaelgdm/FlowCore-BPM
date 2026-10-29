<?php

namespace Tests\Feature;

use App\Models\ProcessDefinition;
use App\Models\ProcessGroup;
use App\Models\ProcessInstance;
use App\Models\ProcessTaskDecision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProcessDefinitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_can_create_and_save_a_bpmn_draft(): void
    {
        $user = User::factory()->create(['role' => 'master', 'is_active' => true]);
        $this->actingAs($user);

        $this->get(route('master.processes.index'))
            ->assertOk()
            ->assertSee('Novo processo');

        $this->post(route('master.processes.store'), ['name' => 'Aprovação de compras'])
            ->assertRedirect();

        $process = ProcessDefinition::query()->firstOrFail();
        $xml = '<?xml version="1.0"?><bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"><bpmn:process id="Process_1"/></bpmn:definitions>';

        $this->putJson(route('master.processes.diagram.update', $process), ['bpmn_xml' => $xml])
            ->assertOk()
            ->assertJsonPath('message', 'Rascunho salvo.');

        $this->assertDatabaseHas('process_definitions', [
            'id' => $process->id,
            'name' => 'Aprovação de compras',
            'bpmn_xml' => $xml,
            'status' => 'draft',
        ]);

        $this->get(route('master.processes.editor', $process))
            ->assertOk()
            ->assertSee('Aprovação de compras')
            ->assertSee('bpmn:definitions')
            ->assertSee('Publicar versão');
    }

    public function test_master_dashboard_shows_stage_duration_and_overdue_group_tasks(): void
    {
        $master = User::factory()->create(['role' => 'master', 'is_active' => true]);
        $group = ProcessGroup::query()->create([
            'name' => 'Fiscal',
            'flowable_group_id' => 'fiscal',
        ]);
        $process = ProcessDefinition::query()->create([
            'name' => 'Cadastro de Produto',
            'status' => 'published',
            'is_active' => true,
            'version' => 1,
            'task_group_assignments' => ['Task_Fiscal' => $group->id],
            'task_form_schemas' => ['Task_Fiscal' => ['kind' => 'work', 'fields' => [], 'sla_hours' => 1]],
            'created_by' => $master->id,
            'updated_by' => $master->id,
        ]);
        $instance = ProcessInstance::query()->create([
            'uuid' => '123e4567-e89b-42d3-a456-426614174099',
            'idempotency_key' => '123e4567-e89b-42d3-a456-426614174098',
            'process_definition_id' => $process->id,
            'started_by' => $master->id,
            'flowable_process_instance_id' => 'flowable-metrics-instance',
            'status' => 'running',
            'form_data' => [],
        ]);
        ProcessTaskDecision::query()->create([
            'process_instance_id' => $instance->id,
            'flowable_task_id' => 'flowable-completed-metrics-task',
            'task_name' => 'Análise Fiscal',
            'task_definition_key' => 'Task_Fiscal',
            'decided_by' => $master->id,
            'decision' => 'completed',
            'justification' => '',
            'form_data' => [],
            'flowable_started_at' => now()->subHours(2),
            'flowable_completed_at' => now()->subHour(),
        ]);
        config([
            'services.flowable.url' => 'http://127.0.0.1:8091/process-api',
            'services.flowable.username' => 'test-user',
            'services.flowable.password' => 'test-password',
        ]);
        Http::fake(fn (Request $request) => Http::response([
            'total' => 1,
            'data' => [[
                'id' => 'flowable-pending-fiscal-task',
                'name' => 'Análise Fiscal',
                'taskDefinitionKey' => 'Task_Fiscal',
                'processInstanceId' => 'flowable-metrics-instance',
                'createTime' => now()->subHours(2)->toIso8601String(),
            ]],
        ]));

        $this->actingAs($master)
            ->get(route('master.processes.index'))
            ->assertOk()
            ->assertSee('Tempo médio por etapa')
            ->assertSee('1 h 0 min')
            ->assertSee('Análise Fiscal')
            ->assertSee('Pendências por grupo')
            ->assertSee('Fiscal')
            ->assertSee('Cadastro de Produto');
    }

    public function test_task_form_schemas_support_conditionals_and_attachment_fields(): void
    {
        $master = User::factory()->create(['role' => 'master', 'is_active' => true]);
        $group = ProcessGroup::query()->create(['name' => 'Fiscal', 'flowable_group_id' => 'fiscal']);
        $process = ProcessDefinition::query()->create([
            'name' => 'Cadastro de Produto',
            'status' => 'draft',
            'bpmn_xml' => '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"><bpmn:process id="Process_1"><bpmn:userTask id="Task_Work" name="Análise Fiscal"/></bpmn:process></bpmn:definitions>',
            'created_by' => $master->id,
            'updated_by' => $master->id,
        ]);

        $this->actingAs($master)
            ->put(route('master.processes.tasks.update', $process), [
                'assignments' => [['task_id' => 'Task_Work', 'type' => 'group', 'group_id' => $group->id]],
                'task_forms' => [[
                    'task_id' => 'Task_Work',
                    'kind' => 'work',
                    'show_process_data' => '1',
                    'fields' => [
                        ['key' => 'product_type', 'label' => 'Tipo do produto', 'type' => 'select', 'options' => "Revenda\nFabricado"],
                        ['key' => 'ncm', 'label' => 'NCM', 'type' => 'text', 'required' => '1', 'visible_when_field' => 'product_type', 'visible_when_value' => 'Revenda'],
                        ['key' => 'invoice', 'label' => 'Nota fiscal', 'type' => 'file'],
                    ],
                ]],
            ])
            ->assertRedirect(route('master.processes.tasks.edit', $process))
            ->assertSessionHasNoErrors();

        $this->assertSame([
            'kind' => 'work',
            'show_process_data' => true,
            'fields' => [
                ['key' => 'product_type', 'label' => 'Tipo do produto', 'type' => 'select', 'required' => false, 'options' => ['Revenda', 'Fabricado']],
                ['key' => 'ncm', 'label' => 'NCM', 'type' => 'text', 'required' => true, 'visible_when' => ['field' => 'product_type', 'equals' => 'Revenda']],
                ['key' => 'invoice', 'label' => 'Nota fiscal', 'type' => 'file', 'required' => false],
            ],
        ], $process->fresh()->task_form_schemas['Task_Work']);
    }

    public function test_bpmn_save_rejects_xml_with_an_external_entity_declaration(): void
    {
        $user = User::factory()->create(['role' => 'master', 'is_active' => true]);
        $process = ProcessDefinition::query()->create([
            'name' => 'Diagrama de teste',
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->putJson(route('master.processes.diagram.update', $process), [
                'bpmn_xml' => '<!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///windows/win.ini">]><bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"><bpmn:process id="Process_1"/></bpmn:definitions>',
            ])
            ->assertUnprocessable();
    }
}
