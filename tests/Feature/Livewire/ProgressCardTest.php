<?php

namespace Tests\Feature\Livewire;

use App\Livewire\ProgressCard;
use App\Models\Flash;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProgressCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_renders_successfully(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ProgressCard::class)
            ->assertStatus(200)
            ->assertViewHas('totalFlashes')
            ->assertViewHas('sailingCount')
            ->assertViewHas('nonSailingCount')
            ->assertViewHas('earnedAwards')
            ->assertViewHas('currentYear');
    }

    public function test_displays_current_year(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->setDate(2025, 6, 15));

        Livewire::actingAs($user)
            ->test(ProgressCard::class)
            ->assertSet('currentYear', 2025)
            ->assertSee('2025 Progress');
    }

    public function test_calculates_total_flashes_correctly(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->setDate(2025, 6, 15));

        // Create 7 sailing days with unique dates
        Flash::factory()->forUser($user)->count(7)->sequence(
            fn ($sequence) => ['date' => now()->subDays($sequence->index + 1)->format('Y-m-d')]
        )->create([
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
        ]);

        // Create 3 non-sailing days with unique dates
        Flash::factory()->forUser($user)->count(3)->sequence(
            fn ($sequence) => ['date' => now()->subDays($sequence->index + 31)->format('Y-m-d')]
        )->create([
            'activity_type' => 'maintenance',
        ]);

        $component = Livewire::actingAs($user)
            ->test(ProgressCard::class);

        $this->assertEquals(10, $component->viewData('totalFlashes'));
        $this->assertEquals(7, $component->viewData('sailingCount'));
        $this->assertEquals(3, $component->viewData('nonSailingCount'));
    }

    public function test_caps_non_sailing_count_at_five(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->setDate(2025, 6, 15));

        // Create 5 sailing days with unique dates
        Flash::factory()->forUser($user)->count(5)->sequence(
            fn ($sequence) => ['date' => '2025-01-'.str_pad($sequence->index + 1, 2, '0', STR_PAD_LEFT)]
        )->create([
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
        ]);

        // Create 8 non-sailing days (more than the 5 cap) with unique dates
        Flash::factory()->forUser($user)->count(8)->sequence(
            fn ($sequence) => ['date' => '2025-01-'.str_pad($sequence->index + 16, 2, '0', STR_PAD_LEFT)]
        )->create([
            'activity_type' => 'maintenance',
        ]);

        $component = Livewire::actingAs($user)
            ->test(ProgressCard::class);

        $this->assertEquals(10, $component->viewData('totalFlashes')); // 5 sailing + 5 non-sailing (capped)
        $this->assertEquals(5, $component->viewData('sailingCount'));
        $this->assertEquals(5, $component->viewData('nonSailingCount')); // Capped at 5
    }

    public function test_shows_no_earned_awards_when_zero_flashes(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(ProgressCard::class);

        $this->assertEmpty($component->viewData('earnedAwards'));
    }

    public function test_shows_10_day_award_when_earned(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->setDate(2025, 6, 15));

        Flash::factory()->forUser($user)->count(10)->sequence(
            fn ($sequence) => ['date' => '2025-01-'.str_pad($sequence->index + 1, 2, '0', STR_PAD_LEFT)]
        )->create([
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
        ]);

        $component = Livewire::actingAs($user)
            ->test(ProgressCard::class);

        $earnedAwards = $component->viewData('earnedAwards');
        $this->assertContains(10, $earnedAwards);
    }

    public function test_shows_25_day_award_when_earned(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->setDate(2025, 6, 15));

        Flash::factory()->forUser($user)->count(25)->sequence(
            fn ($sequence) => ['date' => '2025-01-'.str_pad($sequence->index + 1, 2, '0', STR_PAD_LEFT)]
        )->create([
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
        ]);

        $component = Livewire::actingAs($user)
            ->test(ProgressCard::class);

        $earnedAwards = $component->viewData('earnedAwards');
        $this->assertContains(10, $earnedAwards);
        $this->assertContains(25, $earnedAwards);
    }

    public function test_shows_50_day_award_when_earned(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->setDate(2025, 12, 15));

        Flash::factory()->forUser($user)->count(50)->sequence(
            fn ($sequence) => [
                'date' => now()->setDate(2025, 1, 1)->addDays($sequence->index)->format('Y-m-d'),
            ]
        )->create([
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
        ]);

        $component = Livewire::actingAs($user)
            ->test(ProgressCard::class);

        $earnedAwards = $component->viewData('earnedAwards');
        $this->assertContains(10, $earnedAwards);
        $this->assertContains(25, $earnedAwards);
        $this->assertContains(50, $earnedAwards);
    }

    public function test_displays_next_milestone_correctly(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->setDate(2025, 6, 15));

        // Create 7 flashes (next milestone should be 10)
        Flash::factory()->forUser($user)->count(7)->sequence(
            fn ($sequence) => ['date' => '2025-01-'.str_pad($sequence->index + 1, 2, '0', STR_PAD_LEFT)]
        )->create([
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
        ]);

        $component = Livewire::actingAs($user)
            ->test(ProgressCard::class);

        $this->assertEquals(10, $component->viewData('nextMilestone'));
    }

    public function test_next_milestone_updates_correctly(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->setDate(2025, 6, 15));

        // Create 15 flashes (next milestone should be 25)
        Flash::factory()->forUser($user)->count(15)->sequence(
            fn ($sequence) => ['date' => '2025-01-'.str_pad($sequence->index + 1, 2, '0', STR_PAD_LEFT)]
        )->create([
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
        ]);

        $component = Livewire::actingAs($user)
            ->test(ProgressCard::class);

        $this->assertEquals(25, $component->viewData('nextMilestone'));
    }

    public function test_no_next_milestone_when_all_completed(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->setDate(2025, 12, 15));

        Flash::factory()->forUser($user)->count(50)->sequence(
            fn ($sequence) => [
                'date' => now()->setDate(2025, 1, 1)->addDays($sequence->index)->format('Y-m-d'),
            ]
        )->create([
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
        ]);

        $component = Livewire::actingAs($user)
            ->test(ProgressCard::class);

        $this->assertNull($component->viewData('nextMilestone'));
    }

    public function test_component_responds_to_flash_saved_event(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->setDate(2025, 6, 15));

        // Create 5 initial flashes
        Flash::factory()->forUser($user)->count(5)->sequence(
            fn ($sequence) => ['date' => '2025-01-'.str_pad($sequence->index + 1, 2, '0', STR_PAD_LEFT)]
        )->create([
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
        ]);

        $component = Livewire::actingAs($user)
            ->test(ProgressCard::class);

        $this->assertEquals(5, $component->viewData('totalFlashes'));

        // Add 5 more flashes
        Flash::factory()->forUser($user)->count(5)->sequence(
            fn ($sequence) => ['date' => '2025-02-'.str_pad($sequence->index + 1, 2, '0', STR_PAD_LEFT)]
        )->create([
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
        ]);

        // Dispatch event to trigger refresh
        $component->dispatch('flash-saved');
        $component->call('refresh');

        $this->assertEquals(10, $component->viewData('totalFlashes'));
    }

    public function test_component_responds_to_flash_deleted_event(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->setDate(2025, 6, 15));

        // Create 10 flashes
        Flash::factory()->forUser($user)->count(10)->sequence(
            fn ($sequence) => ['date' => '2025-01-'.str_pad($sequence->index + 1, 2, '0', STR_PAD_LEFT)]
        )->create([
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
        ]);

        $component = Livewire::actingAs($user)
            ->test(ProgressCard::class);

        $this->assertEquals(10, $component->viewData('totalFlashes'));

        // Delete a flash
        $user->flashes()->first()->delete();

        // Dispatch event to trigger refresh
        $component->dispatch('flash-deleted');
        $component->call('refresh');

        $this->assertEquals(9, $component->viewData('totalFlashes'));
    }

    public function test_progress_updates_after_flash_saved_event(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->setDate(2025, 6, 15));

        // Start with 5 flashes with unique dates
        Flash::factory()->forUser($user)->count(5)->sequence(
            fn ($sequence) => ['date' => '2025-01-'.str_pad($sequence->index + 1, 2, '0', STR_PAD_LEFT)]
        )->create([
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
        ]);

        $component = Livewire::actingAs($user)
            ->test(ProgressCard::class);

        $this->assertEquals(5, $component->viewData('totalFlashes'));

        // Add 5 more flashes with unique dates
        Flash::factory()->forUser($user)->count(5)->sequence(
            fn ($sequence) => ['date' => '2025-02-'.str_pad($sequence->index + 1, 2, '0', STR_PAD_LEFT)]
        )->create([
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
        ]);

        // Simulate flash-saved event
        $component->dispatch('flash-saved');
        $component->call('refresh');

        $this->assertEquals(10, $component->viewData('totalFlashes'));
    }

    public function test_only_counts_current_year_flashes(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->setDate(2025, 6, 15));

        // Create 5 flashes from 2024 with unique dates
        Flash::factory()->forUser($user)->count(5)->sequence(
            fn ($sequence) => ['date' => '2024-06-'.str_pad($sequence->index + 1, 2, '0', STR_PAD_LEFT)]
        )->create([
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
        ]);

        // Create 7 flashes from 2025 with unique dates
        Flash::factory()->forUser($user)->count(7)->sequence(
            fn ($sequence) => ['date' => '2025-01-'.str_pad($sequence->index + 1, 2, '0', STR_PAD_LEFT)]
        )->create([
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
        ]);

        $component = Livewire::actingAs($user)
            ->test(ProgressCard::class);

        // Should only count 2025 flashes
        $this->assertEquals(7, $component->viewData('totalFlashes'));
    }
}
