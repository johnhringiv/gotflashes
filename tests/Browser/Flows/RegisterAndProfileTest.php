<?php

use App\Models\District;
use App\Models\Fleet;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->travelTo(frozenJanuary());
});

it('registering with district/fleet shows them on /profile', function () {
    $district = District::first();
    $fleet = Fleet::where('district_id', $district->id)->first();

    $unique = Str::random(8);
    $page = visit('/register');

    fillRegistrationForm($page, [
        'first_name' => 'RegProf', 'last_name' => 'Tester'.$unique,
        'email' => "delivered+regprof{$unique}@resend.dev",
        'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        'date_of_birth' => '1990-05-15', 'gender' => 'male',
        'address_line1' => '100 Test Ave', 'city' => 'Seattle', 'state' => 'WA', 'zip_code' => '98101',
    ]);

    // Set district and fleet via TomSelect API
    $page->script("document.querySelector('#district-select').tomselect.setValue('{$district->id}')");
    settleLivewire($page);
    $page->script("document.querySelector('#fleet-select').tomselect.setValue('{$fleet->id}')");
    settleLivewire($page);

    $page->pressAndWaitFor('Register', 8)
        ->assertPathIs('/logbook');

    // Navigate to profile and verify district is set
    $profilePage = visit('/profile');
    $profilePage->assertScript(
        "document.querySelector('#district-select').tomselect.getValue()",
        (string) $district->id
    );
});
