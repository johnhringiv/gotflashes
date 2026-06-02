<?php

use App\Models\District;
use App\Models\Fleet;
use App\Models\User;
use Illuminate\Support\Str;

/*
| Registration fills many wire:model.live.blur fields. Livewire 4 runs those
| syncs in parallel, so instant fill() can race them and clobber a field
| (lost-update). fillRegistrationForm()/fillLive()/settleLivewire() (in
| tests/Pest.php) serialize each sync — condition-based, no fixed sleeps —
| the way a real user (filling fields seconds apart) naturally would.
*/

it('registers a new user with district + fleet and lands on /logbook', function () {
    $district = District::first();
    $fleet = Fleet::where('district_id', $district->id)->first();

    $unique = Str::random(8);
    $page = visit('/register');

    // Wait for TomSelect to initialize (it fetches data from /api/districts-and-fleets)
    $page->assertPresent('.ts-wrapper');

    fillRegistrationForm($page, [
        'first_name' => 'TestUser', 'last_name' => 'Reg'.$unique,
        'email' => "delivered+reg{$unique}@resend.dev",
        'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        'date_of_birth' => '1990-05-15', 'gender' => 'male',
        'address_line1' => '123 Test Ave', 'city' => 'Testville', 'state' => 'TX', 'zip_code' => '12345',
    ]);

    // Set district + fleet via TomSelect programmatically
    $page->script("document.getElementById('district-select').tomselect.setValue('{$district->id}')");
    settleLivewire($page);
    $page->script("document.getElementById('fleet-select').tomselect.setValue('{$fleet->id}')");
    settleLivewire($page);
    fillLive($page, '[wire\\:model\\.live\\.blur="yacht_club"]', 'Test YC');

    $page->pressAndWaitFor('Register', 8)
        ->assertPathIs('/logbook')
        ->assertSee('Logbook')
        ->assertSee('Logout');
});

it('registers a new user with district and fleet set to None', function () {
    $district = District::first();

    $unique = Str::random(8);
    $page = visit('/register');
    $page->assertPresent('.ts-wrapper');

    fillRegistrationForm($page, [
        'first_name' => 'NoFleet', 'last_name' => 'User'.$unique,
        'email' => "delivered+nofleet{$unique}@resend.dev",
        'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        'date_of_birth' => '1990-05-15', 'gender' => 'female',
        'address_line1' => '456 Test St', 'city' => 'Testville', 'state' => 'CA', 'zip_code' => '90210',
    ]);

    // Set district, then explicitly pick None for fleet
    $page->script("document.getElementById('district-select').tomselect.setValue('{$district->id}')");
    settleLivewire($page);
    $page->script("document.getElementById('fleet-select').tomselect.setValue('none')");
    settleLivewire($page);
    $page->pressAndWaitFor('Register', 8)
        ->assertPathIs('/logbook');
});

it('registers a fully unaffiliated user', function () {
    $unique = Str::random(8);
    $page = visit('/register');

    fillRegistrationForm($page, [
        'first_name' => 'Unaff', 'last_name' => 'User'.$unique,
        'email' => "delivered+unaff{$unique}@resend.dev",
        'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        'date_of_birth' => '1990-05-15', 'gender' => 'prefer_not_to_say',
        'address_line1' => '789 Solo Ln', 'city' => 'Independent', 'state' => 'FL', 'zip_code' => '33101',
    ]);

    // Explicitly select None for both district and fleet (unaffiliated)
    $page->script("document.getElementById('district-select').tomselect.setValue('none')");
    settleLivewire($page);
    $page->script("document.getElementById('fleet-select').tomselect.setValue('none')");
    settleLivewire($page);
    $page->pressAndWaitFor('Register', 8)
        ->assertPathIs('/logbook');
});

it('shows validation error when password and confirmation differ', function () {
    $page = visit('/register');
    trackLivewireRequests($page);

    fillLive($page, '[wire\\:model\\.live\\.blur="password"]', 'Password123!');
    fillLive($page, '[wire\\:model\\.live\\.blur="password_confirmation"]', 'DifferentPassword!');
    $page->assertSee('password')
        ->assertPathIs('/register');
});

it('shows validation error when email is already taken', function () {
    $existing = User::factory()->create();

    $page = visit('/register');
    trackLivewireRequests($page);

    fillLive($page, '[wire\\:model\\.live\\.blur="email"]', $existing->email);
    $page->assertSee('has already been taken');
});

it('shows validation error when date_of_birth is in the future', function () {
    $futureDate = now()->addYear()->format('Y-m-d');

    $page = visit('/register');
    trackLivewireRequests($page);

    fillLive($page, '[wire\\:model\\.live\\.blur="date_of_birth"]', $futureDate);
    $page->assertSee('date of birth');
});

it('requires district and fleet selection on registration', function () {
    $unique = Str::random(8);
    $page = visit('/register');

    fillRegistrationForm($page, [
        'first_name' => 'NoPick', 'last_name' => 'User'.$unique,
        'email' => "delivered+nopick{$unique}@resend.dev",
        'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        'date_of_birth' => '1990-05-15', 'gender' => 'male',
        'address_line1' => '123 Test St', 'city' => 'Testville', 'state' => 'TX', 'zip_code' => '12345',
    ]);

    // Submit without selecting district/fleet
    $page->pressAndWaitFor('Register', 5);

    $page->assertPathIs('/register');
    $page->assertSee('Please select a district');
    $page->assertSee('Please select a fleet');
});

it('requires fleet when district is set on registration', function () {
    $district = District::first();
    $unique = Str::random(8);
    $page = visit('/register');

    fillRegistrationForm($page, [
        'first_name' => 'NoFleet', 'last_name' => 'User'.$unique,
        'email' => "delivered+nfleet{$unique}@resend.dev",
        'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        'date_of_birth' => '1990-05-15', 'gender' => 'male',
        'address_line1' => '123 Test St', 'city' => 'Testville', 'state' => 'TX', 'zip_code' => '12345',
    ]);

    // Set district but skip fleet (fleet auto-clears to null)
    $page->script("document.getElementById('district-select').tomselect.setValue('{$district->id}')");
    settleLivewire($page);
    $page->pressAndWaitFor('Register', 5);
    $page->assertPathIs('/register');
    $page->assertSee('Please select a fleet');
    $page->assertDontSee('Please select a district');
});

it('shows all field errors and district/fleet errors at once on submit', function () {
    $page = visit('/register');

    // Submit with everything empty
    $page->pressAndWaitFor('Register', 5);

    $page->assertSee('Please select a district');
    $page->assertSee('Please select a fleet');
    $page->assertSee('required');
});

it('clears district error when district is selected after error', function () {
    $page = visit('/register');
    trackLivewireRequests($page);

    // Trigger the error
    $page->pressAndWaitFor('Register', 5);
    $page->assertSee('Please select a district');

    // Select a district
    $district = District::first();
    $page->script("document.getElementById('district-select').tomselect.setValue('{$district->id}')");
    settleLivewire($page);
    $page->assertDontSee('Please select a district');
});

it('keeps fleet error visible after picking district (fleet auto-clears, must still pick)', function () {
    $page = visit('/register');
    trackLivewireRequests($page);

    // Trigger both errors
    $page->pressAndWaitFor('Register', 5);
    $page->assertSee('Please select a district');
    $page->assertSee('Please select a fleet');

    // Pick a district — JS auto-clears fleet, but error must stay
    $district = District::first();
    $page->script("document.getElementById('district-select').tomselect.setValue('{$district->id}')");
    settleLivewire($page);
    $page->assertDontSee('Please select a district');
    $page->assertSee('Please select a fleet');
});

it('clears fleet error when user picks a fleet after district', function () {
    $page = visit('/register');
    trackLivewireRequests($page);
    $district = District::first();
    $fleet = Fleet::where('district_id', $district->id)->first();

    // Trigger both errors
    $page->pressAndWaitFor('Register', 5);
    $page->assertSee('Please select a fleet');

    // Pick district then fleet
    $page->script("document.getElementById('district-select').tomselect.setValue('{$district->id}')");
    settleLivewire($page);
    $page->script("document.getElementById('fleet-select').tomselect.setValue('{$fleet->id}')");
    settleLivewire($page);
    $page->assertDontSee('Please select a fleet');
});

it('shows verification banner immediately after registration', function () {
    $unique = Str::random(8);
    $page = visit('/register');

    fillRegistrationForm($page, [
        'first_name' => 'Banner', 'last_name' => 'Test'.$unique,
        'email' => "delivered+banner{$unique}@resend.dev",
        'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        'date_of_birth' => '1990-05-15', 'gender' => 'male',
        'address_line1' => '100 Banner St', 'city' => 'Testville', 'state' => 'NY', 'zip_code' => '10001',
    ]);

    // Explicitly select None for both
    $page->script("document.getElementById('district-select').tomselect.setValue('none')");
    settleLivewire($page);
    $page->script("document.getElementById('fleet-select').tomselect.setValue('none')");
    settleLivewire($page);
    $page->pressAndWaitFor('Register', 8)
        ->assertPathIs('/logbook')
        ->assertSee('verify');
});

it('rate-limits repeated registration attempts')->todo();
