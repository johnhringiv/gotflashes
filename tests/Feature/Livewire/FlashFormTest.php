<?php

namespace Tests\Feature\Livewire;

use App\Livewire\FlashForm;
use App\Models\Flash;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FlashFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_renders_in_create_mode(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(FlashForm::class, [
                'action' => route('flashes.store'),
                'submitText' => 'Log Activity',
            ])
            ->assertStatus(200)
            ->assertSee('Log Activity')
            ->assertViewHas('minDate')
            ->assertViewHas('maxDate')
            ->assertViewHas('existingDates');
    }

    public function test_component_renders_in_edit_mode(): void
    {
        $user = User::factory()->create();
        $flash = Flash::factory()->forUser($user)->create([
            'date' => now()->format('Y-m-d'),
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
        ]);

        Livewire::actingAs($user)
            ->test(FlashForm::class, [
                'flash' => $flash,
                'action' => route('flashes.update', $flash),
                'method' => 'PUT',
                'submitText' => 'Update Activity',
            ])
            ->assertStatus(200)
            ->assertSee('Update Activity')
            ->assertSet('mode', 'edit')
            ->assertSet('date', now()->format('Y-m-d'))
            ->assertSet('activity_type', 'sailing')
            ->assertSet('event_type', 'regatta');
    }

    public function test_create_mode_determined_correctly_when_no_flash(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(FlashForm::class, [
                'action' => route('flashes.store'),
            ])
            ->assertSet('mode', 'create');
    }

    public function test_edit_mode_determined_correctly_when_flash_exists(): void
    {
        $user = User::factory()->create();
        $flash = Flash::factory()->forUser($user)->create([
            'date' => now()->format('Y-m-d'),
        ]);

        Livewire::actingAs($user)
            ->test(FlashForm::class, [
                'flash' => $flash,
                'action' => route('flashes.update', $flash),
                'method' => 'PUT',
            ])
            ->assertSet('mode', 'edit');
    }

    public function test_min_date_calculated_for_current_year_in_february(): void
    {
        $user = User::factory()->create();

        // Mock time to February 15, 2025
        $this->travelTo(now()->setDate(2025, 2, 15));

        $component = Livewire::actingAs($user)
            ->test(FlashForm::class, [
                'action' => route('flashes.store'),
            ]);

        // In February, minDate should be start of current year (2025-01-01)
        $minDate = $component->viewData('minDate');
        $this->assertEquals('2025-01-01', $minDate->format('Y-m-d'));
    }

    public function test_min_date_calculated_for_previous_year_in_january(): void
    {
        $user = User::factory()->create();

        // Mock time to January 15, 2025
        $this->travelTo(now()->setDate(2025, 1, 15));

        $component = Livewire::actingAs($user)
            ->test(FlashForm::class, [
                'action' => route('flashes.store'),
            ]);

        // In January, minDate should be start of previous year (2024-01-01) - grace period
        $minDate = $component->viewData('minDate');
        $this->assertEquals('2024-01-01', $minDate->format('Y-m-d'));
    }

    public function test_max_date_calculated_as_tomorrow(): void
    {
        $user = User::factory()->create();

        // Mock time to January 15, 2025
        $this->travelTo(now()->setDate(2025, 1, 15));

        $component = Livewire::actingAs($user)
            ->test(FlashForm::class, [
                'action' => route('flashes.store'),
            ]);

        // maxDate should be tomorrow (for timezone tolerance)
        $maxDate = $component->viewData('maxDate');
        $this->assertEquals('2025-01-16', $maxDate->format('Y-m-d'));
    }

    public function test_date_ranges_recalculate_on_rerender(): void
    {
        $user = User::factory()->create();

        // Start in January - grace period active
        $this->travelTo(now()->setDate(2025, 1, 31, 23, 50));

        $component = Livewire::actingAs($user)
            ->test(FlashForm::class, [
                'action' => route('flashes.store'),
            ]);

        // Initial render: minDate should include previous year
        $minDate1 = $component->viewData('minDate');
        $this->assertEquals('2024-01-01', $minDate1->format('Y-m-d'));

        // Travel forward to February - grace period ended
        $this->travelTo(now()->setDate(2025, 2, 1, 0, 10));

        // Call the component again (simulates re-render)
        $component->call('$refresh');

        // After re-render: minDate should NOT include previous year
        $minDate2 = $component->viewData('minDate');
        $this->assertEquals('2025-01-01', $minDate2->format('Y-m-d'));
    }

    public function test_existing_dates_include_only_selectable_range(): void
    {
        $user = User::factory()->create();

        // Create flashes in different years
        Flash::factory()->forUser($user)->create(['date' => '2023-06-15']); // Too old
        Flash::factory()->forUser($user)->create(['date' => '2024-06-15']); // Previous year
        Flash::factory()->forUser($user)->create(['date' => '2025-01-15']); // Current year

        // Test in February (no grace period)
        $this->travelTo(now()->setDate(2025, 2, 1));

        $component = Livewire::actingAs($user)
            ->test(FlashForm::class, [
                'action' => route('flashes.store'),
            ]);

        $existingDates = $component->viewData('existingDates');

        // Should only include 2025 date (within selectable range)
        $this->assertCount(1, $existingDates);
        $this->assertContains('2025-01-15', $existingDates);
        $this->assertNotContains('2024-06-15', $existingDates);
        $this->assertNotContains('2023-06-15', $existingDates);
    }

    public function test_existing_dates_include_previous_year_in_january(): void
    {
        $user = User::factory()->create();

        // Create flashes in different years
        Flash::factory()->forUser($user)->create(['date' => '2023-06-15']); // Too old
        Flash::factory()->forUser($user)->create(['date' => '2024-06-15']); // Previous year
        Flash::factory()->forUser($user)->create(['date' => '2025-01-15']); // Current year

        // Test in January (grace period)
        $this->travelTo(now()->setDate(2025, 1, 15));

        $component = Livewire::actingAs($user)
            ->test(FlashForm::class, [
                'action' => route('flashes.store'),
            ]);

        $existingDates = $component->viewData('existingDates');

        // Should include both 2024 and 2025 dates (within selectable range during grace period)
        $this->assertCount(2, $existingDates);
        $this->assertContains('2024-06-15', $existingDates);
        $this->assertContains('2025-01-15', $existingDates);
        $this->assertNotContains('2023-06-15', $existingDates);
    }

    public function test_edit_mode_prefills_all_form_fields(): void
    {
        $user = User::factory()->create();
        $flash = Flash::factory()->forUser($user)->create([
            'date' => '2025-01-15',
            'activity_type' => 'maintenance',
            'event_type' => null,
            'location' => 'Lake Norman, NC',
            'sail_number' => 15234,
            'notes' => 'Fixed the mast',
        ]);

        Livewire::actingAs($user)
            ->test(FlashForm::class, [
                'flash' => $flash,
                'action' => route('flashes.update', $flash),
                'method' => 'PUT',
            ])
            ->assertSet('date', '2025-01-15')
            ->assertSet('activity_type', 'maintenance')
            ->assertSet('event_type', '')
            ->assertSet('location', 'Lake Norman, NC')
            ->assertSet('sail_number', 15234)
            ->assertSet('notes', 'Fixed the mast');
    }

    public function test_edit_mode_handles_null_optional_fields(): void
    {
        $user = User::factory()->create();
        $flash = Flash::factory()->forUser($user)->create([
            'date' => '2025-01-15',
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
            'location' => null,
            'sail_number' => null,
            'notes' => null,
        ]);

        Livewire::actingAs($user)
            ->test(FlashForm::class, [
                'flash' => $flash,
                'action' => route('flashes.update', $flash),
            ])
            ->assertSet('location', '')
            ->assertSet('sail_number', '')
            ->assertSet('notes', '');
    }

    public function test_component_displays_correct_input_id_for_create_mode(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(FlashForm::class, [
                'action' => route('flashes.store'),
            ])
            ->assertSee('id="date-picker"', false); // Multi-date picker for create
    }

    public function test_component_displays_correct_input_id_for_edit_mode(): void
    {
        $user = User::factory()->create();
        $flash = Flash::factory()->forUser($user)->create([
            'date' => now()->format('Y-m-d'),
        ]);

        Livewire::actingAs($user)
            ->test(FlashForm::class, [
                'flash' => $flash,
                'action' => route('flashes.update', $flash),
                'method' => 'PUT',
            ])
            ->assertSee('id="date-picker-single"', false); // Single-date picker for edit
    }

    public function test_grace_period_boundary_crossing_updates_year_dropdown_range(): void
    {
        $user = User::factory()->create();

        // Start in January - should show 2024 and 2025 in year dropdown
        $this->travelTo(now()->setDate(2025, 1, 31, 23, 50));

        $component = Livewire::actingAs($user)
            ->test(FlashForm::class, [
                'action' => route('flashes.store'),
            ]);

        // Check that 2024 dates are within range
        $minDate1 = $component->viewData('minDate');
        $this->assertEquals(2024, $minDate1->year);

        // Travel to February - should only show 2025
        $this->travelTo(now()->setDate(2025, 2, 1, 0, 10));
        $component->call('$refresh');

        // Check that 2024 dates are no longer within range
        $minDate2 = $component->viewData('minDate');
        $this->assertEquals(2025, $minDate2->year);
    }

    public function test_component_passes_data_attributes_to_view(): void
    {
        $user = User::factory()->create();
        Flash::factory()->forUser($user)->create(['date' => '2025-01-10']);

        $this->travelTo(now()->setDate(2025, 1, 15));

        Livewire::actingAs($user)
            ->test(FlashForm::class, [
                'action' => route('flashes.store'),
            ])
            ->assertSee('data-min-date="2024-01-01"', false)
            ->assertSee('data-max-date="2025-01-16"', false)
            ->assertSee('data-existing-dates', false);
    }
}
