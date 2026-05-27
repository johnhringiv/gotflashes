<?php

use App\Models\District;
use App\Models\Fleet;
use App\Models\User;

it('registers a new user with district + fleet and lands on /logbook', function () {
    $district = District::first();
    $fleet = Fleet::where('district_id', $district->id)->first();

    $unique = time();
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

it('registers a new user with district but no fleet', function () {
    $district = District::first();

    $unique = time();
    $page = visit('/register');

    // Wait for TomSelect to initialize
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

    // Set district via TomSelect programmatically
    $page->script("document.getElementById('district-select').tomselect.setValue('{$district->id}')");
    $page->wait(0.3);

    // Skip fleet selection (leave as unaffiliated)

    // Submit the form
    $page->pressAndWaitFor('Register', 8)
        ->assertPathIs('/logbook');
});

it('registers a fully unaffiliated user', function () {
    $unique = time();
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

    // Skip both district and fleet selection (unaffiliated)

    // Remove required from selects that block native validation

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

it('shows verification banner immediately after registration', function () {
    $unique = time();
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

    $page->pressAndWaitFor('Register', 8)
        ->assertPathIs('/logbook')
        ->assertSee('verify');
});

it('rate-limits repeated registration attempts')->todo();
