<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Fleet;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationWithMembershipsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Note: Districts and fleets are seeded automatically by the migration
        // via RefreshDatabase trait
    }

    public function test_user_can_register_with_district_and_fleet(): void
    {
        $district = District::first();
        $fleet = Fleet::where('district_id', $district->id)->first();

        $response = $this->post('/register', [
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
            'district_id' => $district->id,
            'fleet_id' => $fleet->id,
            'yacht_club' => 'Test Yacht Club',
        ]);

        $response->assertRedirect('/logbook');
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
        $response = $this->post('/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'date_of_birth' => '1990-01-01',
            'gender' => 'female',
            'address_line1' => '123 Main St',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip_code' => '94102',
            'country' => 'United States',
            'district_id' => 'none',
            'fleet_id' => 'none',
        ]);

        $response->assertRedirect('/logbook');
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

        $response = $this->post('/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'date_of_birth' => '1990-01-01',
            'gender' => 'non_binary',
            'address_line1' => '123 Main St',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip_code' => '94102',
            'country' => 'United States',
            'district_id' => $district->id,
            'fleet_id' => 'none',
        ]);

        $response->assertRedirect('/logbook');

        $user = User::where('email', 'test@example.com')->first();
        $membership = $user->currentMembership();

        $this->assertEquals($district->id, $membership->district_id);
        $this->assertNull($membership->fleet_id);
    }

    public function test_user_can_register_with_only_fleet(): void
    {
        $fleet = Fleet::first();

        $response = $this->post('/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'date_of_birth' => '1990-01-01',
            'gender' => 'prefer_not_to_say',
            'address_line1' => '123 Main St',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip_code' => '94102',
            'country' => 'United States',
            'district_id' => 'none',
            'fleet_id' => $fleet->id,
        ]);

        $response->assertRedirect('/logbook');

        $user = User::where('email', 'test@example.com')->first();
        $membership = $user->currentMembership();

        $this->assertNull($membership->district_id);
        $this->assertEquals($fleet->id, $membership->fleet_id);
    }

    public function test_registration_fails_with_invalid_district_id(): void
    {
        $response = $this->post('/register', [
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
            'district_id' => 99999, // Invalid ID
            'fleet_id' => 'none',
        ]);

        $response->assertSessionHasErrors('district_id');
        $this->assertGuest();
    }

    public function test_registration_fails_with_invalid_fleet_id(): void
    {
        $district = District::first();

        $response = $this->post('/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'date_of_birth' => '1990-01-01',
            'gender' => 'female',
            'address_line1' => '123 Main St',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip_code' => '94102',
            'country' => 'United States',
            'district_id' => $district->id,
            'fleet_id' => 99999, // Invalid ID
        ]);

        $response->assertSessionHasErrors('fleet_id');
        $this->assertGuest();
    }

    public function test_registration_creates_membership_for_current_year(): void
    {
        $district = District::first();
        $fleet = Fleet::where('district_id', $district->id)->first();
        $currentYear = now()->year;

        $this->post('/register', [
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
            'district_id' => $district->id,
            'fleet_id' => $fleet->id,
        ]);

        $user = User::where('email', 'test@example.com')->first();
        $membership = $user->currentMembership();

        $this->assertEquals($currentYear, $membership->year);
    }

    public function test_user_without_district_or_fleet_still_creates_membership(): void
    {
        $this->post('/register', [
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
            'district_id' => 'none',
            'fleet_id' => 'none',
        ]);

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

        $this->post('/register', [
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
            'district_id' => $district->id,
            'fleet_id' => $fleet->id,
        ]);

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
        $response = $this->post('/register', [
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
            'district_id' => null,
            'fleet_id' => null,
        ]);

        $response->assertRedirect('/logbook');

        $user = User::where('email', 'test@example.com')->first();
        $membership = $user->currentMembership();

        $this->assertNotNull($membership);
        $this->assertNull($membership->district_id);
        $this->assertNull($membership->fleet_id);
    }
}
