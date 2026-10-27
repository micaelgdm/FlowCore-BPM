<?php

namespace Tests\Feature;

use App\Models\ProcessDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserAndProcessAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_can_create_a_user_with_a_limited_role(): void
    {
        $master = User::factory()->create(['role' => 'master', 'is_active' => true]);

        $this->actingAs($master)
            ->post(route('master.users.store'), [
                'name' => 'Ana Solicitante',
                'email' => 'ana@example.test',
                'role' => 'requester',
                'password' => 'initial-password-123',
                'password_confirmation' => 'initial-password-123',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $user = User::query()->where('email', 'ana@example.test')->firstOrFail();
        $this->assertSame('requester', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('initial-password-123', $user->password));
    }

    public function test_non_master_cannot_manage_users_or_process_permissions(): void
    {
        $requester = User::factory()->create(['role' => 'requester', 'is_active' => true]);
        $process = $this->publishedProcess($requester);

        $this->actingAs($requester)
            ->get(route('master.users.index'))
            ->assertForbidden();

        $this->get(route('master.processes.access.edit', $process))
            ->assertForbidden();
    }

    public function test_user_portal_lists_only_published_processes_granted_to_that_user(): void
    {
        $requester = User::factory()->create(['role' => 'requester', 'is_active' => true]);
        $authorized = $this->publishedProcess($requester, 'Viagem corporativa');
        $authorized->authorizedUsers()->attach($requester->id);
        $this->publishedProcess(User::factory()->create(['role' => 'consultant', 'is_active' => true]), 'Acesso a sistemas');

        $this->actingAs($requester)
            ->get(route('portal.processes.index'))
            ->assertOk()
            ->assertSee('Viagem corporativa')
            ->assertDontSee('Acesso a sistemas')
            ->assertDontSee(route('master.processes.editor', $authorized));
    }

    public function test_master_can_grant_published_process_access_to_users(): void
    {
        $master = User::factory()->create(['role' => 'master', 'is_active' => true]);
        $requester = User::factory()->create(['role' => 'requester', 'is_active' => true]);
        $process = $this->publishedProcess($master, 'Reembolso');

        $this->actingAs($master)
            ->get(route('master.processes.access.edit', $process))
            ->assertOk()
            ->assertSee('Quem pode ver, solicitar e aprovar?')
            ->assertSee($requester->email);

        $this->put(route('master.processes.access.update', $process), [
            'permissions' => [
                $requester->id => ['can_view' => '1', 'can_start' => '1', 'can_approve' => '0'],
            ],
        ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('process_user_access', [
            'process_definition_id' => $process->id,
            'user_id' => $requester->id,
            'can_view' => 1,
            'can_start' => 1,
            'can_approve' => 0,
        ]);
    }

    public function test_master_cannot_grant_approval_to_a_requester_profile(): void
    {
        $master = User::factory()->create(['role' => 'master', 'is_active' => true]);
        $requester = User::factory()->create(['role' => 'requester', 'is_active' => true]);
        $process = $this->publishedProcess($master, 'Reembolso');

        $this->actingAs($master)
            ->from(route('master.processes.access.edit', $process))
            ->put(route('master.processes.access.update', $process), [
                'permissions' => [
                    $requester->id => ['can_view' => '1', 'can_start' => '1', 'can_approve' => '1'],
                ],
            ])
            ->assertSessionHasErrors('permissions');

        $this->assertDatabaseMissing('process_user_access', [
            'process_definition_id' => $process->id,
            'user_id' => $requester->id,
        ]);
    }

    public function test_each_process_can_have_only_one_default_approver(): void
    {
        $master = User::factory()->create(['role' => 'master', 'is_active' => true]);
        $approverA = User::factory()->create(['role' => 'approver', 'is_active' => true]);
        $approverB = User::factory()->create(['role' => 'approver', 'is_active' => true]);
        $process = $this->publishedProcess($master, 'Reembolso');
        $approval = ['can_view' => '1', 'can_start' => '0', 'can_approve' => '1'];

        $this->actingAs($master)
            ->from(route('master.processes.access.edit', $process))
            ->put(route('master.processes.access.update', $process), [
                'permissions' => [
                    $approverA->id => $approval,
                    $approverB->id => $approval,
                ],
            ])
            ->assertSessionHasErrors('permissions');

        $this->assertDatabaseCount('process_user_access', 0);
    }

    private function publishedProcess(User $owner, string $name = 'Processo publicado'): ProcessDefinition
    {
        return ProcessDefinition::query()->create([
            'name' => $name,
            'status' => 'published',
            'bpmn_xml' => '<bpmn:definitions/>',
            'flowable_deployment_id' => 'deployment-'.$name,
            'flowable_process_definition_id' => 'flowable-'.$name,
            'published_bpmn_hash' => hash('sha256', $name),
            'published_at' => now(),
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
    }
}
