<?php

namespace Tests\Feature\Auth;

use App\Livewire\RegistrationForm;
use App\Models\District;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ValidateOnlyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression test for a Livewire 3 footgun:
     *
     * `validateOnly($field)` with a field name that has no matching rule
     * (e.g. `password_confirmation`, which is only enforced via the
     * `confirmed` rule on `password`) ends up calling `resetErrorBag([])`,
     * which Livewire treats as "reset all" and wipes the entire bag.
     *
     * The fix in `updated()` is to call validateOnly('password') for both
     * password fields, since `confirmed` validates them together.
     */
    public function test_typing_in_password_does_not_clear_other_field_errors(): void
    {
        Livewire::test(RegistrationForm::class)
            ->call('register')
            ->assertHasErrors(['district_id', 'fleet_id', 'first_name', 'email'])
            ->set('password', 'Password123!')
            ->set('password_confirmation', 'Password123!')
            ->assertHasErrors(['district_id', 'fleet_id', 'first_name', 'email']);
    }

    /**
     * When the user picks a district, the district/fleet glue JS auto-clears fleet
     * (setting it to null in Livewire). That should not silently clear
     * the existing fleet error — the user still needs to pick a fleet.
     */
    public function test_picking_district_does_not_clear_fleet_error(): void
    {
        $district = District::first();

        Livewire::test(RegistrationForm::class)
            ->call('register')
            ->assertHasErrors(['district_id', 'fleet_id'])
            // Simulate picking a district + JS auto-clearing fleet
            ->set('district_id', (string) $district->id)
            ->set('fleet_id', null)
            // District error clears (user picked), fleet error stays
            ->assertHasNoErrors('district_id')
            ->assertHasErrors('fleet_id');
    }
}
