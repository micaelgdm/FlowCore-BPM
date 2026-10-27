<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_sent_to_login_for_the_master_area(): void
    {
        $this->get(route('master.processes.index'))->assertRedirect(route('login'));
    }

    public function test_master_user_can_sign_in(): void
    {
        $user = User::factory()->create([
            'role' => 'master',
            'is_active' => true,
            'password' => 'local-test-password',
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'local-test-password',
        ])->assertRedirect(route('master.processes.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_requester_signs_in_to_the_process_portal_instead_of_the_modeler(): void
    {
        $user = User::factory()->create([
            'role' => 'requester',
            'is_active' => true,
            'password' => 'local-test-password',
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'local-test-password',
        ])->assertRedirect(route('portal.processes.index'));

        $this->assertAuthenticatedAs($user);
        $this->get(route('master.processes.index'))->assertForbidden();
    }

    public function test_inactive_user_cannot_sign_in(): void
    {
        $user = User::factory()->create([
            'role' => 'requester',
            'is_active' => false,
            'password' => 'local-test-password',
        ]);

        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'local-test-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_regular_user_cannot_enter_the_master_area(): void
    {
        $user = User::factory()->create(['role' => 'requester', 'is_active' => true]);

        $this->actingAs($user)
            ->get(route('master.processes.index'))
            ->assertForbidden();
    }
}
