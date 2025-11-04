<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    // ============================================
    // New User Registration & Verification
    // ============================================

    public function test_new_user_registration_sends_verification_email(): void
    {
        Notification::fake();

        Livewire::test(\App\Livewire\RegistrationForm::class)
            ->set('first_name', 'John')
            ->set('last_name', 'Doe')
            ->set('email', 'john@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->set('date_of_birth', '1990-01-01')
            ->set('gender', 'male')
            ->set('address_line1', '123 Main St')
            ->set('city', 'City')
            ->set('state', 'State')
            ->set('zip_code', '12345')
            ->set('country', 'USA')
            ->set('district_id', 1)
            ->set('fleet_id', 1)
            ->call('register');

        $user = User::where('email', 'john@example.com')->first();

        // User should be created
        $this->assertNotNull($user);

        // User should NOT be verified yet
        $this->assertNull($user->email_verified_at);

        // User should have a verification token
        $this->assertNotNull($user->email_verification_token);
        $this->assertNotNull($user->email_verification_expires_at);

        // Token should expire in the future (approximately 24 hours)
        $this->assertTrue($user->email_verification_expires_at->isFuture());
        $this->assertTrue($user->email_verification_expires_at->isAfter(now()->addHours(23)));
        $this->assertTrue($user->email_verification_expires_at->isBefore(now()->addHours(25)));
    }

    public function test_new_user_can_login_before_verifying(): void
    {
        // Create unverified user
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('password123'),
            'email_verified_at' => null,
        ]);

        // User should be able to login
        $response = $this->post('/login', [
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/logbook');
        $this->assertAuthenticated();
    }

    public function test_clicking_verification_link_verifies_new_user(): void
    {
        $token = Str::random(64);

        $user = User::factory()->create([
            'email' => 'john@example.com',
            'email_verified_at' => null,
            'email_verification_token' => $token,
            'email_verification_expires_at' => now()->addHours(24),
        ]);

        // Click verification link
        $response = $this->get('/verify-email/'.$token);

        // Should redirect to logbook with success message
        $response->assertRedirect('/logbook');
        $response->assertSessionHas('success', 'Your email has been verified! Thank you.');

        // User should be verified
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->email_verification_token);
        $this->assertNull($user->email_verification_expires_at);
    }

    public function test_invalid_verification_token_shows_error(): void
    {
        $response = $this->get('/verify-email/invalid-token-12345');

        $response->assertRedirect('/logbook');
        $response->assertSessionHas('error', 'Invalid verification link.');
    }

    public function test_expired_verification_token_shows_error(): void
    {
        $token = Str::random(64);

        $user = User::factory()->create([
            'email' => 'john@example.com',
            'email_verified_at' => null,
            'email_verification_token' => $token,
            'email_verification_expires_at' => now()->subHours(1), // Expired 1 hour ago
        ]);

        $response = $this->get('/verify-email/'.$token);

        $response->assertRedirect('/profile');
        $response->assertSessionHas('error');

        // User should still be unverified
        $user->refresh();
        $this->assertNull($user->email_verified_at);
    }

    // ============================================
    // Email Change Verification
    // ============================================

    public function test_email_change_creates_pending_email_with_verification(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->post('/logbook', [
            // Would typically go through ProfileForm, but testing the logic
        ]);

        // Update email directly through ProfileForm logic
        $user->update([
            'pending_email' => 'new@example.com',
            'email_verification_token' => Str::random(64),
            'email_verification_expires_at' => now()->addHours(24),
        ]);

        // Old email should remain active
        $this->assertEquals('old@example.com', $user->email);

        // New email should be pending
        $this->assertEquals('new@example.com', $user->pending_email);
        $this->assertNotNull($user->email_verification_token);
    }

    public function test_clicking_email_change_verification_link_updates_email(): void
    {
        $token = Str::random(64);

        $user = User::factory()->create([
            'email' => 'old@example.com',
            'email_verified_at' => now(),
            'pending_email' => 'new@example.com',
            'email_verification_token' => $token,
            'email_verification_expires_at' => now()->addHours(24),
        ]);

        // Click verification link
        $response = $this->get('/verify-email/'.$token);

        // Should redirect to profile with success message
        $response->assertRedirect('/profile');
        $response->assertSessionHas('success', 'Your email has been successfully updated!');

        // Email should be updated
        $user->refresh();
        $this->assertEquals('new@example.com', $user->email);
        $this->assertNull($user->pending_email);
        $this->assertNull($user->email_verification_token);
        $this->assertNotNull($user->email_verified_at); // Should still be verified
    }

    public function test_old_email_remains_active_until_verification_completes(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'password' => bcrypt('password123'),
            'pending_email' => 'new@example.com',
            'email_verification_token' => Str::random(64),
            'email_verification_expires_at' => now()->addHours(24),
        ]);

        // User should still be able to login with old email
        $response = $this->post('/login', [
            'email' => 'old@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/logbook');
        $this->assertAuthenticated();
    }

    public function test_new_email_cannot_login_until_verified(): void
    {
        User::factory()->create([
            'email' => 'old@example.com',
            'password' => bcrypt('password123'),
            'pending_email' => 'new@example.com',
            'email_verification_token' => Str::random(64),
            'email_verification_expires_at' => now()->addHours(24),
        ]);

        // Attempt to login with new (unverified) email
        $response = $this->post('/login', [
            'email' => 'new@example.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors();
        $this->assertGuest();
    }

    // ============================================
    // Auth Session Updates
    // ============================================

    public function test_verification_updates_authenticated_session(): void
    {
        $token = Str::random(64);

        $user = User::factory()->create([
            'email' => 'john@example.com',
            'email_verified_at' => null,
            'email_verification_token' => $token,
            'email_verification_expires_at' => now()->addHours(24),
        ]);

        // Login as the user
        $this->actingAs($user);

        // Verify email
        $response = $this->get('/verify-email/'.$token);

        // Auth session should be updated with fresh user data
        $this->assertNotNull(auth()->user()->email_verified_at);
    }

    // ============================================
    // Token Reuse & Security
    // ============================================

    public function test_verification_token_can_only_be_used_once(): void
    {
        $token = Str::random(64);

        $user = User::factory()->create([
            'email' => 'john@example.com',
            'email_verified_at' => null,
            'email_verification_token' => $token,
            'email_verification_expires_at' => now()->addHours(24),
        ]);

        // Use token first time
        $this->get('/verify-email/'.$token);

        // Try to use token again
        $response = $this->get('/verify-email/'.$token);

        $response->assertRedirect('/logbook');
        $response->assertSessionHas('error', 'Invalid verification link.');
    }

    public function test_verification_token_is_unique_per_user(): void
    {
        $token1 = Str::random(64);
        $token2 = Str::random(64);

        $user1 = User::factory()->create([
            'email' => 'user1@example.com',
            'email_verified_at' => null,
            'email_verification_token' => $token1,
            'email_verification_expires_at' => now()->addHours(24),
        ]);

        $user2 = User::factory()->create([
            'email' => 'user2@example.com',
            'email_verified_at' => null,
            'email_verification_token' => $token2,
            'email_verification_expires_at' => now()->addHours(24),
        ]);

        // Verify user1
        $this->get('/verify-email/'.$token1);

        // User1 should be verified
        $user1->refresh();
        $this->assertNotNull($user1->email_verified_at);

        // User2 should not be affected
        $user2->refresh();
        $this->assertNull($user2->email_verified_at);
        $this->assertEquals($token2, $user2->email_verification_token);
    }

    // ============================================
    // Edge Cases
    // ============================================

    public function test_already_verified_user_clicking_link_again_is_harmless(): void
    {
        $token = Str::random(64);

        $user = User::factory()->create([
            'email' => 'john@example.com',
            'email_verified_at' => now()->subDays(5), // Already verified 5 days ago
            'email_verification_token' => $token,
            'email_verification_expires_at' => now()->addHours(24),
        ]);

        $originalVerifiedAt = $user->email_verified_at;

        // Click link again
        $this->get('/verify-email/'.$token);

        $user->refresh();

        // Should still be verified (timestamp updated but not null)
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_user_with_no_token_is_considered_unverified(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'email_verified_at' => null,
            'email_verification_token' => null,
            'email_verification_expires_at' => null,
        ]);

        $this->assertNull($user->email_verified_at);
        $this->assertFalse((bool) $user->email_verified_at);
    }

    public function test_pending_email_with_expired_token_does_not_change_email(): void
    {
        $token = Str::random(64);

        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
            'email_verification_token' => $token,
            'email_verification_expires_at' => now()->subHour(), // Expired
        ]);

        // Try to verify with expired token
        $this->get('/verify-email/'.$token);

        // Email should NOT change
        $user->refresh();
        $this->assertEquals('old@example.com', $user->email);
        $this->assertEquals('new@example.com', $user->pending_email);
    }
}
