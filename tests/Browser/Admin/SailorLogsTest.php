<?php

use App\Models\District;
use App\Models\Flash;
use App\Models\Fleet;
use App\Models\Member;
use App\Models\User;

beforeEach(function () {
    $this->travelTo(frozenJanuary());

    $this->admin = User::factory()->create([

        'first_name' => 'Logs',
        'last_name' => 'Admin',
    ]);
    $this->admin->is_admin = true;
    $this->admin->save();

    $this->districtA = District::first();
    $this->districtB = District::skip(1)->first();

    $this->fleetA = Fleet::where('district_id', $this->districtA->id)->first();
    $this->fleetB = Fleet::where('district_id', $this->districtB->id)->first();

    $this->sailorA = User::factory()->create([

        'first_name' => 'Alpha',
        'last_name' => 'Sailor',
    ]);
    Member::create([
        'user_id' => $this->sailorA->id,
        'district_id' => $this->districtA->id,
        'fleet_id' => $this->fleetA->id,
        'year' => now()->year,
    ]);
    Flash::factory()->sailing()->forUser($this->sailorA)->onDate(testDate(5))->create();

    $this->sailorB = User::factory()->create([

        'first_name' => 'Bravo',
        'last_name' => 'Captain',
    ]);
    Member::create([
        'user_id' => $this->sailorB->id,
        'district_id' => $this->districtB->id,
        'fleet_id' => $this->fleetB->id,
        'year' => now()->year,
    ]);
    Flash::factory()->sailing()->forUser($this->sailorB)->onDate(testDate(6))->create();
});

it('renders table with filters', function () {
    $this->actingAs($this->admin);

    $page = visit('/admin/sailor-logs');

    $page->assertSee('Sailor Logs');
    $page->assertSee('Alpha Sailor');
    $page->assertSee('Bravo Captain');
    $page->assertVisible('select[wire\\:model\\.live="selectedYear"]');
    $page->assertPresent('#sailor-logs-district-select');
    $page->assertPresent('#sailor-logs-fleet-select');
    $page->assertVisible('input[wire\\:model\\.live\\.debounce\\.300ms="searchQuery"]');
});

it('clears fleet filter when district changes', function () {
    $this->actingAs($this->admin);

    $page = visit('/admin/sailor-logs');

    // Set fleet first
    $page->script("document.getElementById('sailor-logs-fleet-select')._combobox.setValue('{$this->fleetA->id}')");
    $page->wait(1);

    // Now change district - should clear fleet
    $page->script(selectNativeJs('sailor-logs-district-select', $this->districtB->id));
    // Fleet should be cleared - both the hidden select and the visible input
    $page->assertScript("document.getElementById('sailor-logs-fleet-select').value", '');
    $page->assertScript("document.getElementById('sailor-logs-fleet-select-input').value", '');
});

it('clears all filters via Clear button', function () {
    $this->actingAs($this->admin);

    $page = visit('/admin/sailor-logs');

    // Apply a search filter
    $page->fill('input[wire\\:model\\.live\\.debounce\\.300ms="searchQuery"]', 'Alpha');
    $page->assertDontSee('Bravo Captain');

    // Click "Clear Filters"
    $page->click('Clear Filters');
    // Both sailors should now be visible
    $page->assertSee('Alpha Sailor');
    $page->assertSee('Bravo Captain');
});

it('searches by sailor name', function () {
    $this->actingAs($this->admin);

    $page = visit('/admin/sailor-logs');

    $page->fill('input[wire\\:model\\.live\\.debounce\\.300ms="searchQuery"]', 'Bravo');
    $page->assertSee('Bravo Captain');
    $page->assertDontSee('Alpha Sailor');
});
