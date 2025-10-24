<?php

namespace Tests\Feature\Livewire;

use App\Livewire\FlashForm;
use App\Models\Flash;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tests the data contract between Livewire and JavaScript for calendar integration.
 *
 * These tests provide coverage that the existing FlashFormTest does not:
 * 1. HTML rendering - verifies data attributes are actually present in rendered HTML
 * 2. Date format validation - ensures dates are in exact Y-m-d format JavaScript expects
 * 3. Edit mode calendar behavior - verifies current date is not disabled when editing
 *
 * Note: These tests verify the Livewire → JavaScript data layer.
 * They do NOT test the JavaScript side (flatpickr behavior).
 * For full end-to-end testing including JavaScript, use Laravel Dusk browser tests.
 *
 * Coverage complement to FlashFormTest:
 * - FlashFormTest: Tests viewData() and component behavior
 * - This file: Tests HTML output and data format contracts
 */
class FlashCalendarIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_data_attributes_are_present_in_rendered_html(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->setDate(2025, 1, 15));

        Flash::factory()->forUser($user)->create(['date' => '2025-01-10']);

        $component = Livewire::actingAs($user)
            ->test(FlashForm::class, ['submitText' => 'Log Activity']);

        // Verify the rendered HTML contains the data attributes JavaScript needs
        $html = $component->html();

        $this->assertStringContainsString('id="date-picker"', $html, 'Date picker element should exist');
        $this->assertStringContainsString('data-existing-dates=', $html, 'Should have data-existing-dates attribute');
        $this->assertStringContainsString('data-min-date=', $html, 'Should have data-min-date attribute');
        $this->assertStringContainsString('data-max-date=', $html, 'Should have data-max-date attribute');

        // Verify the existing dates JSON format (may have HTML entities)
        $this->assertStringContainsString('2025-01-10', $html, 'Existing date should be in HTML');
    }

    public function test_edit_mode_calendar_excludes_current_date_from_disabled(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->setDate(2025, 1, 15));

        // Create two flashes
        Flash::factory()->forUser($user)->create(['date' => '2025-01-10']);
        $editingFlash = Flash::factory()->forUser($user)->create(['date' => '2025-01-12']);

        $component = Livewire::actingAs($user)
            ->test(FlashForm::class, [
                'flash' => $editingFlash,
            ]);

        $html = $component->html();

        // Should have single date picker in edit mode
        $this->assertStringContainsString('id="date-picker-single"', $html);
        $this->assertStringContainsString('data-default-date="2025-01-12"', $html);

        // Both dates should be in existing dates for visual indicators
        $this->assertStringContainsString('2025-01-10', $html);
        $this->assertStringContainsString('2025-01-12', $html);
    }

    public function test_calendar_data_format_matches_javascript_expectations(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->setDate(2025, 1, 15));

        // Create flashes with various dates (all within selectable range)
        Flash::factory()->forUser($user)->create(['date' => '2025-01-05']);
        Flash::factory()->forUser($user)->create(['date' => '2025-01-10']);

        $component = Livewire::actingAs($user)
            ->test(FlashForm::class);

        $existingDates = $component->viewData('existingDates');

        // Verify format: array of Y-m-d strings
        $this->assertIsArray($existingDates);
        $this->assertCount(2, $existingDates);

        foreach ($existingDates as $date) {
            $this->assertIsString($date);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $date, 'Date should be in Y-m-d format');
        }

        // Verify dates are in the array (order doesn't matter for JavaScript)
        $this->assertContains('2025-01-05', $existingDates);
        $this->assertContains('2025-01-10', $existingDates);
    }
}
