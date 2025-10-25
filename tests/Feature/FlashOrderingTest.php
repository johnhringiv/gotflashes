<?php

namespace Tests\Feature;

use App\Models\Flash;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_flashes_are_ordered_by_date_descending(): void
    {
        $user = User::factory()->create();

        // Create flashes with different dates (in random creation order)
        $flash1 = Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2025-01-15',
            'activity_type' => 'sailing',
        ]);

        $flash2 = Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2025-01-20', // Newer date
            'activity_type' => 'sailing',
        ]);

        $flash3 = Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2025-01-10', // Older date
            'activity_type' => 'sailing',
        ]);

        $response = $this->actingAs($user)->get(route('logbook.index'));

        $response->assertStatus(200);

        // The order should be: Jan 20, Jan 15, Jan 10 (newest first)
        $response->assertSeeInOrder([
            'Jan 20, 2025',
            'Jan 15, 2025',
            'Jan 10, 2025',
        ]);
    }

    public function test_flashes_ordered_by_activity_date_not_creation_time(): void
    {
        $user = User::factory()->create();

        // Create flash with older activity date but newer creation time
        sleep(1); // Ensure different timestamps
        $newerCreation = Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2025-01-10',
            'activity_type' => 'sailing',
        ]);

        sleep(1);
        // Create flash with newer activity date but older creation time
        $olderCreation = Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2025-01-20',
            'activity_type' => 'sailing',
        ]);

        $response = $this->actingAs($user)->get(route('logbook.index'));

        $response->assertStatus(200);

        // Should be ordered by activity date (Jan 20 first), not creation time
        $content = $response->getContent();
        $pos20 = strpos($content, 'Jan 20, 2025');
        $pos10 = strpos($content, 'Jan 10, 2025');

        $this->assertLessThan($pos10, $pos20, 'Flash with date Jan 20, 2025 should appear before Jan 10, 2025');
    }

    public function test_most_recent_activities_appear_first(): void
    {
        $user = User::factory()->create();

        // Create activities spanning several months
        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2025-03-15',
            'activity_type' => 'sailing',
        ]);

        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2025-01-05',
            'activity_type' => 'sailing',
        ]);

        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2025-02-20',
            'activity_type' => 'sailing',
        ]);

        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2024-12-25',
            'activity_type' => 'sailing',
        ]);

        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2025-04-01',
            'activity_type' => 'sailing',
        ]);

        $response = $this->actingAs($user)->get(route('logbook.index'));

        $response->assertStatus(200);

        // Should see dates in descending order (formatted as "M j, Y")
        $response->assertSeeInOrder([
            'Apr 1, 2025',
            'Mar 15, 2025',
            'Feb 20, 2025',
            'Jan 5, 2025',
            'Dec 25, 2024',
        ]);
    }

    public function test_just_logged_badge_appears_for_todays_entries(): void
    {
        $user = User::factory()->create();

        // Create a flash logged today
        $todayFlash = Flash::factory()->create([
            'user_id' => $user->id,
            'date' => now()->subDays(5)->format('Y-m-d'), // Old activity date
            'activity_type' => 'sailing',
            'created_at' => now(), // But logged today
        ]);

        $response = $this->actingAs($user)->get(route('logbook.index'));

        $response->assertStatus(200);
        $response->assertSee('Just logged');
    }

    public function test_just_logged_badge_does_not_appear_for_old_entries(): void
    {
        $user = User::factory()->create();

        // Create a flash logged yesterday
        $oldFlash = Flash::factory()->create([
            'user_id' => $user->id,
            'date' => now()->subDays(5)->format('Y-m-d'),
            'activity_type' => 'sailing',
            'created_at' => now()->subDay(), // Logged yesterday
        ]);

        $response = $this->actingAs($user)->get(route('logbook.index'));

        $response->assertStatus(200);
        $response->assertDontSee('Just logged');
    }

    public function test_just_logged_badge_provides_visual_feedback_for_recent_entry(): void
    {
        $user = User::factory()->create();

        // Create multiple flashes - one logged today, others logged in the past
        $oldFlash1 = Flash::factory()->create([
            'user_id' => $user->id,
            'date' => now()->format('Y-m-d'),
            'activity_type' => 'sailing',
            'created_at' => now()->subDays(3),
        ]);

        $todayFlash = Flash::factory()->create([
            'user_id' => $user->id,
            'date' => now()->subDays(10)->format('Y-m-d'), // Old sailing date
            'activity_type' => 'sailing',
            'created_at' => now(), // But logged today
        ]);

        $oldFlash2 = Flash::factory()->create([
            'user_id' => $user->id,
            'date' => now()->subDays(5)->format('Y-m-d'),
            'activity_type' => 'sailing',
            'created_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($user)->get(route('logbook.index'));

        $response->assertStatus(200);

        // Should see exactly one "Just logged" badge
        $content = $response->getContent();
        $justLoggedCount = substr_count($content, 'Just logged');
        $this->assertEquals(1, $justLoggedCount, 'Should have exactly one "Just logged" badge');
    }
}
