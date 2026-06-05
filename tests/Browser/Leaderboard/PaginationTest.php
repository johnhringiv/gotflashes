<?php

use App\Models\District;
use App\Models\Flash;
use App\Models\Fleet;
use App\Models\Member;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2027-01-15 12:00:00'));
});

it('paginates sailor results at 15 per page', function () {
    $district = District::first();
    $fleet = Fleet::where('district_id', District::first()->id)->first();

    // Create 17 users, each with at least one flash
    // The unique constraint is (user_id, date), so different users can share dates
    for ($i = 1; $i <= 17; $i++) {
        $user = User::factory()->create([
            'first_name' => 'Sailor',
            'last_name' => "Number{$i}",
        ]);
        Member::create([
            'user_id' => $user->id,
            'district_id' => $district->id,
            'fleet_id' => $fleet->id,
            'year' => 2027,
        ]);
        Flash::factory()->sailing()->forUser($user)->onDate('2027-01-05')->create();
    }

    $page = visit('/leaderboard');

    // Should show 15 rows on page 1 (table body rows)
    $page->assertScript('document.querySelectorAll("table tbody tr").length >= 15', true);

    // Verify page 1 has exactly 15 rows (pagination caps at 15)
    $page->assertScript('document.querySelectorAll("table tbody tr").length', 15);
});
