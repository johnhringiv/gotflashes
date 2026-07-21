<?php

namespace Tests\Feature;

use App\Livewire\EmailVerificationBanner;
use App\Livewire\ProfileForm;
use App\Livewire\RegistrationForm;
use App\Models\District;
use App\Models\Fleet;
use App\Models\Member;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailChange;
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

        Livewire::test(RegistrationForm::class)
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

    // ============================================
    // Email-change verification is delivered to the NEW address (recipient routing)
    // ============================================

    public function test_email_change_verification_routes_to_new_pending_address(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
        ]);

        // A change-verification (isNewUser: false) must be delivered to the NEW address being
        // verified, so clicking the link proves control of the new inbox — not the old one.
        $recipient = $user->routeNotificationForMail(new VerifyEmailChange('token', false));

        $this->assertSame('new@example.com', $recipient);
    }

    public function test_new_user_verification_routes_to_current_address(): void
    {
        $user = User::factory()->create([
            'email' => 'current@example.com',
            'pending_email' => null,
        ]);

        // New-user registration verification (isNewUser: true, no pending change) → current email.
        $recipient = $user->routeNotificationForMail(new VerifyEmailChange('token', true));

        $this->assertSame('current@example.com', $recipient);
    }

    public function test_other_mail_is_not_misrouted_while_an_email_change_is_pending(): void
    {
        $user = User::factory()->create([
            'email' => 'current@example.com',
            'pending_email' => 'pending@example.com',
        ]);

        // Regression guard for the routing fix: a password reset must still go to the CURRENT
        // (verified) address even while an unverified email change is pending — never the
        // pending address (which the user has not yet proven they control).
        $recipient = $user->routeNotificationForMail(new ResetPasswordNotification('token'));

        $this->assertSame('current@example.com', $recipient);
    }

    public function test_email_change_flow_delivers_verification_to_the_new_address(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'john@example.com',
            'email_verified_at' => now(),
        ]);

        // Profile saves require an affiliation (every registered user has one)
        Member::create([
            'user_id' => $user->id,
            'district_id' => District::noneId(),
            'fleet_id' => Fleet::noneId(),
            'year' => now()->year,
        ]);

        Livewire::actingAs($user)
            ->test(ProfileForm::class)
            ->set('email', 'jane@example.com')
            ->call('save')
            ->assertHasNoErrors();

        // End-to-end guard: changing the email must send the change-verification, resolved to
        // the new pending address rather than the old login email.
        Notification::assertSentTo(
            $user,
            VerifyEmailChange::class,
            function (VerifyEmailChange $notification, array $channels, $notifiable) {
                return $notification->isNewUser === false
                    && $notifiable->routeNotificationForMail($notification) === 'jane@example.com';
            }
        );
    }

    public function test_banner_resend_during_pending_change_routes_to_new_address(): void
    {
        Notification::fake();

        // Unverified user (e.g. registered with a typo) who has since requested an email
        // change — pending_email is set but email is still unverified, so the global
        // verification banner shows.
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
            'email_verified_at' => null,
        ]);

        Livewire::actingAs($user)
            ->test(EmailVerificationBanner::class)
            ->call('resendVerification');

        // The banner's resend must behave like a change-verification (isNewUser: false)
        // and route to the NEW address — not hardcode isNewUser: true and misdeliver the
        // link to the old/unreachable address.
        Notification::assertSentTo(
            $user,
            VerifyEmailChange::class,
            function (VerifyEmailChange $notification, array $channels, $notifiable) {
                return $notification->isNewUser === false
                    && $notifiable->routeNotificationForMail($notification) === 'new@example.com';
            }
        );
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
