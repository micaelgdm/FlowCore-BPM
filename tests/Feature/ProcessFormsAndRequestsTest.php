<?php

namespace Tests\Feature;

use App\Models\ProcessDefinition;
use App\Models\ProcessInstance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessFormsAndRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_can_define_a_form_for_a_draft_process(): void
    {
        $master = User::factory()->create(['role' => 'master', 'is_active' => true]);
        $process = $this->draftProcess($master);

        $this->actingAs($master)
            ->put(route('master.processes.form.update', $process), [
                'fields' => [
                    ['key' => 'justificativa', 'label' => 'Justificativa', 'type' => 'textarea', 'required' => '1'],
                    ['key' => 'area', 'label' => 'Área', 'type' => 'select', 'options' => "Financeiro\nOperações"],
                ],
            ])
            ->assertRedirect(route('master.processes.form.edit', $process))
            ->assertSessionHas('status');

        $this->assertSame([
            ['key' => 'justificativa', 'label' => 'Justificativa', 'type' => 'textarea', 'required' => true],
            ['key' => 'area', 'label' => 'Área', 'type' => 'select', 'required' => false, 'options' => ['Financeiro', 'Operações']],
        ], $process->fresh()->form_schema);
    }

    public function test_form_schema_supports_private_attachments_and_conditional_fields(): void
    {
        $master = User::factory()->create(['role' => 'master', 'is_active' => true]);
        $process = $this->draftProcess($master);

        $this->actingAs($master)
            ->put(route('master.processes.form.update', $process), [
                'fields' => [
                    ['key' => 'item_type', 'label' => 'Tipo do produto', 'type' => 'select', 'options' => "Revenda\nFabricado"],
                    ['key' => 'tax_code', 'label' => 'NCM', 'type' => 'text', 'required' => '1', 'visible_when_field' => 'item_type', 'visible_when_value' => 'Revenda'],
                    ['key' => 'spec_sheet', 'label' => 'Ficha técnica', 'type' => 'file'],
                ],
            ])
            ->assertRedirect(route('master.processes.form.edit', $process))
            ->assertSessionHasNoErrors();

        $this->assertSame([
            ['key' => 'item_type', 'label' => 'Tipo do produto', 'type' => 'select', 'required' => false, 'options' => ['Revenda', 'Fabricado']],
            ['key' => 'tax_code', 'label' => 'NCM', 'type' => 'text', 'required' => true, 'visible_when' => ['field' => 'item_type', 'equals' => 'Revenda']],
            ['key' => 'spec_sheet', 'label' => 'Ficha técnica', 'type' => 'file', 'required' => false],
        ], $process->fresh()->form_schema);
    }

    public function test_requester_can_submit_an_attachment_and_download_it_privately(): void
    {
        Storage::fake('local');
        $requester = User::factory()->create(['role' => 'requester', 'is_active' => true]);
        $process = $this->publishedProcess(User::factory()->create(['role' => 'master', 'is_active' => true]));
        $process->forceFill([
            'is_active' => true,
            'form_schema' => [
                ['key' => 'category', 'label' => 'Categoria', 'type' => 'select', 'required' => true, 'options' => ['Produto', 'Outro']],
                ['key' => 'tax_code', 'label' => 'NCM', 'type' => 'text', 'required' => true, 'visible_when' => ['field' => 'category', 'equals' => 'Produto']],
                ['key' => 'spec_sheet', 'label' => 'Ficha técnica', 'type' => 'file', 'required' => true],
            ],
        ])->save();
        $process->authorizedUsers()->attach($requester->id, ['can_view' => true, 'can_start' => true, 'can_approve' => false]);
        $this->configureFlowable();
        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && str_contains($request->url(), 'runtime/process-instances?businessKey=')) {
                return Http::response(['total' => 0, 'data' => []]);
            }
            if ($request->method() === 'GET' && str_contains($request->url(), 'history/historic-process-instances?businessKey=')) {
                return Http::response(['total' => 0, 'data' => []]);
            }
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/runtime/process-instances')) {
                return Http::response(['id' => 'flowable-attachment-instance'], 201);
            }
            if ($request->method() === 'GET' && str_contains($request->url(), 'runtime/process-instances?id=')) {
                return Http::response(['total' => 1, 'data' => [['id' => 'flowable-attachment-instance']]]);
            }
            return Http::response(['total' => 0, 'data' => []]);
        });

        $this->actingAs($requester)
            ->post(route('portal.processes.start.submit', $process), [
                'idempotency_key' => '123e4567-e89b-42d3-a456-426614174099',
                'category' => 'Outro',
                'spec_sheet' => UploadedFile::fake()->create('ficha.pdf', 40, 'application/pdf'),
            ])
            ->assertRedirect();

        $instance = ProcessInstance::query()->firstOrFail();
        $attachment = $instance->form_data['spec_sheet'];
        $this->assertSame('ficha.pdf', $attachment['name']);
        Storage::disk('local')->assertExists($attachment['path']);
        $this->assertArrayNotHasKey('tax_code', $instance->form_data);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/runtime/process-instances')
            && ! collect($request['variables'])->contains(fn (array $variable): bool => $variable['name'] === 'spec_sheet'));

        $this->get(route('portal.attachments.download', [$instance->uuid, $attachment['attachment_id']]))
            ->assertOk()
            ->assertHeader('content-disposition');

        $otherUser = User::factory()->create(['role' => 'requester', 'is_active' => true]);
        $this->actingAs($otherUser)
            ->get(route('portal.attachments.download', [$instance->uuid, $attachment['attachment_id']]))
            ->assertForbidden();
    }

    public function test_form_builder_rejects_duplicate_keys_and_selects_without_options(): void
    {
        $master = User::factory()->create(['role' => 'master', 'is_active' => true]);
        $process = $this->draftProcess($master);

        $this->actingAs($master)
            ->from(route('master.processes.form.edit', $process))
            ->put(route('master.processes.form.update', $process), [
                'fields' => [
                    ['key' => 'area', 'label' => 'Área 1', 'type' => 'select', 'options' => ''],
                    ['key' => 'area', 'label' => 'Área 2', 'type' => 'text'],
                ],
            ])
            ->assertSessionHasErrors(['fields.0.options', 'fields.0.key', 'fields.1.key']);
    }

    public function test_published_form_is_read_only(): void
    {
        $master = User::factory()->create(['role' => 'master', 'is_active' => true]);
        $process = $this->publishedProcess($master);

        $this->actingAs($master)
            ->get(route('master.processes.form.edit', $process))
            ->assertStatus(409);

        $this->put(route('master.processes.form.update', $process), [
            'fields' => [['key' => 'reason', 'label' => 'Motivo', 'type' => 'text']],
        ])->assertStatus(409);
    }

    public function test_master_and_authorized_user_can_open_their_respective_form_pages(): void
    {
        $master = User::factory()->create(['role' => 'master', 'is_active' => true]);
        $requester = User::factory()->create(['role' => 'requester', 'is_active' => true]);
        $draft = $this->draftProcess($master);
        $published = $this->publishedProcess($master);
        $published->authorizedUsers()->attach($requester->id);

        $this->actingAs($master)
            ->get(route('master.processes.form.edit', $draft))
            ->assertOk()
            ->assertSee('Formulário da solicitação')
            ->assertSee('Identificador (sem espaços)');

        $this->actingAs($requester)
            ->get(route('portal.processes.start', $published))
            ->assertOk()
            ->assertSee('Enviar solicitação')
            ->assertSee('Justificativa');
    }

    public function test_user_can_start_an_authorized_process_and_view_their_submission(): void
    {
        $requester = User::factory()->create(['role' => 'requester', 'is_active' => true]);
        $process = $this->publishedProcess(User::factory()->create(['role' => 'master', 'is_active' => true]));
        $process->authorizedUsers()->attach($requester->id);
        $this->configureFlowable();

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'runtime/process-instances?id=')) {
                return Http::response(['total' => 1, 'data' => [['id' => 'flowable-instance-1']]]);
            }

            if ($request->method() === 'GET') {
                return Http::response(['total' => 0, 'data' => []]);
            }

            return Http::response(['id' => 'flowable-instance-1'], 201);
        });

        $key = '123e4567-e89b-42d3-a456-426614174000';
        $response = $this->actingAs($requester)
            ->post(route('portal.processes.start.submit', $process), [
                'idempotency_key' => $key,
                'reason' => 'Compra para a equipe',
                'department' => 'Operações',
                'amount' => '125.50',
                'unrecognized' => 'ignored',
            ]);

        $instance = ProcessInstance::query()->firstOrFail();
        $response->assertRedirect(route('portal.requests.show', $instance->uuid));
        $this->assertSame('running', $instance->status);
        $this->assertSame('flowable-instance-1', $instance->flowable_process_instance_id);
        $this->assertSame([
            'reason' => 'Compra para a equipe',
            'department' => 'Operações',
            'amount' => 125.5,
        ], $instance->form_data);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/runtime/process-instances')
            && $request['processDefinitionId'] === $process->flowable_process_definition_id
            && $request['businessKey'] === $key
            && collect($request['variables'])->contains(fn (array $variable): bool => $variable['name'] === 'amount' && $variable['type'] === 'double' && $variable['value'] === 125.5));

        $this->actingAs($requester)
            ->get(route('portal.requests.show', $instance->uuid))
            ->assertOk()
            ->assertSee('Detalhes da solicitação')
            ->assertSee('Compra para a equipe');
    }

    public function test_repeating_a_successful_submission_with_the_same_key_does_not_start_another_instance(): void
    {
        $requester = User::factory()->create(['role' => 'requester', 'is_active' => true]);
        $process = $this->publishedProcess(User::factory()->create(['role' => 'master', 'is_active' => true]));
        $process->authorizedUsers()->attach($requester->id);
        $this->configureFlowable();
        Http::fake(fn (Request $request) => str_contains($request->url(), 'runtime/process-instances?id=')
            ? Http::response(['total' => 1, 'data' => [['id' => 'flowable-instance-2']]])
            : ($request->method() === 'GET'
                ? Http::response(['total' => 0, 'data' => []])
                : Http::response(['id' => 'flowable-instance-2'], 201)));

        $data = [
            'idempotency_key' => '123e4567-e89b-42d3-a456-426614174001',
            'reason' => 'Compra para a equipe',
            'department' => 'Operações',
            'amount' => '125.50',
        ];

        $this->actingAs($requester)->post(route('portal.processes.start.submit', $process), $data)->assertRedirect();
        $this->post(route('portal.processes.start.submit', $process), $data)->assertRedirect();

        $this->assertDatabaseCount('process_instances', 1);
        $this->assertCount(5, Http::recorded());
    }

    public function test_unassigned_user_cannot_open_or_start_another_users_process(): void
    {
        $requester = User::factory()->create(['role' => 'requester', 'is_active' => true]);
        $process = $this->publishedProcess(User::factory()->create(['role' => 'master', 'is_active' => true]));

        $this->actingAs($requester)
            ->get(route('portal.processes.start', $process))
            ->assertNotFound();
    }

    public function test_form_submission_is_validated_on_the_server(): void
    {
        $requester = User::factory()->create(['role' => 'requester', 'is_active' => true]);
        $process = $this->publishedProcess(User::factory()->create(['role' => 'master', 'is_active' => true]));
        $process->authorizedUsers()->attach($requester->id);

        $this->actingAs($requester)
            ->from(route('portal.processes.start', $process))
            ->post(route('portal.processes.start.submit', $process), [
                'idempotency_key' => '123e4567-e89b-42d3-a456-426614174002',
                'reason' => '',
                'department' => 'Diretoria',
                'amount' => 'não é um número',
            ])
            ->assertSessionHasErrors(['reason', 'department', 'amount']);

        $this->assertDatabaseCount('process_instances', 0);
    }

    public function test_flowable_failure_is_saved_without_exposing_engine_details(): void
    {
        $requester = User::factory()->create(['role' => 'requester', 'is_active' => true]);
        $process = $this->publishedProcess(User::factory()->create(['role' => 'master', 'is_active' => true]));
        $process->authorizedUsers()->attach($requester->id);
        $this->configureFlowable();

        Http::fake(fn (Request $request) => str_contains($request->url(), 'runtime/process-instances?id=')
            ? Http::response(['total' => 1, 'data' => [['id' => 'flowable-instance-3']]])
            : ($request->method() === 'GET'
                ? Http::response(['total' => 0, 'data' => []])
                : Http::response(['exception' => 'internal database host detail'], 503)));

        $this->actingAs($requester)
            ->from(route('portal.processes.start', $process))
            ->post(route('portal.processes.start.submit', $process), [
                'idempotency_key' => '123e4567-e89b-42d3-a456-426614174003',
                'reason' => 'Solicitação de teste',
                'department' => 'Operações',
                'amount' => '25',
            ])
            ->assertRedirect(route('portal.processes.start', $process))
            ->assertSessionHasErrors('flowable')
            ->assertDontSee('internal database host detail');

        $this->assertDatabaseHas('process_instances', [
            'status' => 'failed',
            'flowable_process_instance_id' => null,
        ]);
    }

    private function draftProcess(User $owner): ProcessDefinition
    {
        return ProcessDefinition::query()->create([
            'name' => 'Reembolso de despesas',
            'status' => 'draft',
            'bpmn_xml' => '<bpmn:definitions/>',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
    }

    private function publishedProcess(User $owner): ProcessDefinition
    {
        $process = ProcessDefinition::query()->create([
            'name' => 'Reembolso de despesas',
            'status' => 'published',
            'bpmn_xml' => '<bpmn:definitions/>',
            'flowable_process_definition_id' => 'flowcore_process_1:1:deployment',
            'published_bpmn_hash' => hash('sha256', 'published'),
            'published_at' => now(),
            'form_schema' => [
                ['key' => 'reason', 'label' => 'Justificativa', 'type' => 'text', 'required' => true],
                ['key' => 'department', 'label' => 'Área', 'type' => 'select', 'required' => true, 'options' => ['Financeiro', 'Operações']],
                ['key' => 'amount', 'label' => 'Valor', 'type' => 'number', 'required' => false],
            ],
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $approver = User::factory()->create(['role' => 'approver', 'is_active' => true]);
        $process->authorizedUsers()->attach($approver->id, ['can_view' => true, 'can_start' => false, 'can_approve' => true]);

        return $process;
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
