<?php

use App\Models\Flash;
use App\Models\User;

beforeEach(function () {
    $this->travelTo(frozenJanuary());
});

it('downloads user data CSV from /profile', function () {
    $user = User::factory()->create([
        'first_name' => 'Export',
        'last_name' => 'User',
    ]);
    Flash::factory()->sailing()->forUser($user)->onDate(testDate(5))->create([
        'event_type' => 'regatta',
        'location' => 'Export Lake',
    ]);

    $this->actingAs($user);

    $page = visit('/profile');
    $page->assertSee('Export My Data');
    $page->assertVisible('a[href*="export/user-data"]');

    // Verify the export endpoint returns a valid CSV with user data
    $response = $this->get('/export/user-data');
    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();
    expect($csv)->toContain('Export User');
    expect($csv)->toContain('Export Lake');
    expect($csv)->toContain('regatta');
    expect($csv)->toContain(testDate(5));
});

it('disables Export button for users with no flashes', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visit('/profile');
    $page->assertSee('Export My Data');
    $page->assertPresent('[data-tip*="No activities"]');
});
