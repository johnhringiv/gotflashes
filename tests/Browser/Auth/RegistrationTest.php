<?php

use App\Models\District;
use App\Models\Fleet;
use App\Models\User;

it('registers a new user with district + fleet and lands on /logbook', function () {
    $district = District::first();
    $fleet = Fleet::where('district_id', $district->id)->first();

    $unique = \Illuminate\Support\Str::random(8);
    $page = visit('/register');

    // Wait for TomSelect to initialize (it fetches data from /api/districts-and-fleets)
    $page->assertPresent('.ts-wrapper');

    // Fill personal info
    $page->fill('[wire\\:model\\.blur="first_name"]', 'TestUser')
        ->fill('[wire\\:model\\.blur="last_name"]', 'Reg'.$unique)
        ->fill('[wire\\:model\\.blur="email"]', "delivered+reg{$unique}@resend.dev")
        ->fill('[wire\\:model\\.blur="password"]', 'Password123!')
        ->fill('[wire\\:model\\.blur="password_confirmation"]', 'Password123!')
        ->fill('[wire\\:model\\.blur="date_of_birth"]', '1990-05-15')
        ->select('[wire\\:model\\.blur="gender"]', 'male')
        ->fill('[wire\\:model\\.blur="address_line1"]', '123 Test Ave')
        ->fill('[wire\\:model\\.blur="city"]', 'Testville')
        ->fill('[wire\\:model\\.blur="state"]', 'TX')
        ->fill('[wire\\:model\\.blur="zip_code"]', '12345');

    // Set district via TomSelect programmatically
    $page->script("document.getElementById('district-select').tomselect.setValue('{$district->id}')");
    $page->wait(0.3);

    // Set fleet via TomSelect programmatically
    $page->script("document.getElementById('fleet-select').tomselect.setValue('{$fleet->id}')");
    $page->wait(0.3);

    // Fill optional yacht club
    $page->fill('[wire\\:model\\.blur="yacht_club"]', 'Test YC');

    // Submit the form
    $page->pressAndWaitFor('Register', 8)
        ->assertPathIs('/logbook')
        ->assertSee('Logbook')
        ->assertSee('Logout');
});

it('registers a new user with district and fleet set to None', function () {
    $district = District::first();

    $unique = \Illuminate\Support\Str::random(8);
    $page = visit('/register');

    $page->assertPresent('.ts-wrapper');

    $page->fill('[wire\\:model\\.blur="first_name"]', 'NoFleet')
        ->fill('[wire\\:model\\.blur="last_name"]', 'User'.$unique)
        ->fill('[wire\\:model\\.blur="email"]', "delivered+nofleet{$unique}@resend.dev")
        ->fill('[wire\\:model\\.blur="password"]', 'Password123!')
        ->fill('[wire\\:model\\.blur="password_confirmation"]', 'Password123!')
        ->fill('[wire\\:model\\.blur="date_of_birth"]', '1990-05-15')
        ->select('[wire\\:model\\.blur="gender"]', 'female')
        ->fill('[wire\\:model\\.blur="address_line1"]', '456 Test St')
        ->fill('[wire\\:model\\.blur="city"]', 'Testville')
        ->fill('[wire\\:model\\.blur="state"]', 'CA')
        ->fill('[wire\\:model\\.blur="zip_code"]', '90210');

    // Set district, then explicitly pick None for fleet
    $page->script("document.getElementById('district-select').tomselect.setValue('{$district->id}')");
    $page->wait(0.3);
    $page->script("document.getElementById('fleet-select').tomselect.setValue('none')");
    $page->wait(0.3);

    $page->pressAndWaitFor('Register', 8)
        ->assertPathIs('/logbook');
});

it('registers a fully unaffiliated user', function () {
    $unique = \Illuminate\Support\Str::random(8);
    $page = visit('/register');

    $page->fill('[wire\\:model\\.blur="first_name"]', 'Unaff')
        ->fill('[wire\\:model\\.blur="last_name"]', 'User'.$unique)
        ->fill('[wire\\:model\\.blur="email"]', "delivered+unaff{$unique}@resend.dev")
        ->fill('[wire\\:model\\.blur="password"]', 'Password123!')
        ->fill('[wire\\:model\\.blur="password_confirmation"]', 'Password123!')
        ->fill('[wire\\:model\\.blur="date_of_birth"]', '1990-05-15')
        ->select('[wire\\:model\\.blur="gender"]', 'prefer_not_to_say')
        ->fill('[wire\\:model\\.blur="address_line1"]', '789 Solo Ln')
        ->fill('[wire\\:model\\.blur="city"]', 'Independent')
        ->fill('[wire\\:model\\.blur="state"]', 'FL')
        ->fill('[wire\\:model\\.blur="zip_code"]', '33101');

    // Explicitly select None for both district and fleet (unaffiliated)
    $page->script("document.getElementById('district-select').tomselect.setValue('none')");
    $page->wait(0.3);
    $page->script("document.getElementById('fleet-select').tomselect.setValue('none')");
    $page->wait(0.3);

    $page->pressAndWaitFor('Register', 8)
        ->assertPathIs('/logbook');
});

it('shows validation error when password and confirmation differ', function () {
    $page = visit('/register');

    $page->fill('[wire\\:model\\.blur="password"]', 'Password123!')
        ->fill('[wire\\:model\\.blur="password_confirmation"]', 'DifferentPassword!')
        // Click elsewhere to trigger blur validation
        ->click('[wire\\:model\\.blur="first_name"]')
        ->assertSee('password')
        ->assertPathIs('/register');
});

it('shows validation error when email is already taken', function () {
    $existing = User::factory()->create();

    $page = visit('/register');
    $page->fill('[wire\\:model\\.blur="email"]', $existing->email)
        ->click('[wire\\:model\\.blur="first_name"]')
        ->wait(1)
        ->assertSee('has already been taken');
});

it('shows validation error when date_of_birth is in the future', function () {
    $futureDate = now()->addYear()->format('Y-m-d');

    $page = visit('/register');
    $page->fill('[wire\\:model\\.blur="date_of_birth"]', $futureDate)
        // Click elsewhere to trigger blur validation
        ->click('[wire\\:model\\.blur="first_name"]')
        ->assertSee('date of birth');
});

it('requires district and fleet selection on registration', function () {
    $unique = \Illuminate\Support\Str::random(8);
    $page = visit('/register');

    // Fill all required fields except district/fleet
    $page->fill('[wire\\:model\\.blur="first_name"]', 'NoPick')
        ->fill('[wire\\:model\\.blur="last_name"]', 'User'.$unique)
        ->fill('[wire\\:model\\.blur="email"]', "delivered+nopick{$unique}@resend.dev")
        ->fill('[wire\\:model\\.blur="password"]', 'Password123!')
        ->fill('[wire\\:model\\.blur="password_confirmation"]', 'Password123!')
        ->fill('[wire\\:model\\.blur="date_of_birth"]', '1990-05-15')
        ->select('[wire\\:model\\.blur="gender"]', 'male')
        ->fill('[wire\\:model\\.blur="address_line1"]', '123 Test St')
        ->fill('[wire\\:model\\.blur="city"]', 'Testville')
        ->fill('[wire\\:model\\.blur="state"]', 'TX')
        ->fill('[wire\\:model\\.blur="zip_code"]', '12345');

    // Submit without selecting district/fleet
    $page->pressAndWaitFor('Register', 5);

    // Should stay on /register with error messages for district and fleet
    $page->assertPathIs('/register');
    $page->assertSee('Please select a district');
    $page->assertSee('Please select a fleet');
});

it('requires fleet when district is set on registration', function () {
    $district = District::first();
    $unique = \Illuminate\Support\Str::random(8);
    $page = visit('/register');

    $page->fill('[wire\\:model\\.blur="first_name"]', 'NoFleet')
        ->fill('[wire\\:model\\.blur="last_name"]', 'User'.$unique)
        ->fill('[wire\\:model\\.blur="email"]', "delivered+nfleet{$unique}@resend.dev")
        ->fill('[wire\\:model\\.blur="password"]', 'Password123!')
        ->fill('[wire\\:model\\.blur="password_confirmation"]', 'Password123!')
        ->fill('[wire\\:model\\.blur="date_of_birth"]', '1990-05-15')
        ->select('[wire\\:model\\.blur="gender"]', 'male')
        ->fill('[wire\\:model\\.blur="address_line1"]', '123 Test St')
        ->fill('[wire\\:model\\.blur="city"]', 'Testville')
        ->fill('[wire\\:model\\.blur="state"]', 'TX')
        ->fill('[wire\\:model\\.blur="zip_code"]', '12345');

    // Set district but skip fleet (fleet auto-clears to null)
    $page->script("document.getElementById('district-select').tomselect.setValue('{$district->id}')");
    $page->wait(0.5);

    $page->pressAndWaitFor('Register', 5);
    $page->assertPathIs('/register');
    $page->assertSee('Please select a fleet');
    $page->assertDontSee('Please select a district');
});

it('shows all field errors and district/fleet errors at once on submit', function () {
    $page = visit('/register');

    // Submit with everything empty
    $page->pressAndWaitFor('Register', 5);

    // Should see both Livewire field errors AND district/fleet errors simultaneously
    $page->assertSee('Please select a district');
    $page->assertSee('Please select a fleet');
    // Standard Livewire validation errors should also appear (e.g., email required)
    $page->assertSee('required');
});

it('clears district error when district is selected after error', function () {
    $page = visit('/register');

    // Trigger the error
    $page->pressAndWaitFor('Register', 5);
    $page->assertSee('Please select a district');

    // Select a district
    $district = District::first();
    $page->script("document.getElementById('district-select').tomselect.setValue('{$district->id}')");
    $page->wait(1);

    // District error should clear
    $page->assertDontSee('Please select a district');
});

it('keeps fleet error visible after picking district (fleet auto-clears, must still pick)', function () {
    $page = visit('/register');

    // Trigger both errors
    $page->pressAndWaitFor('Register', 5);
    $page->assertSee('Please select a district');
    $page->assertSee('Please select a fleet');

    // Pick a district — JS auto-clears fleet, but error must stay
    $district = District::first();
    $page->script("document.getElementById('district-select').tomselect.setValue('{$district->id}')");
    $page->wait(1);

    $page->assertDontSee('Please select a district');
    $page->assertSee('Please select a fleet');
});

it('clears fleet error when user picks a fleet after district', function () {
    $page = visit('/register');
    $district = District::first();
    $fleet = Fleet::where('district_id', $district->id)->first();

    // Trigger both errors
    $page->pressAndWaitFor('Register', 5);
    $page->assertSee('Please select a fleet');

    // Pick district then fleet
    $page->script("document.getElementById('district-select').tomselect.setValue('{$district->id}')");
    $page->wait(1);
    $page->script("document.getElementById('fleet-select').tomselect.setValue('{$fleet->id}')");
    $page->wait(1);

    $page->assertDontSee('Please select a fleet');
});

it('shows verification banner immediately after registration', function () {
    $unique = \Illuminate\Support\Str::random(8);
    $page = visit('/register');

    $page->fill('[wire\\:model\\.blur="first_name"]', 'Banner')
        ->fill('[wire\\:model\\.blur="last_name"]', 'Test'.$unique)
        ->fill('[wire\\:model\\.blur="email"]', "delivered+banner{$unique}@resend.dev")
        ->fill('[wire\\:model\\.blur="password"]', 'Password123!')
        ->fill('[wire\\:model\\.blur="password_confirmation"]', 'Password123!')
        ->fill('[wire\\:model\\.blur="date_of_birth"]', '1990-05-15')
        ->select('[wire\\:model\\.blur="gender"]', 'male')
        ->fill('[wire\\:model\\.blur="address_line1"]', '100 Banner St')
        ->fill('[wire\\:model\\.blur="city"]', 'Testville')
        ->fill('[wire\\:model\\.blur="state"]', 'NY')
        ->fill('[wire\\:model\\.blur="zip_code"]', '10001');

    // Explicitly select None for both
    $page->script("document.getElementById('district-select').tomselect.setValue('none')");
    $page->wait(0.3);
    $page->script("document.getElementById('fleet-select').tomselect.setValue('none')");
    $page->wait(0.3);

    $page->pressAndWaitFor('Register', 8)
        ->assertPathIs('/logbook')
        ->assertSee('verify');
});

it('rate-limits repeated registration attempts')->todo();
