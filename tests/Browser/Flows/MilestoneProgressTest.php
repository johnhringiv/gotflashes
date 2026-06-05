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
});

it('hitting 10-day milestone displays badge', function () {
    $user = User::factory()->create([

        'first_name' => 'Mile',
        'last_name' => 'Ten',
    ]);
    Member::create([
        'user_id' => $user->id,
        'district_id' => $this->district->id,
        'fleet_id' => $this->fleet->id,
        'year' => 2027,
    ]);

    // Seed 9 flashes
    for ($i = 1; $i <= 9; $i++) {
        $day = str_pad($i, 2, '0', STR_PAD_LEFT);
        Flash::factory()->sailing()->forUser($user)->onDate("2027-01-{$day}")->create();
    }

    $this->actingAs($user);

    // Verify badge is NOT shown with 9 flashes
    $page = visit('/logbook');
    $page->assertMissing('img[alt="10 Day Award"]');

    // Add the 10th flash directly
    Flash::factory()->sailing()->forUser($user)->onDate('2027-01-10')->create();

    // Reload and verify badge appears
    $page2 = visit('/logbook');
    $page2->assertVisible('img[alt="10 Day Award"]');
});

it('hitting 25-day milestone displays badge', function () {
    $user = User::factory()->create([

        'first_name' => 'Mile',
        'last_name' => 'TwentyFive',
    ]);
    Member::create([
        'user_id' => $user->id,
        'district_id' => $this->district->id,
        'fleet_id' => $this->fleet->id,
        'year' => 2027,
    ]);

    $this->actingAs($user);

    // Seed 24 flashes in 2027 (Jan 1-15 + Feb 1-9) — factory bypasses date validation
    for ($i = 1; $i <= 24; $i++) {
        $month = $i <= 15 ? '01' : '02';
        $day = $i <= 15 ? str_pad($i, 2, '0', STR_PAD_LEFT) : str_pad($i - 15, 2, '0', STR_PAD_LEFT);
        Flash::factory()->sailing()->forUser($user)->onDate("2027-{$month}-{$day}")->create();
    }

    $page = visit('/logbook');
    $page->assertVisible('img[alt="10 Day Award"]');
    $page->assertMissing('img[alt="25 Day Award"]');

    // Add the 25th flash
    Flash::factory()->sailing()->forUser($user)->onDate('2027-02-10')->create();

    $page2 = visit('/logbook');
    $page2->assertVisible('img[alt="25 Day Award"]');
});

it('hitting 50-day milestone displays badge and burgee', function () {
    $user = User::factory()->create([

        'first_name' => 'Mile',
        'last_name' => 'Fifty',
    ]);
    Member::create([
        'user_id' => $user->id,
        'district_id' => $this->district->id,
        'fleet_id' => $this->fleet->id,
        'year' => 2027,
    ]);

    // Seed 49 flashes
    for ($i = 1; $i <= 49; $i++) {
        $month = str_pad(intdiv($i - 1, 28) + 1, 2, '0', STR_PAD_LEFT);
        $day = str_pad((($i - 1) % 28) + 1, 2, '0', STR_PAD_LEFT);
        Flash::factory()->sailing()->forUser($user)->onDate("2027-{$month}-{$day}")->create();
    }

    $this->actingAs($user);

    $page = visit('/logbook');
    $page->assertVisible('img[alt="25 Day Award"]');
    $page->assertMissing('img[alt="50 Day Award (Burgee)"]');

    // Add the 50th flash
    Flash::factory()->sailing()->forUser($user)->onDate('2027-03-01')->create();

    $page2 = visit('/logbook');
    $page2->assertVisible('img[alt="50 Day Award (Burgee)"]');
    // The progress card should show "All tiers completed!"
    $page2->assertSee('All tiers completed');
});

it('deleting a flash that crossed threshold removes badge', function () {
    $user = User::factory()->create([

        'first_name' => 'Mile',
        'last_name' => 'Delete',
    ]);
    Member::create([
        'user_id' => $user->id,
        'district_id' => $this->district->id,
        'fleet_id' => $this->fleet->id,
        'year' => 2027,
    ]);

    // Seed exactly 10 flashes
    for ($i = 1; $i <= 10; $i++) {
        $day = str_pad($i, 2, '0', STR_PAD_LEFT);
        Flash::factory()->sailing()->forUser($user)->onDate("2027-01-{$day}")->create();
    }

    $this->actingAs($user);

    // Verify 10-day badge is visible
    $page = visit('/logbook');
    $page->assertVisible('img[alt="10 Day Award"]');

    // Delete one flash to drop below 10
    $flashToDelete = Flash::where('user_id', $user->id)->first();
    $flashToDelete->delete();

    // Reload and verify badge is gone
    $page2 = visit('/logbook');
    $page2->assertMissing('img[alt="10 Day Award"]');
});
