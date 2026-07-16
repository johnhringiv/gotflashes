<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\IpRateLimiter;
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

        // The per-email+IP counter was cleared on success. The key hashes the email
        // (so special chars can't alias one account's bucket onto another's).
        $this->assertSame(
            0,
            RateLimiter::attempts(IpRateLimiter::identityKey('login', 'u@example.com', '127.0.0.1'))
        );
    }

    public function test_lockout_message_reports_the_short_per_account_window_not_the_ip_backstop(): void
    {
        User::factory()->create([
            'email' => 'victim@example.com',
            'password' => bcrypt('password123'),
        ]);

        // 5 rapid failures trip only the 60s per-email+IP limit (well under the 25/IP
        // backstop). The retry time must reflect that ~1-minute window, not the
        // untripped per-IP limiter's 15-minute decay timer.
        for ($i = 0; $i < 6; $i++) {
            $response = $this->post('/login', ['email' => 'victim@example.com', 'password' => 'wrong']);
        }

        $message = session('errors')->get('email')[0];
        $this->assertStringContainsString('Too many login attempts', $message);
        $this->assertStringContainsString('1 minute(s)', $message);
        $this->assertStringNotContainsString('15 minute', $message);
    }

    public function test_coarse_per_ip_backstop_locks_out_spraying_across_accounts(): void
    {
        // Spray 25 failures across distinct (non-existent) accounts from one IP so the
        // per-email+IP limiter never trips but the coarse 25/IP backstop does.
        for ($i = 0; $i < 25; $i++) {
            $this->post('/login', ['email' => "spray{$i}@example.com", 'password' => 'wrong']);
        }

        // A brand-new account from the same IP is now blocked by the per-IP backstop,
        // even though its own per-email+IP counter is empty.
        User::factory()->create(['email' => 'target@example.com', 'password' => bcrypt('password123')]);

        $response = $this->post('/login', [
            'email' => 'target@example.com',
            'password' => 'password123',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many login attempts', session('errors')->get('email')[0]);
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
