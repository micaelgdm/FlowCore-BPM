<?php

namespace Tests\Feature;

use App\Models\ProcessDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
