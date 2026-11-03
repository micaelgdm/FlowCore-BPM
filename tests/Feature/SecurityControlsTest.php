<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_includes_security_headers_and_disables_caching(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.store'), [
                'email' => 'unknown@example.test',
                'password' => 'incorrect-password',
            ])->assertRedirect();
        }

        $this->post(route('login.store'), [
            'email' => 'unknown@example.test',
            'password' => 'incorrect-password',
        ])->assertTooManyRequests();
    }
}
