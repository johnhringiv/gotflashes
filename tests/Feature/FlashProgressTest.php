<?php

namespace Tests\Feature;

use App\Models\Flash;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_card_displays_on_activities_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('logbook.index'));

        $response->assertStatus(200);
        $response->assertSee('Progress');
        $response->assertSee('Total Days');
    }

    public function test_progress_shows_correct_counts(): void
    {
        $user = User::factory()->create();

        // Create 5 sailing days in current year
        for ($i = 1; $i <= 5; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }

        // Create 2 maintenance days in current year
        for ($i = 10; $i <= 11; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'maintenance',
            ]);
        }

        $response = $this->actingAs($user)->get(route('logbook.index'));

        $response->assertStatus(200);
        $response->assertSee('7'); // Total: 5 sailing + 2 non-sailing
        $response->assertSee('5 sailing + 2 non-sailing');
    }

    public function test_progress_caps_non_sailing_days_at_5(): void
    {
        $user = User::factory()->create();

        // Create 3 sailing days
        for ($i = 1; $i <= 3; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }

        // Create 7 non-sailing days (4 maintenance + 3 race committee)
        for ($i = 10; $i <= 13; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'maintenance',
            ]);
        }
        for ($i = 20; $i <= 22; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'race_committee',
            ]);
        }

        $response = $this->actingAs($user)->get(route('logbook.index'));

        $response->assertStatus(200);
        $response->assertSee('8'); // Total: 3 sailing + 5 non-sailing (capped)
        $response->assertSee('3 sailing + 5 non-sailing');
    }

    public function test_progress_shows_next_milestone(): void
    {
        $user = User::factory()->create();

        // Create 7 sailing days
        for ($i = 1; $i <= 7; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }

        $response = $this->actingAs($user)->get(route('logbook.index'));

        $response->assertStatus(200);
        $response->assertSee('Next Award');
        $response->assertSee('10'); // Next milestone
        $response->assertSee('3 days to go'); // 10 - 7 = 3
    }

    public function test_progress_shows_second_milestone(): void
    {
        $user = User::factory()->create();

        // Create 15 sailing days (past first milestone of 10)
        for ($i = 1; $i <= 15; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }

        $response = $this->actingAs($user)->get(route('logbook.index'));

        $response->assertStatus(200);
        $response->assertSee('Next Award');
        $response->assertSee('25'); // Next milestone
        $response->assertSee('10 days to go'); // 25 - 15 = 10
    }

    public function test_progress_shows_completion_when_all_tiers_reached(): void
    {
        $user = User::factory()->create();

        // Create 50 sailing days (all tiers completed)
        for ($i = 1; $i <= 28; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }
        for ($i = 1; $i <= 22; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addMonth()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }

        $response = $this->actingAs($user)->get(route('logbook.index'));

        $response->assertStatus(200);
        $response->assertSee('Achievement');
        $response->assertSee('All tiers completed!');
    }

    public function test_progress_only_counts_current_year(): void
    {
        $user = User::factory()->create();

        // Create 5 sailing days in previous year
        for ($i = 1; $i <= 5; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->subYear()->startOfYear()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }

        // Create 3 sailing days in current year
        for ($i = 1; $i <= 3; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }

        $response = $this->actingAs($user)->get(route('logbook.index'));

        $response->assertStatus(200);
        // Check that we see the sailing count breakdown for current year only
        $response->assertSee('3 sailing + 0 non-sailing');
    }

    public function test_no_awards_shown_below_10_days(): void
    {
        $user = User::factory()->create();

        // Create 5 sailing days
        for ($i = 1; $i <= 5; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }

        $response = $this->actingAs($user)->get(route('logbook.index'));

        $response->assertStatus(200);
        $response->assertDontSee('bi-trophy-fill'); // No trophy icon
        $response->assertDontSee('10 Day Award');
    }

    public function test_bronze_award_shown_at_10_days(): void
    {
        $user = User::factory()->create();

        // Create 10 sailing days
        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }

        $response = $this->actingAs($user)->get(route('logbook.index'));

        $response->assertStatus(200);
        $response->assertSee('got_10_transparent.png'); // 10 Day badge image
        $response->assertSee('10 Day Award');
    }

    public function test_silver_award_shown_at_25_days(): void
    {
        $user = User::factory()->create();

        // Create 25 sailing days
        for ($i = 1; $i <= 25; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }

        $response = $this->actingAs($user)->get(route('logbook.index'));

        $response->assertStatus(200);
        $response->assertSee('got_10_transparent.png'); // 10 Day badge image (still earned)
        $response->assertSee('got_25_transparent.png'); // 25 Day badge image
        $response->assertSee('25 Day Award');
    }

    public function test_gold_award_shown_at_50_days(): void
    {
        $user = User::factory()->create();

        // Create 50 sailing days
        for ($i = 1; $i <= 28; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }
        for ($i = 1; $i <= 22; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addMonth()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }

        $response = $this->actingAs($user)->get(route('logbook.index'));

        $response->assertStatus(200);
        $response->assertSee('got_10_transparent.png'); // 10 Day badge image (still earned)
        $response->assertSee('got_25_transparent.png'); // 25 Day badge image (still earned)
        $response->assertSee('got_50_transparent.png'); // 50 Day badge image
        $response->assertSee('50 Day Award (Burgee)');
    }

    public function test_burgee_image_shown_when_all_tiers_completed(): void
    {
        $user = User::factory()->create();

        // Create 50+ sailing days
        for ($i = 1; $i <= 28; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }
        for ($i = 1; $i <= 22; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addMonth()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }

        $response = $this->actingAs($user)->get(route('logbook.index'));

        $response->assertStatus(200);
        $response->assertSee('burgee_50_transparent.png'); // Burgee image in "Next Award" stat
        $response->assertSee('All tiers completed!'); // Stat description
    }

    public function test_burgee_image_not_shown_below_50_days(): void
    {
        $user = User::factory()->create();

        // Create 49 sailing days (just below the threshold)
        for ($i = 1; $i <= 28; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }
        for ($i = 1; $i <= 21; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addMonth()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }

        $response = $this->actingAs($user)->get(route('logbook.index'));

        $response->assertStatus(200);
        $response->assertDontSee('burgee_50_transparent.png'); // No burgee yet
        $response->assertSee('1 days to go'); // Should show "1 days to go" for next milestone
    }
}
