<?php

use App\Models\District;
use App\Models\Fleet;
use App\Models\Member;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->district = District::first();
    $this->fleet = Fleet::where('district_id', $this->district->id)->first();
    Member::create([
        'user_id' => $this->user->id,
        'district_id' => $this->district->id,
        'fleet_id' => $this->fleet->id,
        'year' => now()->year,
    ]);
    $this->actingAs($this->user);
});

describe('TomSelect on profile', function () {
    it('renders both selects with data from /api/districts-and-fleets', function () {
        $page = visit('/profile');
        $page->assertScript('document.querySelectorAll(".ts-wrapper .ts-control").length', 2);
    });

    it('district dropdown shows options when clicked', function () {
        $page = visit('/profile');
        $page->script("document.querySelector('#district-select').tomselect.open()");
        $page->assertScript('document.querySelectorAll(".ts-dropdown-content .option").length > 0', true);
    });

    it('fleet dropdown shows options when clicked', function () {
        $page = visit('/profile');
        $page->script("document.querySelector('#fleet-select').tomselect.open()");
        $page->assertScript('document.querySelectorAll(".ts-dropdown-content .option").length > 0', true);
    });

    it('filters fleet list when district is selected', function () {
        $page = visit('/profile');
        $districtId = $this->district->id;
        $page->script("document.querySelector('#district-select').tomselect.setValue('{$districtId}')");
        $page->wait(1);
        $page->script("document.querySelector('#fleet-select').tomselect.open()");
        $page->assertScript('document.querySelectorAll(".ts-dropdown-content .option").length > 0', true);
    });

    it('auto-selects district when fleet is chosen', function () {
        $page = visit('/profile');
        $fleetId = $this->fleet->id;
        $page->script("document.querySelector('#fleet-select').tomselect.setValue('{$fleetId}')");
        $page->wait(1);
        $page->assertScript(
            "document.querySelector('#district-select').tomselect.getValue() !== ''",
            true
        );
    });
});

describe('TomSelect on registration', function () {
    it('shows placeholder for both selects on registration form', function () {
        // Need to be anonymous to visit /register (guest middleware)
        auth()->logout();
        $page = visit('/register');
        $page->assertScript('document.querySelectorAll(".ts-wrapper").length', 2);
    });
});
