<?php

use App\Models\District;
use App\Models\Flash;
use App\Models\Fleet;
use App\Models\Member;
use App\Models\User;

beforeEach(function () {
    $this->travelTo(frozenJanuary());
});

it('logging flashes updates the leaderboard for the same user', function () {
    $district = District::first();
    $fleet = Fleet::where('district_id', District::first()->id)->first();

    $user = User::factory()->create([

        'first_name' => 'Logger',
        'last_name' => 'Sailor',
    ]);
    Member::create([
        'user_id' => $user->id,
        'district_id' => $district->id,
        'fleet_id' => $fleet->id,
        'year' => now()->year,
    ]);

    // First, verify user is NOT on leaderboard (no flashes yet)
    $page = visit('/leaderboard');
    $page->assertDontSee('Logger Sailor');

    // Now create a flash directly in the database (simulating a log)
    Flash::factory()->sailing()->forUser($user)->onDate(testDate(10))->create();

    // Visit leaderboard again and verify user appears
    $page2 = visit('/leaderboard');
    $page2->assertSee('Logger');
    $page2->assertSee('Sailor');
});
