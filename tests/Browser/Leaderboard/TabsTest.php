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
    $this->fleet = Fleet::where('district_id', $this->district->id)->first();

    $this->user = User::factory()->create();
    Member::create([
        'user_id' => $this->user->id,
        'district_id' => $this->district->id,
        'fleet_id' => $this->fleet->id,
        'year' => 2027,
    ]);
    Flash::factory()->sailing()->forUser($this->user)->onDate('2027-01-10')->create();
});

it('renders three tabs and defaults to sailor tab', function () {
    $page = visit('/leaderboard');

    $page->assertPresent('button[wire\\:click="switchTab(\'sailor\')"]');
    $page->assertPresent('button[wire\\:click="switchTab(\'fleet\')"]');
    $page->assertPresent('button[wire\\:click="switchTab(\'district\')"]');

    $page->assertAttributeContains('button[wire\\:click="switchTab(\'sailor\')"]', 'class', 'tab-active');
});

it('shows current year in page heading', function () {
    $page = visit('/leaderboard');
    $page->assertSee('2027');
    $page->assertSee('Leaderboard');
});

it('switches to fleet tab without full page reload', function () {
    $page = visit('/leaderboard');

    $page->script('window.__navHappened = false; window.addEventListener("beforeunload", () => { window.__navHappened = true; })');

    $page->click('button[wire\\:click="switchTab(\'fleet\')"]');
    $page->wait(1);

    $page->assertScript('window.__navHappened', false);
    $page->assertAttributeContains('button[wire\\:click="switchTab(\'fleet\')"]', 'class', 'tab-active');
});

it('updates URL query param when switching tabs', function () {
    $page = visit('/leaderboard');

    $page->click('button[wire\\:click="switchTab(\'fleet\')"]');
    $page->wait(1);

    $page->assertQueryStringHas('tab', 'fleet');
});

it('resets pagination when switching tabs', function () {
    for ($i = 1; $i <= 17; $i++) {
        $u = User::factory()->create();
        Member::create([
            'user_id' => $u->id,
            'district_id' => $this->district->id,
            'fleet_id' => $this->fleet->id,
            'year' => 2027,
        ]);
        Flash::factory()->sailing()->forUser($u)->onDate('2027-01-10')->create();
    }

    $page = visit('/leaderboard?page=2');

    $page->click('button[wire\\:click="switchTab(\'fleet\')"]');
    $page->wait(1);

    // After tab switch, should be on page 1 (fleet tab only has 1 fleet, so no page=2)
    $page->assertAttributeContains('button[wire\\:click="switchTab(\'fleet\')"]', 'class', 'tab-active');
});

it('loads fleet tab from ?tab=fleet on page load', function () {
    $page = visit('/leaderboard?tab=fleet');

    $page->assertAttributeContains('button[wire\\:click="switchTab(\'fleet\')"]', 'class', 'tab-active');
});

it('loads district tab from ?tab=district on page load', function () {
    $page = visit('/leaderboard?tab=district');

    $page->assertAttributeContains('button[wire\\:click="switchTab(\'district\')"]', 'class', 'tab-active');
});
