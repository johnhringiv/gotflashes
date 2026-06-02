<?php

use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2027-01-15 12:00:00'));
});

it('shows empty-state message when user has no flashes', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visit('/logbook');

    $page->assertSee('No activities yet. Log your first flash to get started!');
});
