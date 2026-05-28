<?php

use App\Models\District;
use App\Models\Fleet;
use App\Models\Member;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2027-01-15 12:00:00'));

    $this->district = District::first();
    $this->fleet = Fleet::where('district_id', $this->district->id)->first();

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
    $page->wait(1);

    // Verify fleet dropdown has options after district selection
    $page->assertScript("Object.keys(document.querySelector('#fleet-select').tomselect.options).length > 0", true);
});

it('auto-selects district when fleet is chosen', function () {
    $this->actingAs($this->user);
    $page = visit('/profile');

    $fleetId = $this->fleet->id;
    $page->script("document.querySelector('#fleet-select').tomselect.setValue('{$fleetId}')");
    $page->wait(1);

    $page->assertScript("document.querySelector('#district-select').tomselect.getValue() !== ''", true);
});

it('lets user set fleet to None', function () {
    Member::create([
        'user_id' => $this->user->id,
        'district_id' => $this->district->id,
        'fleet_id' => $this->fleet->id,
        'year' => 2027,
    ]);

    $this->actingAs($this->user);
    $page = visit('/profile');

    // Verify fleet is initially set
    $page->assertScript("document.querySelector('#fleet-select').tomselect.getValue() !== ''", true);

    // Clear fleet and save — set + call on same reference batches into one request
    $page->script("
        document.querySelector('#fleet-select').tomselect.clear();
        const formEl = document.querySelector('#fleet-select').closest('[wire\\\\:id]');
        const comp = Livewire.find(formEl.getAttribute('wire:id'));
        comp.set('fleet_id', null);
        comp.call('save');
    ");
    $page->wait(3);
    $page->assertSee('updated');

    $member = Member::where('user_id', $this->user->id)->where('year', 2027)->first();
    expect($member->fleet_id)->toBeNull();
});

it('persists unaffiliated choice on save and reload', function () {
    Member::create([
        'user_id' => $this->user->id,
        'district_id' => $this->district->id,
        'fleet_id' => $this->fleet->id,
        'year' => 2027,
    ]);

    $this->actingAs($this->user);
    $page = visit('/profile');

    // User explicitly picks "Unaffiliated/None" for district (which auto-sets fleet to None too)
    $page->script("document.querySelector('#district-select').tomselect.setValue('none')");
    $page->wait(1);
    $page->script("document.querySelector('#fleet-select').tomselect.setValue('none')");
    $page->wait(1);

    $page->script("
        const formEl = document.querySelector('#district-select').closest('[wire\\\\:id]');
        Livewire.find(formEl.getAttribute('wire:id')).call('save');
    ");
    $page->wait(3);
    $page->assertSee('updated');

    // Reload and verify
    $page2 = visit('/profile');
    // App auto-sets "none" for unaffiliated (via district-fleet-select.js)
    $page2->assertScript("document.querySelector('#district-select').tomselect.getValue()", 'none');
    $page2->assertScript("document.querySelector('#fleet-select').tomselect.getValue()", 'none');
});

it('blocks save when district change clears fleet and user does not re-select', function () {
    Member::create([
        'user_id' => $this->user->id,
        'district_id' => $this->district->id,
        'fleet_id' => $this->fleet->id,
        'year' => 2027,
    ]);

    $this->actingAs($this->user);
    $page = visit('/profile');

    // Change to a different district — JS auto-clears fleet to null; server detects this via DB comparison
    $otherDistrict = District::where('id', '!=', $this->district->id)->first();
    $page->script("document.getElementById('district-select').tomselect.setValue('{$otherDistrict->id}')");
    $page->wait(1);

    // Submit without picking a fleet
    $page->script("
        const formEl = document.querySelector('#fleet-select').closest('[wire\\\\:id]');
        Livewire.find(formEl.getAttribute('wire:id')).call('save');
    ");
    $page->wait(2);

    // Fleet error should appear, save should NOT have happened
    $page->assertSee('Please select a fleet');
    $member = Member::where('user_id', $this->user->id)->where('year', 2027)->first();
    expect($member->district_id)->toBe($this->district->id); // unchanged
});

it('clears fleet error when fleet is selected after district change', function () {
    Member::create([
        'user_id' => $this->user->id,
        'district_id' => $this->district->id,
        'fleet_id' => $this->fleet->id,
        'year' => 2027,
    ]);

    $this->actingAs($this->user);
    $page = visit('/profile');

    // Change district to trigger fleet auto-clear
    $otherDistrict = District::where('id', '!=', $this->district->id)->first();
    $page->script("document.getElementById('district-select').tomselect.setValue('{$otherDistrict->id}')");
    $page->wait(1);

    // Trigger error
    $page->script("
        const formEl = document.querySelector('#fleet-select').closest('[wire\\\\:id]');
        Livewire.find(formEl.getAttribute('wire:id')).call('save');
    ");
    $page->wait(2);
    $page->assertSee('Please select a fleet');

    // Now pick None
    $page->script("document.getElementById('fleet-select').tomselect.setValue('none')");
    $page->wait(1);

    $page->assertDontSee('Please select a fleet');
});
