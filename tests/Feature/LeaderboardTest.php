<?php

namespace Tests\Feature;

use App\Models\Flash;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaderboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_leaderboard_is_publicly_accessible(): void
    {
        $response = $this->get('/leaderboard');

        $response->assertStatus(200);
        $response->assertViewIs('leaderboard.index');
    }

    public function test_leaderboard_shows_users_ranked_by_2025_flashes(): void
    {
        // Create users with different flash counts for 2025
        $user1 = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Smith']);
        $user2 = User::factory()->create(['first_name' => 'Bob', 'last_name' => 'Jones']);
        $user3 = User::factory()->create(['first_name' => 'Charlie', 'last_name' => 'Brown']);

        // User1: 5 flashes in 2025
        for ($i = 1; $i <= 5; $i++) {
            Flash::factory()->create([
                'user_id' => $user1->id,
                'date' => "2025-01-{$i}",
            ]);
        }

        // User2: 10 flashes in 2025
        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->create([
                'user_id' => $user2->id,
                'date' => "2025-02-{$i}",
            ]);
        }

        // User3: 3 flashes in 2025
        for ($i = 1; $i <= 3; $i++) {
            Flash::factory()->create([
                'user_id' => $user3->id,
                'date' => "2025-03-{$i}",
            ]);
        }

        $response = $this->get('/leaderboard');

        $response->assertStatus(200);

        // Check that users are ordered by flash count (highest first)
        $response->assertSeeInOrder([
            'Bob Jones',
            '10',
            'Alice Smith',
            '5',
            'Charlie Brown',
            '3',
        ]);
    }

    public function test_leaderboard_only_shows_2025_flashes(): void
    {
        $user = User::factory()->create(['first_name' => 'Test', 'last_name' => 'User']);

        // Create flashes in 2024
        for ($i = 1; $i <= 5; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => "2024-06-{$i}",
            ]);
        }

        // Create flashes in 2025
        for ($i = 1; $i <= 3; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => "2025-06-{$i}",
            ]);
        }

        $response = $this->get('/leaderboard');

        $response->assertStatus(200);
        $response->assertSee('Test User');
        $response->assertSee('3'); // Only 2025 flashes counted
    }

    public function test_leaderboard_excludes_users_with_no_2025_flashes(): void
    {
        // User with flashes only in 2024
        $user1 = User::factory()->create(['first_name' => 'Old', 'last_name' => 'User']);
        for ($i = 1; $i <= 5; $i++) {
            Flash::factory()->create([
                'user_id' => $user1->id,
                'date' => "2024-01-{$i}",
            ]);
        }

        // User with flashes in 2025
        $user2 = User::factory()->create(['first_name' => 'Current', 'last_name' => 'User']);
        for ($i = 1; $i <= 3; $i++) {
            Flash::factory()->create([
                'user_id' => $user2->id,
                'date' => "2025-01-{$i}",
            ]);
        }

        $response = $this->get('/leaderboard');

        $response->assertStatus(200);
        $response->assertDontSee('Old User');
        $response->assertSee('Current User');
    }

    public function test_leaderboard_displays_user_information(): void
    {
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'district' => 5,
            'fleet_number' => 123,
            'yacht_club' => 'Test Yacht Club',
        ]);

        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2025-01-15',
        ]);

        $response = $this->get('/leaderboard');

        $response->assertStatus(200);
        $response->assertSee('John Doe');
        $response->assertSee('5'); // district
        $response->assertSee('123'); // fleet number
        $response->assertSee('Test Yacht Club');
    }

    public function test_leaderboard_handles_missing_user_information(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'district' => null,
            'fleet_number' => null,
            'yacht_club' => null,
        ]);

        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2025-01-15',
        ]);

        $response = $this->get('/leaderboard');

        $response->assertStatus(200);
        $response->assertSee('Jane Smith');
        $response->assertSee('—'); // em dash for missing data
    }

    public function test_leaderboard_shows_empty_state_when_no_2025_flashes(): void
    {
        // No users or flashes
        $response = $this->get('/leaderboard');

        $response->assertStatus(200);
        $response->assertSee('No flashes logged yet for 2025');
    }

    public function test_leaderboard_paginates_results(): void
    {
        // Create 20 users with flashes (more than default pagination limit of 15)
        $users = [];
        for ($i = 1; $i <= 20; $i++) {
            $user = User::factory()->create([
                'first_name' => sprintf('Alpha%02d', $i), // Use zero-padded names for consistent sorting
                'last_name' => 'Test',
            ]);

            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => '2025-01-15',
            ]);

            $users[] = $user;
        }

        $response = $this->get('/leaderboard');

        $response->assertStatus(200);

        // Should see first 15 users (Alpha01 through Alpha15)
        $response->assertSee('Alpha01 Test');
        $response->assertSee('Alpha15 Test');

        // Should NOT see users 16-20 on first page
        $response->assertDontSee('Alpha16 Test', false);
        $response->assertDontSee('Alpha20 Test', false);
    }

    public function test_leaderboard_ranks_correctly_with_equal_flash_counts(): void
    {
        // Create users with same flash count - should be ordered alphabetically
        $user1 = User::factory()->create(['first_name' => 'Zara', 'last_name' => 'Adams']);
        $user2 = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Brown']);
        $user3 = User::factory()->create(['first_name' => 'Bob', 'last_name' => 'Carter']);

        // All users get 5 flashes
        for ($i = 1; $i <= 5; $i++) {
            Flash::factory()->create([
                'user_id' => $user1->id,
                'date' => "2025-01-{$i}",
            ]);
            Flash::factory()->create([
                'user_id' => $user2->id,
                'date' => "2025-02-{$i}",
            ]);
            Flash::factory()->create([
                'user_id' => $user3->id,
                'date' => "2025-03-{$i}",
            ]);
        }

        $response = $this->get('/leaderboard');

        $response->assertStatus(200);

        // When tied, should be ordered alphabetically by first name
        $response->assertSeeInOrder([
            'Alice Brown',
            'Bob Carter',
            'Zara Adams',
        ]);
    }

    public function test_leaderboard_counts_all_activity_types(): void
    {
        $user = User::factory()->create(['first_name' => 'Test', 'last_name' => 'User']);

        // Create different activity types
        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2025-01-10',
            'activity_type' => 'sailing',
        ]);

        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2025-01-11',
            'activity_type' => 'maintenance',
        ]);

        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2025-01-12',
            'activity_type' => 'race_committee',
        ]);

        $response = $this->get('/leaderboard');

        $response->assertStatus(200);
        $response->assertSee('Test User');
        $response->assertSee('3'); // All activity types counted
    }

    public function test_leaderboard_highlights_authenticated_user(): void
    {
        $currentUser = User::factory()->create(['first_name' => 'Current', 'last_name' => 'User']);
        $otherUser = User::factory()->create(['first_name' => 'Other', 'last_name' => 'User']);

        // Give both users some flashes
        Flash::factory()->create([
            'user_id' => $currentUser->id,
            'date' => '2025-01-10',
            'activity_type' => 'sailing',
        ]);

        Flash::factory()->create([
            'user_id' => $otherUser->id,
            'date' => '2025-01-11',
            'activity_type' => 'sailing',
        ]);

        $response = $this->actingAs($currentUser)->get('/leaderboard');

        $response->assertStatus(200);
        $response->assertSee('Current User');
        $response->assertSee('You'); // Badge showing current user
        $response->assertSee('bg-primary/10'); // Highlighted row styling
    }

    public function test_leaderboard_does_not_highlight_for_guest_users(): void
    {
        $user = User::factory()->create(['first_name' => 'Test', 'last_name' => 'User']);

        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2025-01-10',
            'activity_type' => 'sailing',
        ]);

        $response = $this->get('/leaderboard');

        $response->assertStatus(200);
        $response->assertSee('Test User');
        $response->assertDontSee('You'); // No "You" badge for guests
    }

    public function test_leaderboard_only_highlights_current_user_row(): void
    {
        $user1 = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Smith']);
        $user2 = User::factory()->create(['first_name' => 'Bob', 'last_name' => 'Jones']);
        $user3 = User::factory()->create(['first_name' => 'Charlie', 'last_name' => 'Brown']);

        // Create flashes for all users
        foreach ([$user1, $user2, $user3] as $user) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => '2025-01-10',
                'activity_type' => 'sailing',
            ]);
        }

        // Log in as user2 (Bob)
        $response = $this->actingAs($user2)->get('/leaderboard');

        $response->assertStatus(200);
        $response->assertSee('Alice Smith');
        $response->assertSee('Bob Jones');
        $response->assertSee('Charlie Brown');

        // Check HTML contains only one "You" badge
        $content = $response->getContent();
        $youBadgeCount = substr_count($content, 'You</span>');
        $this->assertEquals(1, $youBadgeCount, 'Should only have one "You" badge');
    }

    public function test_leaderboard_caps_non_sailing_days_at_5(): void
    {
        $user = User::factory()->create(['first_name' => 'Test', 'last_name' => 'User']);

        // Create 3 sailing days
        for ($i = 1; $i <= 3; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => "2025-01-{$i}",
                'activity_type' => 'sailing',
            ]);
        }

        // Create 4 maintenance days
        for ($i = 10; $i <= 13; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => "2025-01-{$i}",
                'activity_type' => 'maintenance',
            ]);
        }

        // Create 3 race committee days
        for ($i = 20; $i <= 22; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => "2025-01-{$i}",
                'activity_type' => 'race_committee',
            ]);
        }

        // Total: 3 sailing + 7 non-sailing days, but non-sailing capped at 5
        // Expected: 3 + 5 = 8

        $response = $this->get('/leaderboard');

        $response->assertStatus(200);
        $response->assertSee('Test User');
        $response->assertSee('8'); // 3 sailing + 5 non-sailing (capped)
    }

    public function test_leaderboard_allows_unlimited_sailing_days(): void
    {
        $user = User::factory()->create(['first_name' => 'Test', 'last_name' => 'User']);

        // Create 50 sailing days
        for ($i = 1; $i <= 28; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => "2025-01-{$i}",
                'activity_type' => 'sailing',
            ]);
        }
        for ($i = 1; $i <= 22; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => "2025-02-{$i}",
                'activity_type' => 'sailing',
            ]);
        }

        // Create 3 maintenance days (non-sailing)
        for ($i = 23; $i <= 25; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => "2025-02-{$i}",
                'activity_type' => 'maintenance',
            ]);
        }

        // Total: 50 sailing + 3 non-sailing = 53

        $response = $this->get('/leaderboard');

        $response->assertStatus(200);
        $response->assertSee('Test User');
        $response->assertSee('53'); // All 50 sailing days + 3 non-sailing counted
    }

    public function test_leaderboard_defaults_to_sailor_tab(): void
    {
        $response = $this->get('/leaderboard');

        $response->assertStatus(200);
        $response->assertSee('tab-active');
        $response->assertSee('Sailor');
    }

    public function test_fleet_tab_shows_fleet_rankings(): void
    {
        // Create users in different fleets
        $user1 = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Smith', 'fleet_number' => 100]);
        $user2 = User::factory()->create(['first_name' => 'Bob', 'last_name' => 'Jones', 'fleet_number' => 100]);
        $user3 = User::factory()->create(['first_name' => 'Charlie', 'last_name' => 'Brown', 'fleet_number' => 200]);

        // Fleet 100: Alice (5 flashes) + Bob (3 flashes) = 8 total
        for ($i = 1; $i <= 5; $i++) {
            Flash::factory()->create([
                'user_id' => $user1->id,
                'date' => "2025-01-{$i}",
                'activity_type' => 'sailing',
            ]);
        }
        for ($i = 10; $i <= 12; $i++) {
            Flash::factory()->create([
                'user_id' => $user2->id,
                'date' => "2025-01-{$i}",
                'activity_type' => 'sailing',
            ]);
        }

        // Fleet 200: Charlie (10 flashes)
        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->create([
                'user_id' => $user3->id,
                'date' => "2025-02-{$i}",
                'activity_type' => 'sailing',
            ]);
        }

        $response = $this->get('/leaderboard?tab=fleet');

        $response->assertStatus(200);
        $response->assertSee('Fleet 200');
        $response->assertSee('Fleet 100');
        $response->assertSeeInOrder(['Fleet 200', '10', 'Fleet 100', '8']);
    }

    public function test_fleet_tab_shows_member_count(): void
    {
        // Create 3 users in fleet 100
        for ($i = 1; $i <= 3; $i++) {
            $user = User::factory()->create(['fleet_number' => 100]);
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => "2025-01-{$i}",
                'activity_type' => 'sailing',
            ]);
        }

        $response = $this->get('/leaderboard?tab=fleet');

        $response->assertStatus(200);
        $response->assertSee('Fleet 100');
        $response->assertSee('3'); // Member count
    }

    public function test_fleet_tab_excludes_users_without_fleet_number(): void
    {
        $user1 = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Smith', 'fleet_number' => 100]);
        $user2 = User::factory()->create(['first_name' => 'Bob', 'last_name' => 'Jones', 'fleet_number' => null]);

        Flash::factory()->create([
            'user_id' => $user1->id,
            'date' => '2025-01-01',
            'activity_type' => 'sailing',
        ]);

        Flash::factory()->create([
            'user_id' => $user2->id,
            'date' => '2025-01-02',
            'activity_type' => 'sailing',
        ]);

        $response = $this->get('/leaderboard?tab=fleet');

        $response->assertStatus(200);
        $response->assertSee('Fleet 100');
        $response->assertDontSee('Bob Jones'); // User without fleet not shown
    }

    public function test_district_tab_shows_district_rankings(): void
    {
        // Create users in different districts
        $user1 = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Smith', 'district' => 5]);
        $user2 = User::factory()->create(['first_name' => 'Bob', 'last_name' => 'Jones', 'district' => 5]);
        $user3 = User::factory()->create(['first_name' => 'Charlie', 'last_name' => 'Brown', 'district' => 10]);

        // District 5: Alice (5 flashes) + Bob (3 flashes) = 8 total
        for ($i = 1; $i <= 5; $i++) {
            Flash::factory()->create([
                'user_id' => $user1->id,
                'date' => "2025-01-{$i}",
                'activity_type' => 'sailing',
            ]);
        }
        for ($i = 10; $i <= 12; $i++) {
            Flash::factory()->create([
                'user_id' => $user2->id,
                'date' => "2025-01-{$i}",
                'activity_type' => 'sailing',
            ]);
        }

        // District 10: Charlie (10 flashes)
        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->create([
                'user_id' => $user3->id,
                'date' => "2025-02-{$i}",
                'activity_type' => 'sailing',
            ]);
        }

        $response = $this->get('/leaderboard?tab=district');

        $response->assertStatus(200);
        $response->assertSee('District 10');
        $response->assertSee('District 5');
        $response->assertSeeInOrder(['District 10', '10', 'District 5', '8']);
    }

    public function test_district_tab_shows_member_count(): void
    {
        // Create 3 users in district 5
        for ($i = 1; $i <= 3; $i++) {
            $user = User::factory()->create(['district' => 5]);
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => "2025-01-{$i}",
                'activity_type' => 'sailing',
            ]);
        }

        $response = $this->get('/leaderboard?tab=district');

        $response->assertStatus(200);
        $response->assertSee('District 5');
        $response->assertSee('3'); // Member count
    }

    public function test_district_tab_excludes_users_without_district(): void
    {
        $user1 = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Smith', 'district' => 5]);
        $user2 = User::factory()->create(['first_name' => 'Bob', 'last_name' => 'Jones', 'district' => null]);

        Flash::factory()->create([
            'user_id' => $user1->id,
            'date' => '2025-01-01',
            'activity_type' => 'sailing',
        ]);

        Flash::factory()->create([
            'user_id' => $user2->id,
            'date' => '2025-01-02',
            'activity_type' => 'sailing',
        ]);

        $response = $this->get('/leaderboard?tab=district');

        $response->assertStatus(200);
        $response->assertSee('District 5');
        $response->assertDontSee('Bob Jones'); // User without district not shown
    }

    public function test_invalid_tab_parameter_defaults_to_sailor(): void
    {
        $response = $this->get('/leaderboard?tab=invalid');

        $response->assertStatus(200);
        $response->assertSee('Sailor');
    }

    public function test_fleet_tab_caps_non_sailing_days_at_5_per_member(): void
    {
        // Create 2 users in fleet 100
        $user1 = User::factory()->create(['fleet_number' => 100]);
        $user2 = User::factory()->create(['fleet_number' => 100]);

        // User 1: 2 sailing + 3 maintenance
        for ($i = 1; $i <= 2; $i++) {
            Flash::factory()->create([
                'user_id' => $user1->id,
                'date' => "2025-01-{$i}",
                'activity_type' => 'sailing',
            ]);
        }
        for ($i = 10; $i <= 12; $i++) {
            Flash::factory()->create([
                'user_id' => $user1->id,
                'date' => "2025-01-{$i}",
                'activity_type' => 'maintenance',
            ]);
        }

        // User 2: 1 sailing + 7 race_committee (should cap at 5)
        Flash::factory()->create([
            'user_id' => $user2->id,
            'date' => '2025-02-01',
            'activity_type' => 'sailing',
        ]);
        for ($i = 10; $i <= 16; $i++) {
            Flash::factory()->create([
                'user_id' => $user2->id,
                'date' => "2025-02-{$i}",
                'activity_type' => 'race_committee',
            ]);
        }

        // Expected: (2 + 3) + (1 + 5) = 11 total

        $response = $this->get('/leaderboard?tab=fleet');

        $response->assertStatus(200);
        $response->assertSee('Fleet 100');
        $response->assertSee('11');
    }

    public function test_district_tab_caps_non_sailing_days_at_5_per_member(): void
    {
        // Create 2 users in district 5
        $user1 = User::factory()->create(['district' => 5]);
        $user2 = User::factory()->create(['district' => 5]);

        // User 1: 2 sailing + 3 maintenance
        for ($i = 1; $i <= 2; $i++) {
            Flash::factory()->create([
                'user_id' => $user1->id,
                'date' => "2025-01-{$i}",
                'activity_type' => 'sailing',
            ]);
        }
        for ($i = 10; $i <= 12; $i++) {
            Flash::factory()->create([
                'user_id' => $user1->id,
                'date' => "2025-01-{$i}",
                'activity_type' => 'maintenance',
            ]);
        }

        // User 2: 1 sailing + 7 race_committee (should cap at 5)
        Flash::factory()->create([
            'user_id' => $user2->id,
            'date' => '2025-02-01',
            'activity_type' => 'sailing',
        ]);
        for ($i = 10; $i <= 16; $i++) {
            Flash::factory()->create([
                'user_id' => $user2->id,
                'date' => "2025-02-{$i}",
                'activity_type' => 'race_committee',
            ]);
        }

        // Expected: (2 + 3) + (1 + 5) = 11 total

        $response = $this->get('/leaderboard?tab=district');

        $response->assertStatus(200);
        $response->assertSee('District 5');
        $response->assertSee('11');
    }

    public function test_sailor_leaderboard_tie_breaking_by_sailing_count(): void
    {
        // User A: 10 total (8 sailing + 2 non-sailing)
        $userA = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Smith']);
        for ($i = 1; $i <= 8; $i++) {
            Flash::factory()->forUser($userA)->sailing()->create(['date' => "2025-01-{$i}"]);
        }
        Flash::factory()->forUser($userA)->maintenance()->create(['date' => '2025-01-09']);
        Flash::factory()->forUser($userA)->maintenance()->create(['date' => '2025-01-10']);

        // User B: 10 total (10 sailing + 0 non-sailing) - should rank higher
        $userB = User::factory()->create(['first_name' => 'Bob', 'last_name' => 'Jones']);
        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->forUser($userB)->sailing()->create(['date' => "2025-01-{$i}"]);
        }

        $response = $this->get('/leaderboard');

        $response->assertStatus(200);
        // Bob should appear before Alice (more sailing days)
        $response->assertSeeInOrder(['Bob Jones', 'Alice Smith']);
    }

    public function test_sailor_leaderboard_tie_breaking_by_first_entry(): void
    {
        // Both users have 10 sailing days, but User A entered first
        $userA = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Smith']);
        $this->travel(-5)->days();
        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->forUser($userA)->sailing()->create(['date' => "2025-01-{$i}"]);
        }
        $this->travelBack();

        $userB = User::factory()->create(['first_name' => 'Bob', 'last_name' => 'Jones']);
        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->forUser($userB)->sailing()->create(['date' => "2025-01-{$i}"]);
        }

        $response = $this->get('/leaderboard');

        $response->assertStatus(200);
        // Alice should appear before Bob (entered earlier)
        $response->assertSeeInOrder(['Alice Smith', 'Bob Jones']);
    }

    public function test_sailor_leaderboard_tie_breaking_alphabetical(): void
    {
        // Both users have same count, same sailing days, entries at same time
        $userA = User::factory()->create(['first_name' => 'Zara', 'last_name' => 'Adams']);
        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->forUser($userA)->sailing()->create(['date' => "2025-01-{$i}"]);
        }

        $userB = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Baker']);
        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->forUser($userB)->sailing()->create(['date' => "2025-01-{$i}"]);
        }

        $response = $this->get('/leaderboard');

        $response->assertStatus(200);
        // Alice should appear before Zara (alphabetical)
        $response->assertSeeInOrder(['Alice Baker', 'Zara Adams']);
    }

    public function test_fleet_leaderboard_tie_breaking(): void
    {
        // Fleet 100: 20 total (15 sailing + 5 non-sailing)
        $user1 = User::factory()->create(['fleet_number' => 100]);
        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->forUser($user1)->sailing()->create(['date' => "2025-01-{$i}"]);
        }
        for ($i = 11; $i <= 13; $i++) {
            Flash::factory()->forUser($user1)->maintenance()->create(['date' => "2025-01-{$i}"]);
        }

        $user2 = User::factory()->create(['fleet_number' => 100]);
        for ($i = 1; $i <= 5; $i++) {
            Flash::factory()->forUser($user2)->sailing()->create(['date' => "2025-02-{$i}"]);
        }
        Flash::factory()->forUser($user2)->maintenance()->create(['date' => '2025-02-06']);
        Flash::factory()->forUser($user2)->maintenance()->create(['date' => '2025-02-07']);

        // Fleet 200: 20 total (20 sailing + 0 non-sailing) - should rank higher
        $user3 = User::factory()->create(['fleet_number' => 200]);
        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->forUser($user3)->sailing()->create(['date' => "2025-01-{$i}"]);
        }

        $user4 = User::factory()->create(['fleet_number' => 200]);
        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->forUser($user4)->sailing()->create(['date' => "2025-02-{$i}"]);
        }

        $response = $this->get('/leaderboard?tab=fleet');

        $response->assertStatus(200);
        // Fleet 200 should appear before Fleet 100 (more sailing days)
        $response->assertSeeInOrder(['Fleet 200', 'Fleet 100']);
    }

    public function test_district_leaderboard_tie_breaking(): void
    {
        // District 5: 20 total (15 sailing + 5 non-sailing)
        $user1 = User::factory()->create(['district' => 5]);
        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->forUser($user1)->sailing()->create(['date' => "2025-01-{$i}"]);
        }
        for ($i = 11; $i <= 13; $i++) {
            Flash::factory()->forUser($user1)->maintenance()->create(['date' => "2025-01-{$i}"]);
        }

        $user2 = User::factory()->create(['district' => 5]);
        for ($i = 1; $i <= 5; $i++) {
            Flash::factory()->forUser($user2)->sailing()->create(['date' => "2025-02-{$i}"]);
        }
        Flash::factory()->forUser($user2)->maintenance()->create(['date' => '2025-02-06']);
        Flash::factory()->forUser($user2)->maintenance()->create(['date' => '2025-02-07']);

        // District 10: 20 total (20 sailing + 0 non-sailing) - should rank higher
        $user3 = User::factory()->create(['district' => 10]);
        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->forUser($user3)->sailing()->create(['date' => "2025-01-{$i}"]);
        }

        $user4 = User::factory()->create(['district' => 10]);
        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->forUser($user4)->sailing()->create(['date' => "2025-02-{$i}"]);
        }

        $response = $this->get('/leaderboard?tab=district');

        $response->assertStatus(200);
        // District 10 should appear before District 5 (more sailing days)
        $response->assertSeeInOrder(['District 10', 'District 5']);
    }
}
