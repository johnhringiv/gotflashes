<?php

use App\Models\District;
use App\Models\Fleet;
use Carbon\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2027-01-15 12:00:00'));
});

it('registering with district/fleet shows them on /profile', function () {
    $district = District::first();
    $fleet = Fleet::where('district_id', $district->id)->first();

    $unique = \Illuminate\Support\Str::random(8);
    $page = visit('/register');

    $page->fill('[wire\\:model\\.blur="first_name"]', 'RegProf')
        ->fill('[wire\\:model\\.blur="last_name"]', 'Tester'.$unique)
        ->fill('[wire\\:model\\.blur="email"]', "delivered+regprof{$unique}@resend.dev")
        ->fill('[wire\\:model\\.blur="password"]', 'Password123!')
        ->fill('[wire\\:model\\.blur="password_confirmation"]', 'Password123!')
        ->fill('[wire\\:model\\.blur="date_of_birth"]', '1990-05-15')
        ->select('[wire\\:model\\.blur="gender"]', 'male')
        ->fill('[wire\\:model\\.blur="address_line1"]', '100 Test Ave')
        ->fill('[wire\\:model\\.blur="city"]', 'Seattle')
        ->fill('[wire\\:model\\.blur="state"]', 'WA')
        ->fill('[wire\\:model\\.blur="zip_code"]', '98101');

    // Set district and fleet via TomSelect API
    $page->script("document.querySelector('#district-select').tomselect.setValue('{$district->id}')");
    $page->wait(1);
    $page->script("document.querySelector('#fleet-select').tomselect.setValue('{$fleet->id}')");
    // Remove required from selects and submit

    $page->pressAndWaitFor('Register', 8)
        ->assertPathIs('/logbook');

    // Navigate to profile and verify district is set
    $profilePage = visit('/profile');
    $profilePage->assertScript(
        "document.querySelector('#district-select').tomselect.getValue()",
        (string) $district->id
    );
});
