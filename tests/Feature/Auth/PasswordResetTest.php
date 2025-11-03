<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_link_screen_can_be_rendered(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertStatus(200);
        $response->assertSee('Reset Password');
        $response->assertSee('Send Reset Link');
    }

    public function test_password_reset_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Password reset link sent! Check your email.',
        ]);
    }

    public function test_password_reset_link_sends_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), [
            'email' => $user->email,
        ]);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_password_reset_fails_with_invalid_email(): void
    {
        $response = $this->postJson(route('password.email'), [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_password_reset_requires_email(): void
    {
        $response = $this->postJson(route('password.email'), [
            'email' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_password_reset_is_throttled(): void
    {
        $user = User::factory()->create();

        // First request should succeed
        $response = $this->post(route('password.email'), [
            'email' => $user->email,
        ]);
        $response->assertStatus(200);

        // Second immediate request should be throttled
        $response = $this->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $response->assertStatus(429);
        $response->assertJson([
            'success' => false,
            'message' => 'Please wait before requesting another reset link.',
        ]);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $response = $this->get(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Set New Password');
        $response->assertSee('Reset Password');
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Password reset successful! You can now login.',
        ]);

        // Verify password was actually changed
        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));
    }

    public function test_password_reset_fails_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_reset_password_requires_email(): void
    {
        $response = $this->postJson(route('password.update'), [
            'token' => 'some-token',
            'email' => '',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_password_reset_requires_password(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $response = $this->postJson(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_password_reset_requires_password_confirmation(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $response = $this->postJson(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password123',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_password_reset_requires_minimum_length(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $response = $this->postJson(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_user_can_login_with_new_password(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);
        $newPassword = 'new-password123';

        // Reset password
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        // Try to login with new password
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => $newPassword,
        ]);

        $response->assertRedirect(route('logbook.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_reset_notification_contains_correct_url(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), [
            'email' => $user->email,
        ]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
            $mail = $notification->toMail($user);
            $actionUrl = $mail->viewData['resetUrl'];

            // Verify URL contains token and email
            $this->assertStringContainsString('password/reset/', $actionUrl);
            $this->assertStringContainsString('email=', $actionUrl);

            return true;
        });
    }

    public function test_reset_notification_uses_custom_template(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), [
            'email' => $user->email,
        ]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
            $mail = $notification->toMail($user);

            // Verify custom view is used
            $this->assertEquals('emails.reset-password', $mail->view);

            // Verify subject
            $this->assertStringContainsString('G.O.T. Flashes', $mail->subject);

            return true;
        });
    }

    public function test_complete_password_reset_flow(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('old-password'),
        ]);

        // Step 1: Request password reset
        $response = $this->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Verify notification was sent
        Notification::assertSentTo($user, ResetPasswordNotification::class);

        // Step 2: Get the token from the database
        $token = Password::broker()->createToken($user);

        // Step 3: Visit reset password page
        $response = $this->get(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]));

        $response->assertStatus(200);

        // Step 4: Submit new password
        $newPassword = 'new-secure-password123';
        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'redirect' => route('login'),
        ]);

        // Step 5: Verify password was changed
        $user->refresh();
        $this->assertTrue(Hash::check($newPassword, $user->password));

        // Step 6: Verify can login with new password
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => $newPassword,
        ]);

        $response->assertRedirect(route('logbook.index'));
        $this->assertAuthenticatedAs($user);
    }
}
