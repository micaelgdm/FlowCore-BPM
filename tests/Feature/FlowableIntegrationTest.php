<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use App\Models\ProcessDefinition;
use Tests\TestCase;

class FlowableIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_can_list_process_definitions_from_flowable(): void
    {
        config([
            'services.flowable.url' => 'http://127.0.0.1:8091/process-api',
            'services.flowable.username' => 'test-user',
            'services.flowable.password' => 'test-password',
        ]);

        Http::fake([
            '127.0.0.1:8091/process-api/repository/process-definitions' => Http::response([
                'total' => 1,
                'data' => [[
                    'id' => 'flowcoreApprovalDemo:1:deployment-id',
                    'key' => 'flowcoreApprovalDemo',
                    'name' => 'Aprovação de solicitação',
                    'version' => 1,
                    'deploymentId' => 'deployment-id',
                    'tenantId' => 'internal-value-not-exposed',
                ]],
            ]),
        ]);

        $user = User::factory()->create(['role' => 'master', 'is_active' => true]);

        $this->actingAs($user)
            ->getJson(route('master.engine.process-definitions'))
            ->assertOk()
            ->assertExactJson([
                'total' => 1,
                'data' => [[
                    'id' => 'flowcoreApprovalDemo:1:deployment-id',
                    'key' => 'flowcoreApprovalDemo',
                    'name' => 'Aprovação de solicitação',
                    'version' => 1,
                    'deploymentId' => 'deployment-id',
                ]],
            ]);

        Http::assertSent(fn ($request): bool => $request->hasHeader(
            'Authorization',
            'Basic '.base64_encode('test-user:test-password'),
        ));
    }

    public function test_non_master_cannot_query_flowable(): void
    {
        $user = User::factory()->create(['role' => 'user', 'is_active' => true]);

        $this->actingAs($user)
            ->getJson(route('master.engine.process-definitions'))
            ->assertForbidden();
    }

    public function test_master_gets_a_service_unavailable_response_when_flowable_is_unreachable(): void
    {
        config([
            'services.flowable.username' => 'test-user',
            'services.flowable.password' => 'test-password',
        ]);

        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $user = User::factory()->create(['role' => 'master', 'is_active' => true]);

        $this->actingAs($user)
            ->getJson(route('master.engine.process-definitions'))
            ->assertServiceUnavailable()
            ->assertJson(['message' => 'O motor de processos está indisponível no momento.']);
    }

    public function test_master_can_publish_a_draft_and_repeated_request_does_not_deploy_twice(): void
    {
        config([
            'services.flowable.url' => 'http://127.0.0.1:8091/process-api',
            'services.flowable.username' => 'test-user',
            'services.flowable.password' => 'test-password',
        ]);

        $user = User::factory()->create(['role' => 'master', 'is_active' => true]);
        $process = ProcessDefinition::query()->create([
            'name' => 'Solicitação de acesso',
            'status' => 'draft',
            'bpmn_xml' => $this->publishableBpmn(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Http::fakeSequence()
            ->push(['total' => 0, 'data' => []], 200)
            ->push(['id' => 'deployment-42', 'name' => "flowcore-process-{$process->id}-v1"], 201)
            ->push(['total' => 1, 'data' => [['id' => "flowcore_process_{$process->id}:1:42"]]], 200);

        $route = route('master.processes.publish', $process);
        $publish = fn () => $this->actingAs($user)->postJson($route);

        $publish()->assertCreated()
            ->assertJsonPath('status', 'published')
            ->assertJsonPath('deployment_id', 'deployment-42');

        $published = $process->fresh();
        $this->assertSame('published', $published->status);
        $this->assertSame('deployment-42', $published->flowable_deployment_id);
        $this->assertSame("flowcore_process_{$process->id}:1:42", $published->flowable_process_definition_id);
        $this->assertNotNull($published->published_at);
        $this->assertSame(hash('sha256', $published->bpmn_xml), $published->published_bpmn_hash);
        $this->assertStringContainsString("id=\"flowcore_process_{$process->id}\"", $published->bpmn_xml);
        $this->assertStringContainsString('isExecutable="true"', $published->bpmn_xml);
        $this->assertStringContainsString('targetNamespace="https://flowcore.local/bpmn"', $published->bpmn_xml);

        $publish()->assertOk()->assertJsonPath('message', 'Esta versão já está publicada.');

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/repository/deployments')
            && str_contains($request->body(), "flowcore-process-{$process->id}-v1.bpmn20.xml")
            && str_contains($request->body(), "flowcore_process_{$process->id}"));
    }

    public function test_a_published_process_cannot_be_modified_as_a_draft(): void
    {
        $user = User::factory()->create(['role' => 'master', 'is_active' => true]);
        $process = ProcessDefinition::query()->create([
            'name' => 'Versão publicada',
            'status' => 'published',
            'bpmn_xml' => $this->publishableBpmn(),
            'flowable_deployment_id' => 'deployment-locked',
            'flowable_process_definition_id' => 'process-locked',
            'published_bpmn_hash' => hash('sha256', $this->publishableBpmn()),
            'published_at' => now(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->putJson(route('master.processes.diagram.update', $process), ['bpmn_xml' => $this->publishableBpmn()])
            ->assertStatus(409);

        $this->get(route('master.processes.editor', $process))
            ->assertOk()
            ->assertSee('PUBLICADO')
            ->assertSee('Publicado em')
            ->assertDontSee('Publicar versão')
            ->assertDontSee('Salvar rascunho');
    }

    public function test_deploy_retry_recovers_a_matching_deployment_already_created_by_flowable(): void
    {
        config([
            'services.flowable.url' => 'http://127.0.0.1:8091/process-api',
            'services.flowable.username' => 'test-user',
            'services.flowable.password' => 'test-password',
        ]);

        $xml = $this->publishableBpmn();
        $filename = 'flowcore-process-41-v1.bpmn20.xml';
        Http::fakeSequence()
            ->push(['total' => 1, 'data' => [['id' => 'deployment-recovered']]], 200)
            ->push([['id' => $filename]], 200)
            ->push($xml, 200, ['Content-Type' => 'text/xml'])
            ->push(['total' => 1, 'data' => [['id' => 'flowcore_process_41:1:recovered']]], 200);

        $deployment = app(\App\Services\FlowableClient::class)->deployBpmn('flowcore-process-41-v1', $xml);

        $this->assertSame('deployment-recovered', $deployment['deployment_id']);
        $this->assertSame('flowcore_process_41:1:recovered', $deployment['process_definition_id']);
        Http::assertSentCount(4);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    private function publishableBpmn(): string
    {
        return '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"><bpmn:process id="Process_1" isExecutable="false"><bpmn:startEvent id="Start"/><bpmn:endEvent id="End"/><bpmn:sequenceFlow id="Flow" sourceRef="Start" targetRef="End"/></bpmn:process></bpmn:definitions>';
    }
}
