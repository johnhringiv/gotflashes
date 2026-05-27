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

    // Clear both via TomSelect and sync Livewire props
    $page->script("
        document.querySelector('#district-select').tomselect.clear();
        document.querySelector('#fleet-select').tomselect.clear();
        const formEl = document.querySelector('#district-select').closest('[wire\\\\:id]');
        const wireId = formEl.getAttribute('wire:id');
        Livewire.find(wireId).set('district_id', null);
        Livewire.find(wireId).set('fleet_id', null);
    ");
    $page->wait(1);

    // Call save directly via Livewire (TomSelect + wire:model sync unreliable)
    $page->script("
        const formEl = document.querySelector('#district-select').closest('[wire\\\\:id]');
        const wireId = formEl.getAttribute('wire:id');
        Livewire.find(wireId).call('save');
    ");
    // wait for Livewire async round-trip
    $page->wait(3);
    $page->assertSee('updated');

    // Reload and verify
    $page2 = visit('/profile');
    // App auto-sets "none" for unaffiliated (via district-fleet-select.js)
    $page2->assertScript("document.querySelector('#district-select').tomselect.getValue()", 'none');
    $page2->assertScript("document.querySelector('#fleet-select').tomselect.getValue()", 'none');
});
