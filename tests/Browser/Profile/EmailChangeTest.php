<?php

use App\Models\User;

beforeEach(function () {
    $this->travelTo(frozenJanuary());

    $this->user = User::factory()->create([

        'email_verified_at' => now(),
        'first_name' => 'Email',
        'last_name' => 'Changer',
    ]);
});

it('initiates email change and shows pending-email banner', function () {
    $this->actingAs($this->user);

    $page = visit('/profile');

    // Change the email field
    $page->fill('[wire\\:model\\.live\\.blur="email"]', 'delivered+newemail@resend.dev')
        ->pressAndWaitFor('Save Changes', 2);

    $page->waitForText('Pending Email Change', 5);
    $page->assertSee('delivered+newemail@resend.dev');
    $page->assertSee('Resend');
    $page->assertSee('Cancel');
});

it('cancels a pending email change', function () {
    $this->user->update([
        'pending_email' => 'delivered+cancel@resend.dev',
        'email_verification_token' => 'cancel-token-456',
        'email_verification_expires_at' => now()->addHours(24),
    ]);

    $this->actingAs($this->user);

    $page = visit('/profile');

    $page->assertSee('Pending Email Change');
    $page->assertSee('delivered+cancel@resend.dev');

    // Call cancelEmailChange via Livewire directly
    $page->script("
        const el = document.querySelector('form[wire\\\\:submit]')?.closest('[wire\\\\:id]');
        if (el) Livewire.find(el.getAttribute('wire:id')).call('cancelEmailChange');
    ");
    $page->assertSee('Email change cancelled');

    // Pending email banner should disappear
    $page->assertDontSee('Pending Email Change');

    // Verify database cleanup
    $this->user->refresh();
    expect($this->user->pending_email)->toBeNull();
    expect($this->user->email_verification_token)->toBeNull();
});

it('verifies pending email change via token link', function () {
    $token = 'verify-token-789';
    $this->user->update([
        'pending_email' => 'delivered+verified@resend.dev',
        'email_verification_token' => $token,
        'email_verification_expires_at' => now()->addHours(24),
    ]);

    $this->actingAs($this->user);

    // Visit the verification URL directly
    $page = visit("/verify-email/{$token}");

    // Should redirect to profile with success message
    $page->assertPathIs('/profile');
    $page->assertSee('email has been successfully updated');

    // Verify database was updated
    $this->user->refresh();
    expect($this->user->email)->toBe('delivered+verified@resend.dev');
    expect($this->user->pending_email)->toBeNull();
    expect($this->user->email_verification_token)->toBeNull();
    expect($this->user->email_verified_at)->not->toBeNull();
});

it('shows error when verification token is invalid', function () {
    $this->actingAs($this->user);

    $page = visit('/verify-email/totally-bogus-token');

    // Should show an error — invalid or expired token
    $page->assertSee('invalid');
});

it('shows error when verification token is expired', function () {
    $this->user->update([
        'pending_email' => 'delivered+expired@resend.dev',
        'email_verification_token' => 'expired-token-123',
        'email_verification_expires_at' => now()->subHour(),
    ]);

    $this->actingAs($this->user);

    $page = visit('/verify-email/expired-token-123');

    // Should show expiration error
    $page->assertSee('expired');
});
