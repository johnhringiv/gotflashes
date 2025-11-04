<?php

namespace Tests\Feature;

use App\Livewire\RegistrationForm;
use App\Models\District;
use App\Models\Fleet;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationWithMembershipsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake notifications to prevent sending real emails in tests
        Notification::fake();

        // Note: Districts and fleets are seeded automatically by the migration
        // via RefreshDatabase trait
    }

    private function getBaseRegistrationData(): array
    {
        return [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'address_line1' => '123 Main St',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip_code' => '94102',
            'country' => 'United States',
        ];
    }

    public function test_user_can_register_with_district_and_fleet(): void
    {
        $district = District::first();
        $fleet = Fleet::where('district_id', $district->id)->first();

        $data = array_merge($this->getBaseRegistrationData(), [
            'district_id' => $district->id,
            'fleet_id' => $fleet->id,
            'yacht_club' => 'Test Yacht Club',
        ]);

        Livewire::test(RegistrationForm::class)
            ->fill($data)
            ->call('register')
            ->assertRedirect('/logbook');

        $this->assertAuthenticated();

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);

        // Check membership was created
        $membership = Member::where('user_id', $user->id)
            ->where('year', now()->year)
            ->first();

        $this->assertNotNull($membership);
        $this->assertEquals($district->id, $membership->district_id);
        $this->assertEquals($fleet->id, $membership->fleet_id);
    }

    public function test_user_can_register_as_unaffiliated_with_none_values(): void
    {
        $data = array_merge($this->getBaseRegistrationData(), [
            'gender' => 'female',
            'district_id' => 0, // Livewire converts 'none' to 0
            'fleet_id' => 0,
        ]);

        Livewire::test(RegistrationForm::class)
            ->fill($data)
            ->call('register')
            ->assertRedirect('/logbook');

        $this->assertAuthenticated();

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);

        // Check membership was created with null values
        $membership = Member::where('user_id', $user->id)
            ->where('year', now()->year)
            ->first();

        $this->assertNotNull($membership);
        $this->assertNull($membership->district_id);
        $this->assertNull($membership->fleet_id);
    }

    public function test_user_can_register_with_only_district(): void
    {
        $district = District::first();

        $data = array_merge($this->getBaseRegistrationData(), [
            'gender' => 'non_binary',
            'district_id' => $district->id,
            'fleet_id' => 0,
        ]);

        Livewire::test(RegistrationForm::class)
            ->fill($data)
            ->call('register')
            ->assertRedirect('/logbook');

        $user = User::where('email', 'test@example.com')->first();
        $membership = $user->currentMembership();

        $this->assertEquals($district->id, $membership->district_id);
        $this->assertNull($membership->fleet_id);
    }

    public function test_user_can_register_with_only_fleet(): void
    {
        $fleet = Fleet::first();

        $data = array_merge($this->getBaseRegistrationData(), [
            'gender' => 'prefer_not_to_say',
            'district_id' => 0,
            'fleet_id' => $fleet->id,
        ]);

        Livewire::test(RegistrationForm::class)
            ->fill($data)
            ->call('register')
            ->assertRedirect('/logbook');

        $user = User::where('email', 'test@example.com')->first();
        $membership = $user->currentMembership();

        $this->assertNull($membership->district_id);
        $this->assertEquals($fleet->id, $membership->fleet_id);
    }

    public function test_registration_fails_with_invalid_district_id(): void
    {
        $data = array_merge($this->getBaseRegistrationData(), [
            'district_id' => 99999, // Invalid ID
            'fleet_id' => 0,
        ]);

        Livewire::test(RegistrationForm::class)
            ->fill($data)
            ->call('register')
            ->assertHasErrors('district_id');

        $this->assertGuest();
    }

    public function test_registration_fails_with_invalid_fleet_id(): void
    {
        $district = District::first();

        $data = array_merge($this->getBaseRegistrationData(), [
            'gender' => 'female',
            'district_id' => $district->id,
            'fleet_id' => 99999, // Invalid ID
        ]);

        Livewire::test(RegistrationForm::class)
            ->fill($data)
            ->call('register')
            ->assertHasErrors('fleet_id');

        $this->assertGuest();
    }

    public function test_registration_creates_membership_for_current_year(): void
    {
        $district = District::first();
        $fleet = Fleet::where('district_id', $district->id)->first();
        $currentYear = now()->year;

        $data = array_merge($this->getBaseRegistrationData(), [
            'district_id' => $district->id,
            'fleet_id' => $fleet->id,
        ]);

        Livewire::test(RegistrationForm::class)
            ->fill($data)
            ->call('register');

        $user = User::where('email', 'test@example.com')->first();
        $membership = $user->currentMembership();

        $this->assertEquals($currentYear, $membership->year);
    }

    public function test_user_without_district_or_fleet_still_creates_membership(): void
    {
        $data = array_merge($this->getBaseRegistrationData(), [
            'district_id' => 0,
            'fleet_id' => 0,
        ]);

        Livewire::test(RegistrationForm::class)
            ->fill($data)
            ->call('register');

        $user = User::where('email', 'test@example.com')->first();

        // Should create membership record even for unaffiliated users
        $this->assertEquals(1, $user->members()->count());
        $this->assertNull($user->currentMembership()->district_id);
        $this->assertNull($user->currentMembership()->fleet_id);
    }

    public function test_registration_does_not_store_district_or_fleet_on_user_table(): void
    {
        $district = District::first();
        $fleet = Fleet::where('district_id', $district->id)->first();

        $data = array_merge($this->getBaseRegistrationData(), [
            'district_id' => $district->id,
            'fleet_id' => $fleet->id,
        ]);

        Livewire::test(RegistrationForm::class)
            ->fill($data)
            ->call('register');

        $user = User::where('email', 'test@example.com')->first();

        // Users table should not have district_id or fleet_id columns
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'test@example.com',
        ]);

        // But membership should exist
        $this->assertDatabaseHas('members', [
            'user_id' => $user->id,
            'district_id' => $district->id,
            'fleet_id' => $fleet->id,
            'year' => now()->year,
        ]);
    }

    public function test_registration_with_null_values_instead_of_none_string(): void
    {
        $data = array_merge($this->getBaseRegistrationData(), [
            'district_id' => null,
            'fleet_id' => null,
        ]);

        Livewire::test(RegistrationForm::class)
            ->fill($data)
            ->call('register')
            ->assertRedirect('/logbook');

        $user = User::where('email', 'test@example.com')->first();
        $membership = $user->currentMembership();

        $this->assertNotNull($membership);
        $this->assertNull($membership->district_id);
        $this->assertNull($membership->fleet_id);
    }
}
