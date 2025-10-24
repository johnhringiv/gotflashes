<?php

namespace Tests\Feature\Livewire;

use App\Livewire\FlashForm;
use App\Livewire\FlashList;
use App\Livewire\ProgressCard;
use App\Models\Flash;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Integration tests for Livewire component communication via events
 */
class ComponentCommunicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_flash_form_save_dispatches_flash_saved_event(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(FlashForm::class, ['submitText' => 'Log Activity'])
            ->set('dates', ['2025-01-15'])
            ->set('activity_type', 'sailing')
            ->set('event_type', 'regatta')
            ->call('save')
            ->assertDispatched('flash-saved');
    }

    public function test_flash_list_delete_dispatches_flash_deleted_event(): void
    {
        $user = User::factory()->create();
        $flash = Flash::factory()->forUser($user)->create(['date' => now()->format('Y-m-d')]);

        Livewire::actingAs($user)
            ->test(FlashList::class)
            ->call('confirmDelete', $flash->id)
            ->call('delete')
            ->assertDispatched('flash-deleted');
    }

    public function test_saving_flash_updates_progress_card_data(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->setDate(2025, 6, 15));

        // Create progress card with initial state (0 flashes)
        $progressCard = Livewire::actingAs($user)
            ->test(ProgressCard::class);

        $this->assertEquals(0, $progressCard->viewData('totalFlashes'));

        // Save a flash
        Livewire::actingAs($user)
            ->test(FlashForm::class, ['submitText' => 'Log Activity'])
            ->set('dates', ['2025-01-15'])
            ->set('activity_type', 'sailing')
            ->set('event_type', 'regatta')
            ->call('save');

        // Simulate the event being received
        $progressCard->dispatch('flash-saved');
        $progressCard->call('refresh');

        // Progress card should now show 1 flash
        $this->assertEquals(1, $progressCard->viewData('totalFlashes'));
    }

    public function test_deleting_flash_updates_progress_card_data(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->setDate(2025, 6, 15));

        // Create a flash
        $flash = Flash::factory()->forUser($user)->create([
            'date' => '2025-01-15',
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
        ]);

        // Create progress card (should show 1 flash)
        $progressCard = Livewire::actingAs($user)
            ->test(ProgressCard::class);

        $this->assertEquals(1, $progressCard->viewData('totalFlashes'));

        // Delete the flash
        Livewire::actingAs($user)
            ->test(FlashList::class)
            ->call('confirmDelete', $flash->id)
            ->call('delete');

        // Simulate the event being received
        $progressCard->dispatch('flash-deleted');
        $progressCard->call('refresh');

        // Progress card should now show 0 flashes
        $this->assertEquals(0, $progressCard->viewData('totalFlashes'));
    }

    public function test_saving_flash_updates_flash_list(): void
    {
        $user = User::factory()->create();

        // Create flash list with initial state (0 flashes)
        $flashList = Livewire::actingAs($user)
            ->test(FlashList::class);

        $this->assertEquals(0, $flashList->viewData('flashes')->count());

        // Save a flash
        Livewire::actingAs($user)
            ->test(FlashForm::class, ['submitText' => 'Log Activity'])
            ->set('dates', ['2025-01-15'])
            ->set('activity_type', 'sailing')
            ->set('event_type', 'regatta')
            ->call('save');

        // Simulate the event being received
        $flashList->dispatch('flash-saved');
        $flashList->call('refresh');

        // Flash list should now show 1 flash
        $this->assertEquals(1, $flashList->viewData('flashes')->count());
    }

    public function test_multiple_component_updates_from_single_save(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->setDate(2025, 6, 15));

        // Initialize both components
        $progressCard = Livewire::actingAs($user)->test(ProgressCard::class);
        $flashList = Livewire::actingAs($user)->test(FlashList::class);

        $this->assertEquals(0, $progressCard->viewData('totalFlashes'));
        $this->assertEquals(0, $flashList->viewData('flashes')->count());

        // Save a flash
        Livewire::actingAs($user)
            ->test(FlashForm::class, ['submitText' => 'Log Activity'])
            ->set('dates', ['2025-01-15'])
            ->set('activity_type', 'sailing')
            ->set('event_type', 'regatta')
            ->call('save')
            ->assertDispatched('flash-saved');

        // Both components should respond to the same event
        $progressCard->dispatch('flash-saved')->call('refresh');
        $flashList->dispatch('flash-saved')->call('refresh');

        $this->assertEquals(1, $progressCard->viewData('totalFlashes'));
        $this->assertEquals(1, $flashList->viewData('flashes')->count());
    }

    public function test_form_dispatches_toast_event_with_correct_data(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(FlashForm::class, ['submitText' => 'Log Activity'])
            ->set('dates', ['2025-01-15'])
            ->set('activity_type', 'sailing')
            ->set('event_type', 'regatta')
            ->call('save')
            ->assertDispatched('toast');
    }

    public function test_form_dispatches_warning_toast_for_non_sailing_limit(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->setDate(2025, 1, 15));

        // Create 5 non-sailing activities with unique dates
        for ($i = 1; $i <= 5; $i++) {
            Flash::factory()->forUser($user)->create([
                'date' => "2025-01-0{$i}",
                'activity_type' => 'maintenance',
            ]);
        }

        // Try to add 6th non-sailing activity (use date within range: today + 1)
        Livewire::actingAs($user)
            ->test(FlashForm::class, ['submitText' => 'Log Activity'])
            ->set('dates', ['2025-01-15']) // Use current date from travelTo
            ->set('activity_type', 'race_committee')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');
    }

    public function test_delete_dispatches_toast_event_with_correct_data(): void
    {
        $user = User::factory()->create();
        $flash = Flash::factory()->forUser($user)->create(['date' => now()->format('Y-m-d')]);

        Livewire::actingAs($user)
            ->test(FlashList::class)
            ->call('confirmDelete', $flash->id)
            ->call('delete')
            ->assertDispatched('toast');
    }

    public function test_flash_list_resets_pagination_on_flash_saved(): void
    {
        $user = User::factory()->create();

        // Create 20 flashes with unique dates to trigger pagination
        for ($i = 1; $i <= 20; $i++) {
            Flash::factory()->forUser($user)->create([
                'date' => now()->subDays($i)->format('Y-m-d'),
            ]);
        }

        $flashList = Livewire::actingAs($user)
            ->test(FlashList::class);

        // Go to page 2
        $flashList->call('gotoPage', 2);
        $this->assertEquals(2, $flashList->viewData('flashes')->currentPage());

        // Save a new flash (via event simulation)
        $flashList->dispatch('flash-saved');
        $flashList->call('refresh');

        // Should be reset to page 1
        $this->assertEquals(1, $flashList->viewData('flashes')->currentPage());
    }

    public function test_flash_list_resets_pagination_on_flash_deleted(): void
    {
        $user = User::factory()->create();

        // Create 20 flashes with unique sequential dates
        Flash::factory()->forUser($user)->count(20)->sequence(
            fn ($sequence) => ['date' => now()->subDays($sequence->index)->format('Y-m-d')]
        )->create();

        $flashList = Livewire::actingAs($user)
            ->test(FlashList::class);

        // Go to page 2
        $flashList->call('gotoPage', 2);

        // Delete a flash (via event simulation)
        $flashList->dispatch('flash-deleted');
        $flashList->call('refresh');

        // Should be reset to page 1
        $this->assertEquals(1, $flashList->viewData('flashes')->currentPage());
    }
}
