<?php

use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2027-01-15 12:00:00'));
});

it('shows unverified banner for unverified users', function () {
    $user = User::factory()->unverified()->create([

        'email_verification_token' => 'unverified-token',
        'email_verification_expires_at' => now()->addHours(24),
    ]);

    $this->actingAs($user);

    $page = visit('/logbook');

    $page->assertSee('verify your email');
});

it('does not show banner once email_verified_at is set', function () {
    $user = User::factory()->create([

        'email_verified_at' => now(),
    ]);

    $this->actingAs($user);

    $page = visit('/logbook');

    $page->assertDontSee('verify your email');
});
