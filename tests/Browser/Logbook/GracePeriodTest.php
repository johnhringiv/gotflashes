<?php

use App\Models\Flash;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2027-01-15 12:00:00'));
});

it('hides Edit and Delete buttons on flashes outside the editable range', function () {
    $user = User::factory()->create();

    Flash::factory()->forUser($user)->sailing()->onDate('2025-06-15')->create([
        'event_type' => 'regatta',
        'location' => 'Old Lake',
    ]);

    Flash::factory()->forUser($user)->sailing()->onDate('2027-01-10')->create([
        'event_type' => 'practice',
        'location' => 'Current Lake',
    ]);

    $this->actingAs($user);

    $page = visit('/logbook');

    $page->assertSee('Old Lake');
    $page->assertSee('Current Lake');

    // Old flash (outside editable range) should NOT have Edit/Delete buttons
    $page->assertScript("(() => {
        const cards = document.querySelectorAll('.card.bg-base-100.shadow');
        let count = 0;
        cards.forEach(c => { if (c.textContent.includes('Old Lake')) count = c.querySelectorAll('.btn-ghost.btn-xs').length; });
        return count;
    })()", 0);
});

it('returns 403 when forging an edit for an out-of-range flash', function () {
    $user = User::factory()->create();

    $flash = Flash::factory()->forUser($user)->sailing()->onDate('2025-06-15')->create([
        'event_type' => 'regatta',
    ]);

    $this->actingAs($user);

    // Directly test via HTTP that editing an out-of-range flash is rejected
    $response = $this->get("/logbook/{$flash->id}/edit");
    $response->assertStatus(403);
});
