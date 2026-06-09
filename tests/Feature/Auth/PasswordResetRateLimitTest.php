<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_requests_are_capped_per_ip_across_different_accounts(): void
    {
        Notification::fake();

        // Six distinct accounts so the per-EMAIL broker throttle never fires — this
        // isolates the per-IP cap, i.e. the mail-bomb / enumeration scenario.
        $users = User::factory()->count(6)->create();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('password.email'), ['email' => $users[$i]->email])
                ->assertStatus(200);
        }

        // 6th distinct email from the same IP trips the per-IP cap (5/hour).
        $this->postJson(route('password.email'), ['email' => $users[5]->email])
            ->assertStatus(429)
            ->assertJson(['message' => 'Too many reset requests. Please wait a while and try again.']);
    }

    public function test_token_submissions_are_capped_per_ip(): void
    {
        $user = User::factory()->create();

        $payload = [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ];

        // 10 submissions are allowed (each fails validation of the token → 422).
        for ($i = 0; $i < 10; $i++) {
            $this->postJson(route('password.update'), $payload)->assertStatus(422);
        }

        // 11th from the same IP is throttled before the reset is attempted.
        $this->postJson(route('password.update'), $payload)
            ->assertStatus(429)
            ->assertJson(['message' => 'Too many attempts. Please wait a while and try again.']);
    }
}
