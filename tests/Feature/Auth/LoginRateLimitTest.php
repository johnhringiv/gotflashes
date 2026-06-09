<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_throttled_after_too_many_failed_attempts(): void
    {
        User::factory()->create([
            'email' => 'victim@example.com',
            'password' => bcrypt('password123'),
        ]);

        // 5 failed attempts are allowed (the per-email+IP limit).
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'victim@example.com', 'password' => 'wrong']);
        }

        // The 6th is throttled — even with the CORRECT password, since the throttle
        // check runs before authentication.
        $response = $this->post('/login', [
            'email' => 'victim@example.com',
            'password' => 'password123',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Too many login attempts',
            session('errors')->get('email')[0]
        );
    }

    public function test_successful_login_clears_the_per_account_counter(): void
    {
        User::factory()->create([
            'email' => 'u@example.com',
            'password' => bcrypt('password123'),
        ]);

        // 4 failures — under the limit, so a correct login still goes through.
        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', ['email' => 'u@example.com', 'password' => 'wrong']);
        }

        $response = $this->post('/login', [
            'email' => 'u@example.com',
            'password' => 'password123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('logbook.index'));

        // The per-email+IP counter was cleared on success.
        $this->assertSame(0, RateLimiter::attempts('login:u@example.com|127.0.0.1'));
    }

    public function test_throttling_one_account_does_not_block_another_on_the_same_ip(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => bcrypt('password123')]);
        User::factory()->create(['email' => 'b@example.com', 'password' => bcrypt('password123')]);

        // Lock out account A from this IP (shared club/family WiFi scenario).
        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['email' => 'a@example.com', 'password' => 'wrong']);
        }

        // Account B can still log in from the same IP — keying is email+IP, not IP alone.
        $response = $this->post('/login', [
            'email' => 'b@example.com',
            'password' => 'password123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('logbook.index'));
    }
}
