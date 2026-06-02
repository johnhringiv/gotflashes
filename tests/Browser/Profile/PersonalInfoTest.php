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
        'year' => 2027,
    ]);
});

it('renders profile form pre-filled with user data', function () {
    $this->actingAs($this->user);

    $page = visit('/profile');
    $page->assertValue('[wire\\:model\\.blur="first_name"]', 'Jane');
    $page->assertValue('[wire\\:model\\.blur="last_name"]', 'Doe');
    $page->assertValue('[wire\\:model\\.blur="email"]', $this->user->email);
    $page->assertValue('[wire\\:model\\.blur="address_line1"]', '123 Sail St');
    $page->assertValue('[wire\\:model\\.blur="city"]', 'Seattle');
    $page->assertValue('[wire\\:model\\.blur="yacht_club"]', 'Seattle YC');
});

it('shows district/fleet pre-selected in TomSelect', function () {
    $this->actingAs($this->user);

    $page = visit('/profile');
    // TomSelect renders selected values as .item elements inside .ts-control
    $page->assertScript(
        "document.querySelector('#district-select')?.tomselect?.getValue()",
        (string) $this->district->id
    );
});

it('updates first_name and last_name and persists after reload', function () {
    $this->actingAs($this->user);

    $page = visit('/profile');
    $page->fill('[wire\\:model\\.blur="first_name"]', 'Janet')
        ->fill('[wire\\:model\\.blur="last_name"]', 'Smith')
        ->pressAndWaitFor('Save Changes', 2);

    $page->assertSee('updated');

    $page2 = visit('/profile');
    $page2->assertValue('[wire\\:model\\.blur="first_name"]', 'Janet');
    $page2->assertValue('[wire\\:model\\.blur="last_name"]', 'Smith');
});

it('updates address fields', function () {
    $this->actingAs($this->user);

    $page = visit('/profile');
    $page->fill('[wire\\:model\\.blur="address_line1"]', '456 Harbor Dr')
        ->fill('[wire\\:model\\.blur="city"]', 'Portland')
        ->fill('[wire\\:model\\.blur="state"]', 'OR')
        ->fill('[wire\\:model\\.blur="zip_code"]', '97201')
        ->pressAndWaitFor('Save Changes', 2);

    $page->assertSee('updated');

    $this->user->refresh();
    expect($this->user->address_line1)->toBe('456 Harbor Dr');
    expect($this->user->city)->toBe('Portland');
});

it('updates yacht_club', function () {
    $this->actingAs($this->user);

    $page = visit('/profile');
    $page->fill('[wire\\:model\\.blur="yacht_club"]', 'Portland YC')
        ->pressAndWaitFor('Save Changes', 2);

    $page->assertSee('updated');

    $this->user->refresh();
    expect($this->user->yacht_club)->toBe('Portland YC');
});
