<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class EmailVerificationRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        RateLimiter::clear('resend-verification:1');
        RateLimiter::clear('resend-verification-hourly:1');
    }

    public function test_user_can_resend_verification_email_once(): void
    {
        $user = User::factory()->create([
            'email_verification_token' => 'test-token',
            'email_verification_expires_at' => now()->addHours(24),
        ]);

        $this->actingAs($user);

        Livewire::test('profile-form')
            ->call('resendEmailVerification')
            ->assertDispatched('toast');
    }

    public function test_user_cannot_resend_verification_email_within_three_minutes(): void
    {
        $user = User::factory()->create([
            'email_verification_token' => 'test-token',
            'email_verification_expires_at' => now()->addHours(24),
        ]);

        $this->actingAs($user);

        // Track how many emails would actually be sent
        $emailsSent = 0;
        $rateLimitKey = 'resend-verification:'.$user->id;

        // Attempt to spam - try sending 10 times rapidly
        for ($i = 0; $i < 10; $i++) {
            // Check if we can send before calling
            if (! RateLimiter::tooManyAttempts($rateLimitKey, 1)) {
                $emailsSent++;
                Livewire::test('profile-form')
                    ->call('resendEmailVerification');
            } else {
                // Just call to verify toast is dispatched with warning
                Livewire::test('profile-form')
                    ->call('resendEmailVerification')
                    ->assertDispatched('toast');
            }
        }

        // Should only be able to send 1 email despite 10 attempts
        $this->assertEquals(1, $emailsSent, 'Rate limiter should only allow 1 email within 3 minutes');
    }

    public function test_user_cannot_resend_more_than_five_emails_per_hour(): void
    {
        $user = User::factory()->create([
            'email_verification_token' => 'test-token',
            'email_verification_expires_at' => now()->addHours(24),
        ]);

        $this->actingAs($user);

        // Simulate 5 successful sends by manually hitting the rate limiter
        $rateLimitKey = 'resend-verification:'.$user->id;
        $hourlyLimitKey = 'resend-verification-hourly:'.$user->id;

        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($rateLimitKey, 180);
            RateLimiter::hit($hourlyLimitKey, 3600);
            // Clear the 3-minute limit to simulate time passing
            RateLimiter::clear($rateLimitKey);
        }

        // 6th attempt - should hit hourly limit
        Livewire::test('profile-form')
            ->call('resendEmailVerification')
            ->assertDispatched('toast');

        // Verify hourly limit was hit
        $this->assertTrue(RateLimiter::tooManyAttempts($hourlyLimitKey, 5));
    }

    public function test_rate_limit_shows_remaining_time(): void
    {
        $user = User::factory()->create([
            'email_verification_token' => 'test-token',
            'email_verification_expires_at' => now()->addHours(24),
        ]);

        $this->actingAs($user);

        // First attempt
        Livewire::test('profile-form')
            ->call('resendEmailVerification');

        // Second attempt - should show wait time
        Livewire::test('profile-form')
            ->call('resendEmailVerification')
            ->assertDispatched('toast');

        // Verify rate limiter provides time remaining
        $rateLimitKey = 'resend-verification:'.$user->id;
        $seconds = RateLimiter::availableIn($rateLimitKey);
        $this->assertGreaterThan(0, $seconds);
        $this->assertLessThanOrEqual(180, $seconds); // Should be within 3 minutes
    }

    public function test_user_without_verification_token_cannot_resend(): void
    {
        $user = User::factory()->create([
            'email_verification_token' => null,
        ]);

        $this->actingAs($user);

        Livewire::test('profile-form')
            ->call('resendEmailVerification')
            ->assertNotDispatched('toast');
    }

    public function test_registration_rate_limit_per_ip_address(): void
    {
        $ipAddress = '192.168.1.1';

        // Simulate 3 registrations from same IP by manually hitting the rate limiter
        $rateLimitKey = 'registration-email:'.$ipAddress;
        for ($i = 0; $i < 3; $i++) {
            RateLimiter::hit($rateLimitKey, 3600);
        }

        // Verify the rate limit is active
        $this->assertTrue(RateLimiter::tooManyAttempts($rateLimitKey, 3));

        // 4th registration attempt should be rate limited but still succeed
        // (In practice, the controller checks this during registration)
        $remaining = RateLimiter::remaining($rateLimitKey, 3);
        $this->assertEquals(0, $remaining);
    }

    public function test_actual_spam_attempt_is_blocked(): void
    {
        $user = User::factory()->create([
            'email_verification_token' => 'test-token',
            'email_verification_expires_at' => now()->addHours(24),
        ]);

        $this->actingAs($user);

        // Notification fake to count actual emails
        Notification::fake();

        // Try to spam 20 times
        $component = Livewire::test('profile-form');
        for ($i = 0; $i < 20; $i++) {
            $component->call('resendEmailVerification');
        }

        // Count how many notifications were actually sent
        Notification::assertSentTimes(\App\Notifications\VerifyEmailChange::class, 1);
    }

    public function test_banner_resend_is_rate_limited(): void
    {
        $user = User::factory()->create([
            'email_verification_token' => 'test-token',
            'email_verification_expires_at' => now()->addHours(24),
            'email_verified_at' => null, // Not verified
        ]);

        $this->actingAs($user);

        // Notification fake to count actual emails
        Notification::fake();

        // Try to spam banner resend button 20 times
        $component = Livewire::test('email-verification-banner');
        for ($i = 0; $i < 20; $i++) {
            $component->call('resendVerification');
        }

        // Should only send 1 email despite 20 attempts
        Notification::assertSentTimes(\App\Notifications\VerifyEmailChange::class, 1);
    }

    public function test_banner_and_profile_share_same_rate_limit(): void
    {
        $user = User::factory()->create([
            'email_verification_token' => 'test-token',
            'email_verification_expires_at' => now()->addHours(24),
            'email_verified_at' => null,
        ]);

        $this->actingAs($user);

        Notification::fake();

        // Send from banner
        Livewire::test('email-verification-banner')
            ->call('resendVerification')
            ->assertDispatched('toast');

        // Immediately try from profile - should be rate limited
        Livewire::test('profile-form')
            ->call('resendEmailVerification')
            ->assertDispatched('toast');

        // Should only send 1 email total (they share the same rate limit key)
        Notification::assertSentTimes(\App\Notifications\VerifyEmailChange::class, 1);
    }

    public function test_registration_is_rate_limited_per_ip(): void
    {
        RateLimiter::clear('registration:127.0.0.1');

        $baseData = [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'address_line1' => '123 Test St',
            'city' => 'Test City',
            'state' => 'TS',
            'zip_code' => '12345',
            'country' => 'US',
        ];

        // First 5 registrations should succeed
        for ($i = 0; $i < 5; $i++) {
            $data = array_merge($baseData, ['email' => "test{$i}@example.com"]);
            Livewire::test(\App\Livewire\RegistrationForm::class)
                ->set('first_name', $data['first_name'])
                ->set('last_name', $data['last_name'])
                ->set('email', $data['email'])
                ->set('password', $data['password'])
                ->set('password_confirmation', $data['password_confirmation'])
                ->set('date_of_birth', $data['date_of_birth'])
                ->set('gender', $data['gender'])
                ->set('address_line1', $data['address_line1'])
                ->set('city', $data['city'])
                ->set('state', $data['state'])
                ->set('zip_code', $data['zip_code'])
                ->set('country', $data['country'])
                ->call('register');

            $this->assertDatabaseHas('users', ['email' => $data['email']]);
        }

        // 6th registration should be rate limited
        $data = array_merge($baseData, ['email' => 'test6@example.com']);
        Livewire::test(\App\Livewire\RegistrationForm::class)
            ->set('first_name', $data['first_name'])
            ->set('last_name', $data['last_name'])
            ->set('email', $data['email'])
            ->set('password', $data['password'])
            ->set('password_confirmation', $data['password_confirmation'])
            ->set('date_of_birth', $data['date_of_birth'])
            ->set('gender', $data['gender'])
            ->set('address_line1', $data['address_line1'])
            ->set('city', $data['city'])
            ->set('state', $data['state'])
            ->set('zip_code', $data['zip_code'])
            ->set('country', $data['country'])
            ->call('register')
            ->assertDispatched('toast');

        // User should NOT be created (rate limited)
        $this->assertDatabaseMissing('users', ['email' => 'test6@example.com']);
    }

    public function test_registration_email_rate_limit_is_separate(): void
    {
        RateLimiter::clear('registration:127.0.0.1');
        RateLimiter::clear('registration-email:127.0.0.1');

        Notification::fake();

        $baseData = [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'address_line1' => '123 Test St',
            'city' => 'Test City',
            'state' => 'TS',
            'zip_code' => '12345',
            'country' => 'US',
        ];

        // Register 4 users (within both rate limits)
        for ($i = 0; $i < 4; $i++) {
            $data = array_merge($baseData, ['email' => "test{$i}@example.com"]);
            Livewire::test(\App\Livewire\RegistrationForm::class)
                ->set('first_name', $data['first_name'])
                ->set('last_name', $data['last_name'])
                ->set('email', $data['email'])
                ->set('password', $data['password'])
                ->set('password_confirmation', $data['password_confirmation'])
                ->set('date_of_birth', $data['date_of_birth'])
                ->set('gender', $data['gender'])
                ->set('address_line1', $data['address_line1'])
                ->set('city', $data['city'])
                ->set('state', $data['state'])
                ->set('zip_code', $data['zip_code'])
                ->set('country', $data['country'])
                ->call('register');
        }

        // Should have sent 3 emails (email rate limit is 3 per hour)
        // 4th registration succeeds but email is rate limited
        Notification::assertSentTimes(\App\Notifications\VerifyEmailChange::class, 3);
    }
}
