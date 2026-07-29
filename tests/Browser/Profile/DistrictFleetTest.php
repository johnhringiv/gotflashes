<?php

use App\Models\District;
use App\Models\Fleet;
use App\Models\Member;
use App\Models\User;

beforeEach(function () {
    $this->travelTo(frozenJanuary());

    $this->district = District::where('name', '!=', District::NONE_NAME)->firstOrFail();
    $this->fleet = Fleet::where('district_id', $this->district->id)->firstOrFail();

    $this->user = User::factory()->create([
        'first_name' => 'Fleet',
        'last_name' => 'Tester',
    ]);
});

it('filters fleet list when district is selected', function () {
    $this->actingAs($this->user);
    $page = visit('/profile');

    $districtId = $this->district->id;
    $page->script("document.querySelector('#district-select').tomselect.setValue('{$districtId}')");
    // Verify fleet dropdown has options after district selection
    $page->assertScript("Object.keys(document.querySelector('#fleet-select').tomselect.options).length > 0", true);
});

it('auto-selects district when fleet is chosen', function () {
    $this->actingAs($this->user);
    $page = visit('/profile');

    $fleetId = $this->fleet->id;
    $page->script("document.querySelector('#fleet-select').tomselect.setValue('{$fleetId}')");
    $page->assertScript("document.querySelector('#district-select').tomselect.getValue() !== ''", true);
});

it('lets user set fleet to None', function () {
    Member::create([
        'user_id' => $this->user->id,
        'district_id' => $this->district->id,
        'fleet_id' => $this->fleet->id,
        'year' => now()->year,
    ]);

    $this->actingAs($this->user);
    $page = visit('/profile');

    // Verify fleet is initially set
    $page->assertScript("document.querySelector('#fleet-select').tomselect.getValue() !== ''", true);

    // Pick the None fleet and save — None is a real row, not a cleared value
    $noneFleetId = Fleet::noneId();
    $page->script("document.querySelector('#fleet-select').tomselect.setValue('{$noneFleetId}')");
    $page->wait(1);
    $page->script("
        const formEl = document.querySelector('#fleet-select').closest('[wire\\\\:id]');
        Livewire.find(formEl.getAttribute('wire:id')).call('save');
    ");
    $page->assertSee('updated');

    $member = Member::where('user_id', $this->user->id)->where('year', now()->year)->first();
    expect($member->fleet_id)->toBe(Fleet::noneId());
});

it('persists unaffiliated choice on save and reload', function () {
    Member::create([
        'user_id' => $this->user->id,
        'district_id' => $this->district->id,
        'fleet_id' => $this->fleet->id,
        'year' => now()->year,
    ]);

    $this->actingAs($this->user);
    $page = visit('/profile');

    // User explicitly picks "Unaffiliated/None" for district and fleet
    $noneDistrictId = District::noneId();
    $noneFleetId = Fleet::noneId();
    $page->script("document.querySelector('#district-select').tomselect.setValue('{$noneDistrictId}')");
    $page->wait(1);
    $page->script("document.querySelector('#fleet-select').tomselect.setValue('{$noneFleetId}')");
    $page->wait(1);

    $page->script("
        const formEl = document.querySelector('#district-select').closest('[wire\\\\:id]');
        Livewire.find(formEl.getAttribute('wire:id')).call('save');
    ");
    $page->assertSee('updated');

    // Reload and verify the saved None ids round-trip
    $page2 = visit('/profile');
    $page2->assertScript("document.querySelector('#district-select').tomselect.getValue()", (string) District::noneId());
    $page2->assertScript("document.querySelector('#fleet-select').tomselect.getValue()", (string) Fleet::noneId());
});

it('blocks save when district change clears fleet and user does not re-select', function () {
    Member::create([
        'user_id' => $this->user->id,
        'district_id' => $this->district->id,
        'fleet_id' => $this->fleet->id,
        'year' => now()->year,
    ]);

    $this->actingAs($this->user);
    $page = visit('/profile');

    // Change to a different district — JS auto-clears fleet to null; server detects this via DB comparison
    $otherDistrict = District::where('id', '!=', $this->district->id)
        ->where('name', '!=', District::NONE_NAME)->firstOrFail();
    $page->script("document.getElementById('district-select').tomselect.setValue('{$otherDistrict->id}')");
    $page->wait(1);

    // Submit without picking a fleet
    $page->script("
        const formEl = document.querySelector('#fleet-select').closest('[wire\\\\:id]');
        Livewire.find(formEl.getAttribute('wire:id')).call('save');
    ");
    // Fleet error should appear, save should NOT have happened
    $page->assertSee('Please select a fleet');
    $member = Member::where('user_id', $this->user->id)->where('year', now()->year)->first();
    expect($member->district_id)->toBe($this->district->id); // unchanged
});

it('clears fleet error when fleet is selected after district change', function () {
    Member::create([
        'user_id' => $this->user->id,
        'district_id' => $this->district->id,
        'fleet_id' => $this->fleet->id,
        'year' => now()->year,
    ]);

    $this->actingAs($this->user);
    $page = visit('/profile');

    // Change district to trigger fleet auto-clear
    $otherDistrict = District::where('id', '!=', $this->district->id)
        ->where('name', '!=', District::NONE_NAME)->firstOrFail();
    $page->script("document.getElementById('district-select').tomselect.setValue('{$otherDistrict->id}')");
    $page->wait(1);

    // Trigger error
    $page->script("
        const formEl = document.querySelector('#fleet-select').closest('[wire\\\\:id]');
        Livewire.find(formEl.getAttribute('wire:id')).call('save');
    ");
    $page->assertSee('Please select a fleet');

    // Now pick None (a real fleet row — live validation passes and clears the error)
    $noneFleetId = Fleet::noneId();
    $page->script("document.getElementById('fleet-select').tomselect.setValue('{$noneFleetId}')");
    $page->assertDontSee('Please select a fleet');
});
