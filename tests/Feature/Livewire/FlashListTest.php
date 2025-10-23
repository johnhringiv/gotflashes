<?php

namespace Tests\Feature\Livewire;

use App\Livewire\FlashList;
use App\Models\Flash;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FlashListTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_renders_with_user_flashes(): void
    {
        $user = User::factory()->create();
        Flash::factory()->forUser($user)->count(3)->sequence(
            fn ($sequence) => ['date' => now()->subDays($sequence->index)->format('Y-m-d')]
        )->create();

        $component = Livewire::actingAs($user)
            ->test(FlashList::class);

        $component->assertStatus(200)
            ->assertViewHas('flashes');

        $flashes = $component->viewData('flashes');
        $this->assertEquals(3, $flashes->count());
    }

    public function test_component_renders_empty_state_when_no_flashes(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(FlashList::class)
            ->assertStatus(200)
            ->assertSee('No activities yet');
    }

    public function test_component_paginates_flashes(): void
    {
        $user = User::factory()->create();

        // Create 20 flashes with unique sequential dates
        Flash::factory()->forUser($user)->count(20)->sequence(
            fn ($sequence) => ['date' => now()->subDays($sequence->index)->format('Y-m-d')]
        )->create();

        $component = Livewire::actingAs($user)
            ->test(FlashList::class);

        $flashes = $component->viewData('flashes');
        $this->assertEquals(15, $flashes->count()); // Default pagination
        $this->assertTrue($flashes->hasPages());
    }

    public function test_component_orders_flashes_by_date_desc(): void
    {
        $user = User::factory()->create();
        Flash::factory()->forUser($user)->create(['date' => '2025-01-10']);
        Flash::factory()->forUser($user)->create(['date' => '2025-01-15']);
        Flash::factory()->forUser($user)->create(['date' => '2025-01-12']);

        $component = Livewire::actingAs($user)
            ->test(FlashList::class);

        $flashes = $component->viewData('flashes');
        $this->assertEquals('2025-01-15', $flashes->first()->date->format('Y-m-d'));
        $this->assertEquals('2025-01-10', $flashes->last()->date->format('Y-m-d'));
    }

    public function test_component_calculates_min_max_dates(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->setDate(2025, 2, 15));

        $component = Livewire::actingAs($user)
            ->test(FlashList::class);

        $minDate = $component->viewData('minDate');
        $maxDate = $component->viewData('maxDate');

        $this->assertEquals('2025-01-01', $minDate->format('Y-m-d'));
        $this->assertEquals('2025-02-16', $maxDate->format('Y-m-d'));
    }

    public function test_can_delete_flash_via_livewire(): void
    {
        $user = User::factory()->create();
        $flash = Flash::factory()->forUser($user)->create(['date' => now()->format('Y-m-d')]);

        Livewire::actingAs($user)
            ->test(FlashList::class)
            ->call('confirmDelete', $flash->id)
            ->assertSet('deletingFlashId', $flash->id)
            ->call('delete')
            ->assertDispatched('flash-deleted')
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('flashes', ['id' => $flash->id]);
    }

    public function test_delete_dispatches_success_toast(): void
    {
        $user = User::factory()->create();
        $flash = Flash::factory()->forUser($user)->create(['date' => now()->format('Y-m-d')]);

        Livewire::actingAs($user)
            ->test(FlashList::class)
            ->call('confirmDelete', $flash->id)
            ->call('delete')
            ->assertDispatched('toast');
    }

    public function test_cannot_delete_another_users_flash(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $flash = Flash::factory()->forUser($user2)->create();

        Livewire::actingAs($user1)
            ->test(FlashList::class)
            ->call('confirmDelete', $flash->id)
            ->call('delete')
            ->assertForbidden();

        // Verify flash was NOT deleted
        $this->assertDatabaseHas('flashes', ['id' => $flash->id]);
    }

    public function test_cannot_delete_flash_outside_editable_range(): void
    {
        $user = User::factory()->create();

        // Create a flash from previous year
        $flash = Flash::factory()->forUser($user)->create(['date' => '2024-06-15']);

        // Travel to February (grace period ended)
        $this->travelTo(now()->setDate(2025, 2, 15));

        Livewire::actingAs($user)
            ->test(FlashList::class)
            ->call('confirmDelete', $flash->id)
            ->call('delete')
            ->assertForbidden();

        // Verify flash was NOT deleted
        $this->assertDatabaseHas('flashes', ['id' => $flash->id]);
    }

    public function test_can_delete_previous_year_flash_during_grace_period(): void
    {
        $user = User::factory()->create();

        // Create a flash from previous year
        $flash = Flash::factory()->forUser($user)->create(['date' => '2024-06-15']);

        // Travel to January (grace period active)
        $this->travelTo(now()->setDate(2025, 1, 15));

        Livewire::actingAs($user)
            ->test(FlashList::class)
            ->call('confirmDelete', $flash->id)
            ->call('delete')
            ->assertDispatched('flash-deleted');

        $this->assertDatabaseMissing('flashes', ['id' => $flash->id]);
    }

    public function test_can_cancel_delete_confirmation(): void
    {
        $user = User::factory()->create();
        $flash = Flash::factory()->forUser($user)->create(['date' => now()->format('Y-m-d')]);

        $component = Livewire::actingAs($user)
            ->test(FlashList::class);

        // Open delete confirmation
        $component->call('confirmDelete', $flash->id);
        $this->assertEquals($flash->id, $component->get('deletingFlashId'));

        // Cancel delete
        $component->call('cancelDelete');
        $this->assertNull($component->get('deletingFlashId'));

        // Verify flash was NOT deleted
        $this->assertDatabaseHas('flashes', ['id' => $flash->id]);
    }

    public function test_refresh_method_resets_pagination(): void
    {
        $user = User::factory()->create();

        // Create 20 flashes with unique sequential dates
        Flash::factory()->forUser($user)->count(20)->sequence(
            fn ($sequence) => ['date' => now()->subDays($sequence->index)->format('Y-m-d')]
        )->create();

        $component = Livewire::actingAs($user)
            ->test(FlashList::class);

        // Go to page 2
        $component->call('gotoPage', 2);

        // Call refresh (simulating flash-saved or flash-deleted event)
        $component->call('refresh');

        // Should be back on page 1
        $flashes = $component->viewData('flashes');
        $this->assertEquals(1, $flashes->currentPage());
    }

    public function test_component_responds_to_flash_saved_event(): void
    {
        $user = User::factory()->create();

        // Create 20 flashes with unique sequential dates
        Flash::factory()->forUser($user)->count(20)->sequence(
            fn ($sequence) => ['date' => now()->subDays($sequence->index)->format('Y-m-d')]
        )->create();

        $component = Livewire::actingAs($user)
            ->test(FlashList::class);

        // Go to page 2
        $component->call('gotoPage', 2);

        // Dispatch the event that FlashForm would send
        $component->dispatch('flash-saved');

        // Component should refresh to page 1
        $flashes = $component->viewData('flashes');
        $this->assertEquals(1, $flashes->currentPage());
    }

    public function test_component_responds_to_flash_deleted_event(): void
    {
        $user = User::factory()->create();

        // Create 20 flashes with unique sequential dates
        Flash::factory()->forUser($user)->count(20)->sequence(
            fn ($sequence) => ['date' => now()->subDays($sequence->index)->format('Y-m-d')]
        )->create();

        $component = Livewire::actingAs($user)
            ->test(FlashList::class);

        // Go to page 2
        $component->call('gotoPage', 2);

        // Dispatch the event
        $component->dispatch('flash-deleted');

        // Component should refresh to page 1
        $flashes = $component->viewData('flashes');
        $this->assertEquals(1, $flashes->currentPage());
    }

    public function test_only_shows_authenticated_users_flashes(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Flash::factory()->forUser($user1)->count(3)->sequence(
            fn ($sequence) => ['date' => now()->subDays($sequence->index)->format('Y-m-d')]
        )->create();
        Flash::factory()->forUser($user2)->count(5)->sequence(
            fn ($sequence) => ['date' => now()->subDays($sequence->index)->format('Y-m-d')]
        )->create();

        $component = Livewire::actingAs($user1)
            ->test(FlashList::class);

        $flashes = $component->viewData('flashes');
        $this->assertEquals(3, $flashes->count());

        // Verify all flashes belong to user1
        foreach ($flashes as $flash) {
            $this->assertEquals($user1->id, $flash->user_id);
        }
    }

    public function test_can_open_edit_modal(): void
    {
        $user = User::factory()->create();
        $flash = Flash::factory()->forUser($user)->create(['date' => now()->format('Y-m-d')]);

        $component = Livewire::actingAs($user)
            ->test(FlashList::class);

        // Initially no modal open
        $this->assertNull($component->get('editingFlashId'));

        // Open edit modal
        $component->call('openEditModal', $flash->id);

        // Modal should be open with the flash ID
        $this->assertEquals($flash->id, $component->get('editingFlashId'));
    }

    public function test_can_close_edit_modal(): void
    {
        $user = User::factory()->create();
        $flash = Flash::factory()->forUser($user)->create(['date' => now()->format('Y-m-d')]);

        $component = Livewire::actingAs($user)
            ->test(FlashList::class);

        // Open modal
        $component->set('editingFlashId', $flash->id);
        $this->assertEquals($flash->id, $component->get('editingFlashId'));

        // Close modal
        $component->call('closeEditModal');
        $this->assertNull($component->get('editingFlashId'));
    }

    public function test_edit_modal_updates_flash(): void
    {
        $user = User::factory()->create();
        $flash = Flash::factory()->forUser($user)->create([
            'date' => now()->format('Y-m-d'),
            'activity_type' => 'sailing',
            'event_type' => 'practice',
            'notes' => 'Original notes',
        ]);

        // Open the modal
        $listComponent = Livewire::actingAs($user)
            ->test(FlashList::class)
            ->call('openEditModal', $flash->id);

        $this->assertEquals($flash->id, $listComponent->get('editingFlashId'));

        // Now test the edit form
        $formComponent = Livewire::actingAs($user)
            ->test(\App\Livewire\FlashForm::class, ['flash' => $flash, 'submitText' => 'Update'])
            ->set('activity_type', 'maintenance')
            ->set('notes', 'Updated notes')
            ->call('save');

        // Verify flash was updated
        $flash->refresh();
        $this->assertEquals('maintenance', $flash->activity_type);
        $this->assertEquals('Updated notes', $flash->notes);
    }

    public function test_edit_delete_buttons_appear_for_editable_flashes(): void
    {
        $user = User::factory()->create();

        // Create flash with today's date (within editable range)
        $flash = Flash::factory()->forUser($user)->create([
            'date' => now()->format('Y-m-d'),
        ]);

        $component = Livewire::actingAs($user)
            ->test(FlashList::class);

        // Check that Edit and Delete buttons appear in the rendered HTML
        $html = $component->html();
        $this->assertStringContainsString('wire:click="openEditModal('.$flash->id.')"', $html);
        $this->assertStringContainsString('wire:click="confirmDelete('.$flash->id.')"', $html);
        $this->assertStringContainsString('Edit', $html);
        $this->assertStringContainsString('Delete', $html);
    }

    public function test_edit_delete_buttons_do_not_appear_for_non_editable_flashes(): void
    {
        $user = User::factory()->create();

        // Create a flash from previous year
        $flash = Flash::factory()->forUser($user)->create(['date' => '2024-06-15']);

        // Travel to February (grace period ended, flash is read-only)
        $this->travelTo(now()->setDate(2025, 2, 15));

        $component = Livewire::actingAs($user)
            ->test(FlashList::class);

        // Check that Edit and Delete buttons do NOT appear
        $html = $component->html();
        $this->assertStringNotContainsString('wire:click="openEditModal('.$flash->id.')"', $html);
        $this->assertStringNotContainsString('wire:click="confirmDelete('.$flash->id.')"', $html);

        // The flash card should still render, just without buttons
        $this->assertStringContainsString($flash->date->format('M j, Y'), $html);
    }

    public function test_edit_delete_buttons_appear_during_grace_period(): void
    {
        $user = User::factory()->create();

        // Create a flash from previous year
        $flash = Flash::factory()->forUser($user)->create(['date' => '2024-06-15']);

        // Travel to January (grace period active, flash is editable)
        $this->travelTo(now()->setDate(2025, 1, 15));

        $component = Livewire::actingAs($user)
            ->test(FlashList::class);

        // Check that Edit and Delete buttons DO appear during grace period
        $html = $component->html();
        $this->assertStringContainsString('wire:click="openEditModal('.$flash->id.')"', $html);
        $this->assertStringContainsString('wire:click="confirmDelete('.$flash->id.')"', $html);
    }
}
