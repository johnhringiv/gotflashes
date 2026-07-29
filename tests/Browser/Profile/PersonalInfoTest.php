<?php

use App\Models\District;
use App\Models\Fleet;
use App\Models\Member;
use App\Models\User;

beforeEach(function () {
    $this->travelTo(frozenJanuary());

    $this->district = District::first();
    $this->fleet = Fleet::where('district_id', $this->district->id)->first();

    $this->user = User::factory()->create([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'address_line1' => '123 Sail St',
        'city' => 'Seattle',
        'state' => 'WA',
        'zip_code' => '98101',
        'yacht_club' => 'Seattle YC',
    ]);
    Member::create([
        'user_id' => $this->user->id,
        'district_id' => $this->district->id,
        'fleet_id' => $this->fleet->id,
        'year' => now()->year,
    ]);
});

it('renders profile form pre-filled with user data', function () {
    $this->actingAs($this->user);

    $page = visit('/profile');
    $page->assertValue('[wire\\:model\\.live\\.blur="first_name"]', 'Jane');
    $page->assertValue('[wire\\:model\\.live\\.blur="last_name"]', 'Doe');
    $page->assertValue('[wire\\:model\\.live\\.blur="email"]', $this->user->email);
    $page->assertValue('[wire\\:model\\.live\\.blur="address_line1"]', '123 Sail St');
    $page->assertValue('[wire\\:model\\.live\\.blur="city"]', 'Seattle');
    $page->assertValue('[wire\\:model\\.live\\.blur="yacht_club"]', 'Seattle YC');
});

it('shows district/fleet pre-selected in the combobox and select', function () {
    $this->actingAs($this->user);

    $page = visit('/profile');
    // The hidden district select still carries the value; selected option is server-rendered
    $page->assertScript(
        "document.getElementById('district-select').value",
        (string) $this->district->id
    );
});

it('updates first_name and last_name and persists after reload', function () {
    $this->actingAs($this->user);

    $page = visit('/profile');
    trackLivewireRequests($page);
    fillLive($page, '[wire\\:model\\.live\\.blur="first_name"]', 'Janet');
    fillLive($page, '[wire\\:model\\.live\\.blur="last_name"]', 'Smith');
    $page->pressAndWaitFor('Save Changes', 2);

    $page->assertSee('updated');

    $page2 = visit('/profile');
    $page2->assertValue('[wire\\:model\\.live\\.blur="first_name"]', 'Janet');
    $page2->assertValue('[wire\\:model\\.live\\.blur="last_name"]', 'Smith');
});

it('updates address fields', function () {
    $this->actingAs($this->user);

    $page = visit('/profile');
    trackLivewireRequests($page);
    fillLive($page, '[wire\\:model\\.live\\.blur="address_line1"]', '456 Harbor Dr');
    fillLive($page, '[wire\\:model\\.live\\.blur="city"]', 'Portland');
    fillLive($page, '[wire\\:model\\.live\\.blur="state"]', 'OR');
    fillLive($page, '[wire\\:model\\.live\\.blur="zip_code"]', '97201');
    $page->pressAndWaitFor('Save Changes', 2);

    $page->assertSee('updated');

    $this->user->refresh();
    expect($this->user->address_line1)->toBe('456 Harbor Dr');
    expect($this->user->city)->toBe('Portland');
});

it('updates yacht_club', function () {
    $this->actingAs($this->user);

    $page = visit('/profile');
    trackLivewireRequests($page);
    fillLive($page, '[wire\\:model\\.live\\.blur="yacht_club"]', 'Portland YC');
    $page->pressAndWaitFor('Save Changes', 2);

    $page->assertSee('updated');

    $this->user->refresh();
    expect($this->user->yacht_club)->toBe('Portland YC');
});
