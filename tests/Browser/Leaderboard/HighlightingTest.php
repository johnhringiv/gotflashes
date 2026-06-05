<?php

use App\Models\District;
use App\Models\Flash;
use App\Models\Fleet;
use App\Models\Member;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2027-01-15 12:00:00'));

    $this->district = District::first();
    $this->fleet = Fleet::where('district_id', District::first()->id)->first();

    $this->user = User::factory()->create([

        'first_name' => 'Highlighted',
        'last_name' => 'Sailor',
    ]);
    Member::create([
        'user_id' => $this->user->id,
        'district_id' => $this->district->id,
        'fleet_id' => $this->fleet->id,
        'year' => 2027,
    ]);
    Flash::factory()->sailing()->forUser($this->user)->onDate('2027-01-05')->create();

    // Create another user so there are multiple rows
    $otherUser = User::factory()->create([

        'first_name' => 'Other',
        'last_name' => 'Person',
    ]);
    Member::create([
        'user_id' => $otherUser->id,
        'district_id' => $this->district->id,
        'fleet_id' => $this->fleet->id,
        'year' => 2027,
    ]);
    Flash::factory()->sailing()->forUser($otherUser)->onDate('2027-01-06')->create();
});

it('highlights authenticated user row on sailor tab', function () {
    $this->actingAs($this->user);

    $page = visit('/leaderboard');

    $page->assertVisible('.current-user-row');
    $page->assertSee('You');
});

it('highlights user fleet on fleet tab', function () {
    $this->actingAs($this->user);

    $page = visit('/leaderboard?tab=fleet');

    $page->assertVisible('.current-user-row');
});

it('highlights user district on district tab', function () {
    $this->actingAs($this->user);

    $page = visit('/leaderboard?tab=district');

    $page->assertVisible('.current-user-row');
});

it('shows no highlight rows when anonymous', function () {
    $page = visit('/leaderboard');

    $page->assertMissing('.current-user-row');
    // The "You" badge should not appear in the table for anonymous users
    $page->assertScript("document.querySelectorAll('table .badge:has(> *)').length === 0 || !document.querySelector('table')?.textContent?.includes('You')", true);
});
