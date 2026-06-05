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

it('ranks user with more sailing days higher when totals tie', function () {
    // User A: 15 sailing days = total 15
    $userA = User::factory()->create([

        'first_name' => 'AllSail',
        'last_name' => 'Alice',
    ]);
    Member::create([
        'user_id' => $userA->id,
        'district_id' => $this->district->id,
        'fleet_id' => $this->fleet->id,
        'year' => 2027,
    ]);
    for ($i = 1; $i <= 15; $i++) {
        $day = str_pad($i, 2, '0', STR_PAD_LEFT);
        Flash::factory()->sailing()->forUser($userA)->onDate("2027-01-{$day}")->create();
    }

    // User B: 10 sailing + 5 maintenance = total 15 (same total, fewer sailing days)
    $userB = User::factory()->create([

        'first_name' => 'MixedType',
        'last_name' => 'Bob',
    ]);
    Member::create([
        'user_id' => $userB->id,
        'district_id' => $this->district->id,
        'fleet_id' => $this->fleet->id,
        'year' => 2027,
    ]);
    for ($i = 1; $i <= 10; $i++) {
        $day = str_pad($i, 2, '0', STR_PAD_LEFT);
        Flash::factory()->sailing()->forUser($userB)->onDate("2027-01-{$day}")->create();
    }
    for ($i = 1; $i <= 5; $i++) {
        $day = str_pad($i + 10, 2, '0', STR_PAD_LEFT);
        Flash::factory()->maintenance()->forUser($userB)->onDate("2027-01-{$day}")->create();
    }

    $page = visit('/leaderboard');

    // AllSail Alice should rank higher (more sailing days as tie-breaker)
    $firstRowText = $page->text('table tbody tr:first-child');
    expect($firstRowText)->toContain('AllSail');
});

it('ranks user with earlier first-entry higher when totals and sailing tie', function () {
    // User Early: 5 sailing, created_at early
    $userEarly = User::factory()->create([

        'first_name' => 'EarlyBird',
        'last_name' => 'Eve',
    ]);
    Member::create([
        'user_id' => $userEarly->id,
        'district_id' => $this->district->id,
        'fleet_id' => $this->fleet->id,
        'year' => 2027,
    ]);
    for ($i = 1; $i <= 5; $i++) {
        $day = str_pad($i, 2, '0', STR_PAD_LEFT);
        Flash::factory()->sailing()->forUser($userEarly)->onDate("2027-01-{$day}")->create([
            'created_at' => Carbon::parse('2027-01-01 08:00:00'),
        ]);
    }

    // User Late: 5 sailing, created_at late
    $userLate = User::factory()->create([

        'first_name' => 'LateComer',
        'last_name' => 'Larry',
    ]);
    Member::create([
        'user_id' => $userLate->id,
        'district_id' => $this->district->id,
        'fleet_id' => $this->fleet->id,
        'year' => 2027,
    ]);
    for ($i = 1; $i <= 5; $i++) {
        $day = str_pad($i, 2, '0', STR_PAD_LEFT);
        Flash::factory()->sailing()->forUser($userLate)->onDate("2027-01-{$day}")->create([
            'created_at' => Carbon::parse('2027-01-10 08:00:00'),
        ]);
    }

    $page = visit('/leaderboard');

    // EarlyBird should rank higher (earlier first_entry_date)
    $firstRowText = $page->text('table tbody tr:first-child');
    expect($firstRowText)->toContain('EarlyBird');
});

it('falls back to alphabetical when everything ties', function () {
    // Two users with identical flash counts, same created_at
    $userAlpha = User::factory()->create([

        'first_name' => 'Alpha',
        'last_name' => 'Anderson',
    ]);
    Member::create([
        'user_id' => $userAlpha->id,
        'district_id' => $this->district->id,
        'fleet_id' => $this->fleet->id,
        'year' => 2027,
    ]);
    Flash::factory()->sailing()->forUser($userAlpha)->onDate('2027-01-05')->create([
        'created_at' => Carbon::parse('2027-01-05 12:00:00'),
    ]);

    $userBeta = User::factory()->create([

        'first_name' => 'Beta',
        'last_name' => 'Brown',
    ]);
    Member::create([
        'user_id' => $userBeta->id,
        'district_id' => $this->district->id,
        'fleet_id' => $this->fleet->id,
        'year' => 2027,
    ]);
    Flash::factory()->sailing()->forUser($userBeta)->onDate('2027-01-05')->create([
        'created_at' => Carbon::parse('2027-01-05 12:00:00'),
    ]);

    $page = visit('/leaderboard');

    // Alpha should rank higher (alphabetical by first_name)
    $firstRowText = $page->text('table tbody tr:first-child');
    expect($firstRowText)->toContain('Alpha');
});
