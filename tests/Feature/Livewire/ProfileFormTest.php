<?php

namespace Tests\Feature\Livewire;

use App\Livewire\ProfileForm;
use App\Models\District;
use App\Models\Fleet;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_renders_successfully(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ProfileForm::class)
            ->assertStatus(200)
            ->assertSee('Edit Profile')
            ->assertSee('Save Changes');
    }

    public function test_component_prefills_user_data(): void
    {
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'date_of_birth' => '1990-05-15',
            'gender' => 'male',
            'address_line1' => '123 Main St',
            'address_line2' => 'Apt 4B',
            'city' => 'Anytown',
            'state' => 'CA',
            'zip_code' => '12345',
            'country' => 'USA',
            'yacht_club' => 'Test Yacht Club',
        ]);

        Livewire::actingAs($user)
            ->test(ProfileForm::class)
            ->assertSet('first_name', 'John')
            ->assertSet('last_name', 'Doe')
            ->assertSet('email', 'john@example.com')
            ->assertSet('date_of_birth', '1990-05-15')
            ->assertSet('gender', 'male')
            ->assertSet('address_line1', '123 Main St')
            ->assertSet('address_line2', 'Apt 4B')
            ->assertSet('city', 'Anytown')
            ->assertSet('state', 'CA')
            ->assertSet('zip_code', '12345')
            ->assertSet('country', 'USA')
            ->assertSet('yacht_club', 'Test Yacht Club');
    }

    public function test_component_prefills_membership_data(): void
    {
        $district = District::first();
        $fleet = Fleet::where('district_id', $district->id)->first();
        $user = User::factory()->create();

        Member::create([
            'user_id' => $user->id,
            'district_id' => $district->id,
            'fleet_id' => $fleet->id,
            'year' => now()->year,
        ]);

        Livewire::actingAs($user)
            ->test(ProfileForm::class)
            ->assertSet('district_id', $district->id)
            ->assertSet('fleet_id', $fleet->id);
    }

    public function test_component_handles_null_membership_data(): void
    {
        $user = User::factory()->create();

        // No membership record created

        Livewire::actingAs($user)
            ->test(ProfileForm::class)
            ->assertSet('district_id', null)
            ->assertSet('fleet_id', null);
    }

    public function test_can_update_profile(): void
    {
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);

        Livewire::actingAs($user)
            ->test(ProfileForm::class)
            ->set('first_name', 'Jane')
            ->set('last_name', 'Smith')
            ->set('date_of_birth', '1995-03-20')
            ->set('gender', 'female')
            ->set('address_line1', '456 Oak Ave')
            ->set('address_line2', 'Suite 100')
            ->set('city', 'Newtown')
            ->set('state', 'NY')
            ->set('zip_code', '54321')
            ->set('country', 'USA')
            ->set('yacht_club', 'New Yacht Club')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        // Verify user was updated (email should remain unchanged)
        $user->refresh();
        $this->assertEquals('Jane', $user->first_name);
        $this->assertEquals('Smith', $user->last_name);
        $this->assertEquals('john@example.com', $user->email); // Email unchanged
        $this->assertEquals('1995-03-20', $user->date_of_birth->format('Y-m-d'));
        $this->assertEquals('female', $user->gender);
        $this->assertEquals('456 Oak Ave', $user->address_line1);
        $this->assertEquals('Suite 100', $user->address_line2);
        $this->assertEquals('Newtown', $user->city);
        $this->assertEquals('NY', $user->state);
        $this->assertEquals('54321', $user->zip_code);
        $this->assertEquals('USA', $user->country);
        $this->assertEquals('New Yacht Club', $user->yacht_club);
    }

    public function test_can_update_membership_info(): void
    {
        $district1 = District::first();
        $district2 = District::skip(1)->first();
        $fleet1 = Fleet::where('district_id', $district1->id)->first();
        $fleet2 = Fleet::where('district_id', $district2->id)->first();

        $user = User::factory()->create();

        // Create initial membership
        Member::create([
            'user_id' => $user->id,
            'district_id' => $district1->id,
            'fleet_id' => $fleet1->id,
            'year' => now()->year,
        ]);

        Livewire::actingAs($user)
            ->test(ProfileForm::class)
            ->set('first_name', $user->first_name)
            ->set('last_name', $user->last_name)
            ->set('email', $user->email)
            ->set('date_of_birth', $user->date_of_birth->format('Y-m-d'))
            ->set('gender', $user->gender)
            ->set('address_line1', $user->address_line1)
            ->set('city', $user->city)
            ->set('state', $user->state)
            ->set('zip_code', $user->zip_code)
            ->set('country', $user->country)
            ->set('district_id', $district2->id)
            ->set('fleet_id', $fleet2->id)
            ->call('save')
            ->assertHasNoErrors();

        // Verify membership was updated
        $membership = Member::where('user_id', $user->id)
            ->where('year', now()->year)
            ->first();

        $this->assertEquals($district2->id, $membership->district_id);
        $this->assertEquals($fleet2->id, $membership->fleet_id);
    }

    public function test_creates_membership_if_none_exists(): void
    {
        $district = District::first();
        $fleet = Fleet::where('district_id', $district->id)->first();
        $user = User::factory()->create();

        // No membership record exists initially

        Livewire::actingAs($user)
            ->test(ProfileForm::class)
            ->set('first_name', $user->first_name)
            ->set('last_name', $user->last_name)
            ->set('email', $user->email)
            ->set('date_of_birth', $user->date_of_birth->format('Y-m-d'))
            ->set('gender', $user->gender)
            ->set('address_line1', $user->address_line1)
            ->set('city', $user->city)
            ->set('state', $user->state)
            ->set('zip_code', $user->zip_code)
            ->set('country', $user->country)
            ->set('district_id', $district->id)
            ->set('fleet_id', $fleet->id)
            ->call('save')
            ->assertHasNoErrors();

        // Verify membership was created
        $this->assertDatabaseHas('members', [
            'user_id' => $user->id,
            'district_id' => $district->id,
            'fleet_id' => $fleet->id,
            'year' => now()->year,
        ]);
    }

    public function test_validates_required_fields(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ProfileForm::class)
            ->set('first_name', '')
            ->set('last_name', '')
            ->set('date_of_birth', '')
            ->set('gender', '')
            ->set('address_line1', '')
            ->set('city', '')
            ->set('state', '')
            ->set('zip_code', '')
            ->set('country', '')
            ->call('save')
            ->assertHasErrors([
                'first_name',
                'last_name',
                'date_of_birth',
                'gender',
                'address_line1',
                'city',
                'state',
                'zip_code',
                'country',
            ]);
    }

    public function test_validates_gender_values(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ProfileForm::class)
            ->set('gender', 'invalid')
            ->set('first_name', $user->first_name)
            ->set('last_name', $user->last_name)
            ->set('email', $user->email)
            ->set('date_of_birth', $user->date_of_birth->format('Y-m-d'))
            ->set('address_line1', $user->address_line1)
            ->set('city', $user->city)
            ->set('state', $user->state)
            ->set('zip_code', $user->zip_code)
            ->set('country', $user->country)
            ->call('save')
            ->assertHasErrors(['gender']);
    }

    public function test_validates_date_of_birth_in_past(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ProfileForm::class)
            ->set('date_of_birth', now()->addDay()->format('Y-m-d'))
            ->set('first_name', $user->first_name)
            ->set('last_name', $user->last_name)
            ->set('email', $user->email)
            ->set('gender', $user->gender)
            ->set('address_line1', $user->address_line1)
            ->set('city', $user->city)
            ->set('state', $user->state)
            ->set('zip_code', $user->zip_code)
            ->set('country', $user->country)
            ->call('save')
            ->assertHasErrors(['date_of_birth']);
    }

    public function test_validates_date_of_birth_after_1900(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ProfileForm::class)
            ->set('date_of_birth', '1899-12-31')
            ->set('first_name', $user->first_name)
            ->set('last_name', $user->last_name)
            ->set('email', $user->email)
            ->set('gender', $user->gender)
            ->set('address_line1', $user->address_line1)
            ->set('city', $user->city)
            ->set('state', $user->state)
            ->set('zip_code', $user->zip_code)
            ->set('country', $user->country)
            ->call('save')
            ->assertHasErrors(['date_of_birth']);
    }

    public function test_optional_fields_can_be_empty(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ProfileForm::class)
            ->set('first_name', $user->first_name)
            ->set('last_name', $user->last_name)
            ->set('email', $user->email)
            ->set('date_of_birth', $user->date_of_birth->format('Y-m-d'))
            ->set('gender', $user->gender)
            ->set('address_line1', $user->address_line1)
            ->set('address_line2', '') // Optional
            ->set('city', $user->city)
            ->set('state', $user->state)
            ->set('zip_code', $user->zip_code)
            ->set('country', $user->country)
            ->set('district_id', null) // Optional
            ->set('fleet_id', null) // Optional
            ->set('yacht_club', '') // Optional
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_validates_district_exists(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ProfileForm::class)
            ->set('first_name', $user->first_name)
            ->set('last_name', $user->last_name)
            ->set('email', $user->email)
            ->set('date_of_birth', $user->date_of_birth->format('Y-m-d'))
            ->set('gender', $user->gender)
            ->set('address_line1', $user->address_line1)
            ->set('city', $user->city)
            ->set('state', $user->state)
            ->set('zip_code', $user->zip_code)
            ->set('country', $user->country)
            ->set('district_id', 99999) // Non-existent
            ->call('save')
            ->assertHasErrors(['district_id']);
    }

    public function test_validates_fleet_exists(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ProfileForm::class)
            ->set('first_name', $user->first_name)
            ->set('last_name', $user->last_name)
            ->set('email', $user->email)
            ->set('date_of_birth', $user->date_of_birth->format('Y-m-d'))
            ->set('gender', $user->gender)
            ->set('address_line1', $user->address_line1)
            ->set('city', $user->city)
            ->set('state', $user->state)
            ->set('zip_code', $user->zip_code)
            ->set('country', $user->country)
            ->set('fleet_id', 99999) // Non-existent
            ->call('save')
            ->assertHasErrors(['fleet_id']);
    }

    public function test_shows_success_toast_after_save(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ProfileForm::class)
            ->set('first_name', 'Updated')
            ->set('last_name', $user->last_name)
            ->set('email', $user->email)
            ->set('date_of_birth', $user->date_of_birth->format('Y-m-d'))
            ->set('gender', $user->gender)
            ->set('address_line1', $user->address_line1)
            ->set('city', $user->city)
            ->set('state', $user->state)
            ->set('zip_code', $user->zip_code)
            ->set('country', $user->country)
            ->call('save')
            ->assertDispatched('toast');
    }

    public function test_handles_null_optional_fields(): void
    {
        $user = User::factory()->create([
            'address_line2' => null,
            'yacht_club' => null,
        ]);

        Livewire::actingAs($user)
            ->test(ProfileForm::class)
            ->assertSet('address_line2', '')
            ->assertSet('yacht_club', '');
    }

    public function test_profile_page_requires_authentication(): void
    {
        $response = $this->get('/profile');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_profile_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile');

        $response->assertStatus(200);
        $response->assertSeeLivewire(ProfileForm::class);
    }
}
