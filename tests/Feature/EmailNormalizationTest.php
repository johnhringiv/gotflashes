<?php

namespace Tests\Feature;

use App\Livewire\ProfileForm;
use App\Livewire\RegistrationForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class EmailNormalizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Register through the Livewire form with a mixed-case/whitespace email and
     * the given event call, returning the test instance.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function registerWith(string $email, array $overrides = []): Testable
    {
        $data = array_merge([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'address_line1' => '123 Main St',
            'city' => 'Anytown',
            'state' => 'CA',
            'zip_code' => '12345',
            'country' => 'USA',
            'district_id' => 'none',
            'fleet_id' => 'none',
            'yacht_club' => '',
        ], $overrides);

        $component = Livewire::test(RegistrationForm::class);
        foreach ($data as $key => $value) {
            $component->set($key, $value);
        }

        return $component->call('register');
    }

    public function test_registration_stores_email_lowercased_and_trimmed(): void
    {
        $this->registerWith('  John.Doe@Example.COM ')
            ->assertRedirect(route('logbook.index'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'john.doe@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'John.Doe@Example.COM']);
    }

    public function test_reregistering_with_different_case_fails_uniqueness(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $this->registerWith('TEST@example.com')
            ->assertHasErrors('email');

        // No second account was created.
        $this->assertEquals(1, User::where('email', 'test@example.com')->count());
    }

    public function test_login_matches_mixed_case_against_lowercased_stored_value(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'Test@Example.COM',
            'password' => 'password123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('logbook.index'));
    }

    public function test_validation_rejects_rfc_warning_addresses(): void
    {
        // "a@b" and "test@localhost" pass the lenient default 'email' rule but
        // are rejected by 'email:strict'.
        $this->registerWith('a@b')->assertHasErrors('email');
        $this->registerWith('test@localhost')->assertHasErrors('email');
    }

    public function test_validation_still_accepts_internationalized_addresses(): void
    {
        // email:strict is Unicode-aware: accented local parts / IDN domains pass.
        $this->registerWith('üser@münchen.de')
            ->assertHasNoErrors('email')
            ->assertRedirect(route('logbook.index'));
    }

    public function test_user_model_lowercases_email_on_direct_write(): void
    {
        // Mutator safety net for paths that bypass the form layer (factories,
        // seeders, tinker).
        $user = User::factory()->create(['email' => 'Mixed@Case.COM']);

        $this->assertSame('mixed@case.com', $user->fresh()->email);
    }

    public function test_profile_email_change_stores_pending_email_lowercased(): void
    {
        $user = User::factory()->create(['email' => 'current@example.com']);
        $this->actingAs($user);

        Livewire::test(ProfileForm::class)
            ->set('email', 'NewAddress@Example.COM')
            ->call('save')
            ->assertHasNoErrors('email');

        // Primary email unchanged until verified; pending_email is normalized.
        $this->assertSame('current@example.com', $user->fresh()->email);
        $this->assertSame('newaddress@example.com', $user->fresh()->pending_email);
    }
}
