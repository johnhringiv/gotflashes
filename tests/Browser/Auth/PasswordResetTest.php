<?php

use App\Models\User;
use Illuminate\Support\Facades\Password;

it('renders the reset form with a token and submits a new password', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $page = visit("/password/reset/{$token}?email={$user->email}");
    $page->assertSee('Set New Password');

    $page->fill('password', 'NewPassword123!')
        ->fill('password_confirmation', 'NewPassword123!');

    $page->click('#submit-btn');
    $page->wait(3);

    $page->assertSee('reset');
});

it('shows error when password and confirmation do not match', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $page = visit("/password/reset/{$token}?email={$user->email}");
    $page->assertSee('Set New Password');

    $page->fill('password', 'NewPassword123!')
        ->fill('password_confirmation', 'DifferentPassword!');

    $page->click('#submit-btn');
    $page->wait(2);

    $page->assertSee('match');
});

it('rejects password reset with invalid token', function () {
    $user = User::factory()->create();

    $response = $this->post('/password/reset', [
        'token' => 'invalid-token-abc123',
        'email' => $user->email,
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ]);

    // Laravel redirects back with session errors for invalid tokens
    $response->assertSessionHasErrors();
});
