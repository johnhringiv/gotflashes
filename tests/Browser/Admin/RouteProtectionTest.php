<?php

use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2027-01-15 12:00:00'));
});

it('returns 403 for /admin/fulfillment as non-admin', function () {
    $user = User::factory()->create([

    ]);

    $this->actingAs($user);

    $response = $this->get('/admin/fulfillment');
    $response->assertStatus(403);
});

it('returns 403 for /admin/sailor-logs as non-admin', function () {
    $user = User::factory()->create([

    ]);

    $this->actingAs($user);

    $response = $this->get('/admin/sailor-logs');
    $response->assertStatus(403);
});

it('redirects to login for /admin/* when anonymous', function () {
    $response = $this->get('/admin/fulfillment');
    $response->assertRedirect('/login');

    $response = $this->get('/admin/sailor-logs');
    $response->assertRedirect('/login');
});
