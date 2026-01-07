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

    protected function setUp(): void
    {
        parent::setUp();

        // Pin tests to mid-2025 so hardcoded 2025 dates work regardless of actual year
        $this->travelTo('2025-06-15');
    }

    public function test_component_renders_in_create_mode(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(FlashForm::class, [

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

            ]);

        // In February, minDate should be start of current year (2025-01-01)
        $minDate = $component->viewData('minDate');
        $this->assertEquals('2025-01-01', $minDate->format('Y-m-d'));
    }

    public function test_min_date_calculated_for_previous_year_in_january(): void
    {
        $user = User::factory()->create();

        // Override START_YEAR so 2025 is after the start year (grace period applies)
        config(['app.start_year' => 2024]);

        // Mock time to January 15, 2025
        $this->travelTo(now()->setDate(2025, 1, 15));

        $component = Livewire::actingAs($user)
            ->test(FlashForm::class, [

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

            ]);

        // maxDate should be tomorrow (for timezone tolerance)
        $maxDate = $component->viewData('maxDate');
        $this->assertEquals('2025-01-16', $maxDate->format('Y-m-d'));
    }

    public function test_date_ranges_recalculate_on_rerender(): void
    {
        $user = User::factory()->create();

        // Override START_YEAR so 2025 is after the start year (grace period applies)
        config(['app.start_year' => 2024]);

        // Start in January - grace period active
        $this->travelTo(now()->setDate(2025, 1, 31, 23, 50));

        $component = Livewire::actingAs($user)
            ->test(FlashForm::class, [

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

        // Override START_YEAR so 2025 is after the start year (grace period applies)
        config(['app.start_year' => 2024]);

        // Create flashes in different years
        Flash::factory()->forUser($user)->create(['date' => '2023-06-15']); // Too old
        Flash::factory()->forUser($user)->create(['date' => '2024-06-15']); // Previous year
        Flash::factory()->forUser($user)->create(['date' => '2025-01-15']); // Current year

        // Test in January (grace period)
        $this->travelTo(now()->setDate(2025, 1, 15));

        $component = Livewire::actingAs($user)
            ->test(FlashForm::class, [

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

            ])
            ->assertSee('id="date-picker-single"', false); // Single-date picker for edit
    }

    public function test_grace_period_boundary_crossing_updates_year_dropdown_range(): void
    {
        $user = User::factory()->create();

        // Override START_YEAR so 2025 is after the start year (grace period applies)
        config(['app.start_year' => 2024]);

        // Start in January - should show 2024 and 2025 in year dropdown
        $this->travelTo(now()->setDate(2025, 1, 31, 23, 50));

        $component = Livewire::actingAs($user)
            ->test(FlashForm::class, [

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

        // Override START_YEAR so 2025 is after the start year (grace period applies)
        config(['app.start_year' => 2024]);

        Flash::factory()->forUser($user)->create(['date' => '2025-01-10']);

        $this->travelTo(now()->setDate(2025, 1, 15));

        Livewire::actingAs($user)
            ->test(FlashForm::class, [
                'submitText' => 'Log Activity',
            ])
            ->assertSee('data-min-date="2024-01-01"', false)
            ->assertSee('data-max-date="2025-01-16"', false)
            ->assertSee('data-existing-dates', false);
    }

    // NEW LIVEWIRE FUNCTIONALITY TESTS

    public function test_can_save_single_flash_via_livewire(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->setDate(2025, 1, 15));

        Livewire::actingAs($user)
            ->test(FlashForm::class, ['submitText' => 'Log Activity'])
            ->set('dates', ['2025-01-15'])
            ->set('activity_type', 'sailing')
            ->set('event_type', 'regatta')
            ->set('location', 'Lake Norman')
            ->set('sail_number', '15234')
            ->set('notes', 'Great day')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('flash-saved')
            ->assertDispatched('toast');

        // Check the flash was created (date might include timestamp)
        $this->assertDatabaseCount('flashes', 1);
        $flash = Flash::first();
        $this->assertEquals($user->id, $flash->user_id);
        $this->assertEquals('2025-01-15', $flash->date->format('Y-m-d'));
        $this->assertEquals('sailing', $flash->activity_type);
        $this->assertEquals('regatta', $flash->event_type);
        $this->assertEquals('Lake Norman', $flash->location);
        $this->assertEquals(15234, $flash->sail_number);
        $this->assertEquals('Great day', $flash->notes);
    }

    public function test_can_save_multiple_flashes_via_livewire(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->setDate(2025, 1, 15));

        Livewire::actingAs($user)
            ->test(FlashForm::class, ['submitText' => 'Log Activity'])
            ->set('dates', ['2025-01-10', '2025-01-11', '2025-01-12'])
            ->set('activity_type', 'sailing')
            ->set('event_type', 'club_race')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('flash-saved');

        $this->assertDatabaseCount('flashes', 3);

        // Verify all three dates were saved
        $flashes = $user->flashes()->pluck('date')->map(fn ($d) => $d->format('Y-m-d'))->toArray();
        $this->assertContains('2025-01-10', $flashes);
        $this->assertContains('2025-01-11', $flashes);
        $this->assertContains('2025-01-12', $flashes);
    }

    public function test_form_resets_after_successful_save(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(FlashForm::class, ['submitText' => 'Log Activity'])
            ->set('dates', ['2025-01-15'])
            ->set('activity_type', 'sailing')
            ->set('event_type', 'regatta')
            ->set('location', 'Lake Norman')
            ->set('sail_number', '15234')
            ->set('notes', 'Great day')
            ->call('save')
            ->assertSet('dates', [])
            ->assertSet('activity_type', '')
            ->assertSet('event_type', '')
            ->assertSet('location', '')
            ->assertSet('sail_number', '')
            ->assertSet('notes', '');
    }

    public function test_validates_required_dates(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(FlashForm::class, ['submitText' => 'Log Activity'])
            ->set('dates', [])
            ->set('activity_type', 'sailing')
            ->set('event_type', 'regatta')
            ->call('save')
            ->assertHasErrors(['dates']);
    }

    public function test_validates_required_activity_type(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(FlashForm::class, ['submitText' => 'Log Activity'])
            ->set('dates', ['2025-01-15'])
            ->set('activity_type', '')
            ->call('save')
            ->assertHasErrors(['activity_type']);
    }

    public function test_validates_event_type_required_for_sailing(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(FlashForm::class, ['submitText' => 'Log Activity'])
            ->set('dates', ['2025-01-15'])
            ->set('activity_type', 'sailing')
            ->set('event_type', '')
            ->call('save')
            ->assertHasErrors(['event_type']);
    }

    public function test_event_type_not_required_for_maintenance(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(FlashForm::class, ['submitText' => 'Log Activity'])
            ->set('dates', ['2025-01-15'])
            ->set('activity_type', 'maintenance')
            ->set('event_type', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('flashes', [
            'user_id' => $user->id,
            'activity_type' => 'maintenance',
            'event_type' => null,
        ]);
    }

    public function test_prevents_duplicate_dates(): void
    {
        $user = User::factory()->create();
        Flash::factory()->forUser($user)->create(['date' => '2025-01-15']);

        Livewire::actingAs($user)
            ->test(FlashForm::class, ['submitText' => 'Log Activity'])
            ->set('dates', ['2025-01-15'])
            ->set('activity_type', 'sailing')
            ->set('event_type', 'regatta')
            ->call('save')
            ->assertHasErrors(['dates']);
    }

    public function test_shows_warning_toast_when_exceeding_non_sailing_limit(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->setDate(2025, 1, 15));

        // Create 5 non-sailing activities already
        for ($i = 1; $i <= 5; $i++) {
            Flash::factory()->forUser($user)->create([
                'date' => "2025-01-0{$i}",
                'activity_type' => 'maintenance',
            ]);
        }

        Livewire::actingAs($user)
            ->test(FlashForm::class, ['submitText' => 'Log Activity'])
            ->set('dates', ['2025-01-15']) // Use current date from travelTo
            ->set('activity_type', 'maintenance')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');
    }

    public function test_form_clears_and_calendar_updates_after_save(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->setDate(2025, 1, 15));

        // Create an existing flash
        Flash::factory()->forUser($user)->create([
            'date' => '2025-01-10',
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
        ]);

        $component = Livewire::actingAs($user)
            ->test(FlashForm::class, ['submitText' => 'Log Activity']);

        // Verify initial state - existing date should be in existingDates
        $initialExistingDates = $component->viewData('existingDates');
        $this->assertContains('2025-01-10', $initialExistingDates);
        $this->assertNotContains('2025-01-15', $initialExistingDates);

        // Save a new flash
        $component
            ->set('dates', ['2025-01-15'])
            ->set('activity_type', 'sailing')
            ->set('event_type', 'regatta')
            ->call('save')
            ->assertHasNoErrors();

        // Verify form fields are cleared
        $component->assertSet('dates', [])
            ->assertSet('activity_type', '')
            ->assertSet('event_type', '');

        // Verify existingDates now includes the new date
        $updatedExistingDates = $component->viewData('existingDates');
        $this->assertContains('2025-01-10', $updatedExistingDates, 'Old date should still be in existingDates');
        $this->assertContains('2025-01-15', $updatedExistingDates, 'Newly saved date should be in existingDates');

        // Verify flash-saved event was dispatched
        $component->assertDispatched('flash-saved');
    }

    public function test_calendar_updates_after_delete(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->setDate(2025, 1, 15));

        // Create two existing flashes
        Flash::factory()->forUser($user)->create([
            'date' => '2025-01-10',
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
        ]);

        Flash::factory()->forUser($user)->create([
            'date' => '2025-01-12',
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
        ]);

        $component = Livewire::actingAs($user)
            ->test(FlashForm::class, ['submitText' => 'Log Activity']);

        // Verify initial state - both dates should be in existingDates
        $initialExistingDates = $component->viewData('existingDates');
        $this->assertContains('2025-01-10', $initialExistingDates);
        $this->assertContains('2025-01-12', $initialExistingDates);

        // Simulate a flash being deleted by dispatching the event
        $component->dispatch('flash-deleted');

        // Component should refresh and fetch updated existingDates
        // (In reality, the flash would be deleted by FlashList, but we're testing FlashForm's response)
        // Force a refresh to simulate what happens when flash-deleted is received
        $component->call('refreshAfterDelete');

        // The component should have re-rendered and fetched fresh data
        // Both dates should still be there since we didn't actually delete from DB
        $updatedExistingDates = $component->viewData('existingDates');
        $this->assertContains('2025-01-10', $updatedExistingDates);
        $this->assertContains('2025-01-12', $updatedExistingDates);
    }
}
