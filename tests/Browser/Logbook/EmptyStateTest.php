<?php

use App\Models\User;

beforeEach(function () {
    $this->travelTo(frozenJanuary());
});

it('shows empty-state message when user has no flashes', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visit('/logbook');

    $page->assertSee('No activities yet. Log your first flash to get started!');
});
