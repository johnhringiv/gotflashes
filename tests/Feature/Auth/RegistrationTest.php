<?php

namespace Tests\Feature\Auth;

use App\Livewire\RegistrationForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake notifications to prevent sending real emails in tests
        Notification::fake();
    }

    public function test_registration_page_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSeeLivewire(RegistrationForm::class);
    }

    public function test_new_users_can_register(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('first_name', 'John')
            ->set('last_name', 'Doe')
            ->set('email', 'test@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->set('date_of_birth', '1990-01-01')
            ->set('gender', 'male')
            ->set('address_line1', '123 Main St')
            ->set('address_line2', '')
            ->set('city', 'Anytown')
            ->set('state', 'CA')
            ->set('zip_code', '12345')
            ->set('country', 'USA')
            ->set('district_id', 1)
            ->set('fleet_id', 1)
            ->set('yacht_club', 'Test Yacht Club')
            ->call('register')
            ->assertRedirect(route('logbook.index'));

        $this->assertAuthenticated();

        // Verify user was created
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
    }

    public function test_registration_requires_all_required_fields(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('country', '') // Clear the default value
            ->call('register')
            ->assertHasErrors([
                'first_name',
                'last_name',
                'email',
                'password',
                'date_of_birth',
                'gender',
                'address_line1',
                'city',
                'state',
                'zip_code',
                'country',
            ]);
    }

    public function test_registration_requires_valid_email(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('first_name', 'John')
            ->set('last_name', 'Doe')
            ->set('email', 'not-an-email')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->set('date_of_birth', '1990-01-01')
            ->set('gender', 'male')
            ->set('address_line1', '123 Main St')
            ->set('city', 'Anytown')
            ->set('state', 'CA')
            ->set('zip_code', '12345')
            ->set('country', 'USA')
            ->call('register')
            ->assertHasErrors('email');
    }

    public function test_registration_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        Livewire::test(RegistrationForm::class)
            ->set('first_name', 'John')
            ->set('last_name', 'Doe')
            ->set('email', 'test@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->set('date_of_birth', '1990-01-01')
            ->set('gender', 'male')
            ->set('address_line1', '123 Main St')
            ->set('city', 'Anytown')
            ->set('state', 'CA')
            ->set('zip_code', '12345')
            ->set('country', 'USA')
            ->call('register')
            ->assertHasErrors('email');
    }

    public function test_registration_requires_password_confirmation(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('first_name', 'John')
            ->set('last_name', 'Doe')
            ->set('email', 'test@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'different-password')
            ->set('date_of_birth', '1990-01-01')
            ->set('gender', 'male')
            ->set('address_line1', '123 Main St')
            ->set('city', 'Anytown')
            ->set('state', 'CA')
            ->set('zip_code', '12345')
            ->set('country', 'USA')
            ->call('register')
            ->assertHasErrors('password');
    }

    public function test_registration_requires_minimum_password_length(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('first_name', 'John')
            ->set('last_name', 'Doe')
            ->set('email', 'test@example.com')
            ->set('password', 'short')
            ->set('password_confirmation', 'short')
            ->set('date_of_birth', '1990-01-01')
            ->set('gender', 'male')
            ->set('address_line1', '123 Main St')
            ->set('city', 'Anytown')
            ->set('state', 'CA')
            ->set('zip_code', '12345')
            ->set('country', 'USA')
            ->call('register')
            ->assertHasErrors('password');
    }

    public function test_registration_requires_valid_gender(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('first_name', 'John')
            ->set('last_name', 'Doe')
            ->set('email', 'test@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->set('date_of_birth', '1990-01-01')
            ->set('gender', 'invalid')
            ->set('address_line1', '123 Main St')
            ->set('city', 'Anytown')
            ->set('state', 'CA')
            ->set('zip_code', '12345')
            ->set('country', 'USA')
            ->call('register')
            ->assertHasErrors('gender');
    }

    public function test_registration_requires_date_of_birth_in_past(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('first_name', 'John')
            ->set('last_name', 'Doe')
            ->set('email', 'test@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->set('date_of_birth', now()->addDay()->format('Y-m-d'))
            ->set('gender', 'male')
            ->set('address_line1', '123 Main St')
            ->set('city', 'Anytown')
            ->set('state', 'CA')
            ->set('zip_code', '12345')
            ->set('country', 'USA')
            ->call('register')
            ->assertHasErrors('date_of_birth');
    }
}
