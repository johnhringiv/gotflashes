<?php

namespace Tests\Feature;

use App\Livewire\ProfileForm;
use App\Livewire\RegistrationForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmailSuggestionLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_form_surfaces_a_suggestion_for_a_typo(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('email', 'sailor@gmial.com')
            ->assertSet('emailSuggestion', 'sailor@gmail.com')
            // The suggestion is rendered (prop reaches the shared blade component).
            ->assertSee('Did you mean')
            ->assertSee('sailor@gmail.com');
    }

    public function test_registration_form_clears_suggestion_for_a_good_address(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('email', 'sailor@gmial.com')
            ->assertSet('emailSuggestion', 'sailor@gmail.com')
            ->set('email', 'sailor@gmail.com')
            ->assertSet('emailSuggestion', null);
    }

    public function test_applying_the_suggestion_corrects_the_email_field(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('email', 'sailor@gmial.com')
            ->call('applyEmailSuggestion')
            ->assertSet('email', 'sailor@gmail.com')
            ->assertSet('emailSuggestion', null)
            ->assertHasNoErrors('email');
    }

    public function test_profile_form_surfaces_a_suggestion_for_a_typo(): void
    {
        $user = User::factory()->create(['email' => 'current@example.com']);

        Livewire::actingAs($user)
            ->test(ProfileForm::class)
            ->set('email', 'current@yahooo.com')
            ->assertSet('emailSuggestion', 'current@yahoo.com')
            ->call('applyEmailSuggestion')
            ->assertSet('email', 'current@yahoo.com')
            ->assertSet('emailSuggestion', null);
    }
}
