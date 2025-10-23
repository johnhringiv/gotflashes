<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Flash;
use App\Models\Fleet;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaderboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Note: Districts and fleets are seeded automatically by the migration
        // via RefreshDatabase trait
    }

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
            'yacht_club' => 'Test Yacht Club',
        ]);

        // Get specific district and fleet
        $district = District::where('name', 'California')->first();
        $fleet = Fleet::where('fleet_number', 194)->first(); // Fleet 194 is in California

        Member::create([
            'user_id' => $user->id,
            'district_id' => $district->id,
            'fleet_id' => $fleet->id,
            'year' => 2025,
        ]);

        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2025-01-15',
        ]);

        $response = $this->get('/leaderboard');

        $response->assertStatus(200);
        $response->assertSee('John Doe');
        $response->assertSee('California'); // district name
        $response->assertSee('194'); // fleet number
        $response->assertSee('Test Yacht Club');
    }

    public function test_leaderboard_handles_missing_user_information(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'yacht_club' => null,
        ]);

        // Create unaffiliated membership (no district/fleet)
        Member::create([
            'user_id' => $user->id,
            'district_id' => null,
            'fleet_id' => null,
            'year' => 2025,
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
        $response->assertSee('current-user-row'); // Highlighted row styling class
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
        // Get fleets from seeded data
        $fleet194 = Fleet::where('fleet_number', 194)->first(); // California
        $fleet1 = Fleet::where('fleet_number', 1)->first(); // Central New York

        // Create users in different fleets
        $user1 = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Smith']);
        $user2 = User::factory()->create(['first_name' => 'Bob', 'last_name' => 'Jones']);
        $user3 = User::factory()->create(['first_name' => 'Charlie', 'last_name' => 'Brown']);

        // Assign users to fleets for 2025
        Member::create([
            'user_id' => $user1->id,
            'district_id' => $fleet194->district_id,
            'fleet_id' => $fleet194->id,
            'year' => 2025,
        ]);

        Member::create([
            'user_id' => $user2->id,
            'district_id' => $fleet194->district_id,
            'fleet_id' => $fleet194->id,
            'year' => 2025,
        ]);

        Member::create([
            'user_id' => $user3->id,
            'district_id' => $fleet1->district_id,
            'fleet_id' => $fleet1->id,
            'year' => 2025,
        ]);

        // Fleet 194: Alice (5 flashes) + Bob (3 flashes) = 8 total
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

        // Fleet 1: Charlie (10 flashes)
        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->create([
                'user_id' => $user3->id,
                'date' => "2025-02-{$i}",
                'activity_type' => 'sailing',
            ]);
        }

        $response = $this->get('/leaderboard?tab=fleet');

        $response->assertStatus(200);
        $response->assertSee('Fleet 1');
        $response->assertSee('Fleet 194');
        $response->assertSeeInOrder(['Fleet 1', '10', 'Fleet 194', '8']);
    }

    public function test_fleet_tab_shows_member_count(): void
    {
        // Get a fleet from seeded data
        $fleet194 = Fleet::where('fleet_number', 194)->first(); // California

        // Create 3 users in fleet 194
        for ($i = 1; $i <= 3; $i++) {
            $user = User::factory()->create();

            Member::create([
                'user_id' => $user->id,
                'district_id' => $fleet194->district_id,
                'fleet_id' => $fleet194->id,
                'year' => 2025,
            ]);

            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => "2025-01-{$i}",
                'activity_type' => 'sailing',
            ]);
        }

        $response = $this->get('/leaderboard?tab=fleet');

        $response->assertStatus(200);
        $response->assertSee('Fleet 194');
        $response->assertSee('3'); // Member count
    }

    public function test_fleet_tab_excludes_users_without_fleet_number(): void
    {
        // Get a fleet from seeded data
        $fleet194 = Fleet::where('fleet_number', 194)->first();

        $user1 = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Smith']);
        $user2 = User::factory()->create(['first_name' => 'Bob', 'last_name' => 'Jones']);

        // User 1 is in a fleet
        Member::create([
            'user_id' => $user1->id,
            'district_id' => $fleet194->district_id,
            'fleet_id' => $fleet194->id,
            'year' => 2025,
        ]);

        // User 2 has no fleet
        Member::create([
            'user_id' => $user2->id,
            'district_id' => null,
            'fleet_id' => null,
            'year' => 2025,
        ]);

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
        $response->assertSee('Fleet 194');
        $response->assertDontSee('Bob Jones'); // User without fleet not shown
    }

    public function test_district_tab_shows_district_rankings(): void
    {
        // Get districts from seeded data
        $california = District::where('name', 'California')->first();
        $centralAtlantic = District::where('name', 'Central Atlantic')->first();

        // Get fleets from those districts
        $fleet194 = Fleet::where('fleet_number', 194)->first(); // California
        $fleet173 = Fleet::where('fleet_number', 173)->first(); // Central Atlantic

        // Create users in different districts
        $user1 = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Smith']);
        $user2 = User::factory()->create(['first_name' => 'Bob', 'last_name' => 'Jones']);
        $user3 = User::factory()->create(['first_name' => 'Charlie', 'last_name' => 'Brown']);

        // Assign users to districts for 2025
        Member::create([
            'user_id' => $user1->id,
            'district_id' => $california->id,
            'fleet_id' => $fleet194->id,
            'year' => 2025,
        ]);

        Member::create([
            'user_id' => $user2->id,
            'district_id' => $california->id,
            'fleet_id' => $fleet194->id,
            'year' => 2025,
        ]);

        Member::create([
            'user_id' => $user3->id,
            'district_id' => $centralAtlantic->id,
            'fleet_id' => $fleet173->id,
            'year' => 2025,
        ]);

        // California: Alice (5 flashes) + Bob (3 flashes) = 8 total
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

        // Central Atlantic: Charlie (10 flashes)
        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->create([
                'user_id' => $user3->id,
                'date' => "2025-02-{$i}",
                'activity_type' => 'sailing',
            ]);
        }

        $response = $this->get('/leaderboard?tab=district');

        $response->assertStatus(200);
        $response->assertSee('Central Atlantic');
        $response->assertSee('California');
        $response->assertSeeInOrder(['Central Atlantic', '10', 'California', '8']);
    }

    public function test_district_tab_shows_member_count(): void
    {
        // Get a district and fleet from seeded data
        $california = District::where('name', 'California')->first();
        $fleet194 = Fleet::where('fleet_number', 194)->first();

        // Create 3 users in California district
        for ($i = 1; $i <= 3; $i++) {
            $user = User::factory()->create();

            Member::create([
                'user_id' => $user->id,
                'district_id' => $california->id,
                'fleet_id' => $fleet194->id,
                'year' => 2025,
            ]);

            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => "2025-01-{$i}",
                'activity_type' => 'sailing',
            ]);
        }

        $response = $this->get('/leaderboard?tab=district');

        $response->assertStatus(200);
        $response->assertSee('California');
        $response->assertSee('3'); // Member count
    }

    public function test_district_tab_excludes_users_without_district(): void
    {
        // Get a district and fleet from seeded data
        $california = District::where('name', 'California')->first();
        $fleet194 = Fleet::where('fleet_number', 194)->first();

        $user1 = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Smith']);
        $user2 = User::factory()->create(['first_name' => 'Bob', 'last_name' => 'Jones']);

        // User 1 is in a district
        Member::create([
            'user_id' => $user1->id,
            'district_id' => $california->id,
            'fleet_id' => $fleet194->id,
            'year' => 2025,
        ]);

        // User 2 has no district
        Member::create([
            'user_id' => $user2->id,
            'district_id' => null,
            'fleet_id' => null,
            'year' => 2025,
        ]);

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
        $response->assertSee('California');
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
        // Get a fleet from seeded data
        $fleet194 = Fleet::where('fleet_number', 194)->first();

        // Create 2 users in fleet 194
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Assign both users to fleet 194
        Member::create([
            'user_id' => $user1->id,
            'district_id' => $fleet194->district_id,
            'fleet_id' => $fleet194->id,
            'year' => 2025,
        ]);

        Member::create([
            'user_id' => $user2->id,
            'district_id' => $fleet194->district_id,
            'fleet_id' => $fleet194->id,
            'year' => 2025,
        ]);

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
        $response->assertSee('Fleet 194');
        $response->assertSee('11');
    }

    public function test_district_tab_caps_non_sailing_days_at_5_per_member(): void
    {
        // Get a district and fleet from seeded data
        $california = District::where('name', 'California')->first();
        $fleet194 = Fleet::where('fleet_number', 194)->first();

        // Create 2 users in California district
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Assign both users to California
        Member::create([
            'user_id' => $user1->id,
            'district_id' => $california->id,
            'fleet_id' => $fleet194->id,
            'year' => 2025,
        ]);

        Member::create([
            'user_id' => $user2->id,
            'district_id' => $california->id,
            'fleet_id' => $fleet194->id,
            'year' => 2025,
        ]);

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
        $response->assertSee('California');
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
        $userB = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Baker']);

        // Create flashes with identical timestamps for both users to force alphabetical tie-breaking
        $timestamp = now()->subDays(5);
        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->forUser($userA)->sailing()->create([
                'date' => "2025-01-{$i}",
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
            Flash::factory()->forUser($userB)->sailing()->create([
                'date' => "2025-01-{$i}",
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        $response = $this->get('/leaderboard');

        $response->assertStatus(200);
        // Alice should appear before Zara (alphabetical)
        $response->assertSeeInOrder(['Alice Baker', 'Zara Adams']);
    }

    public function test_fleet_leaderboard_tie_breaking(): void
    {
        // Get fleets from seeded data
        $fleet194 = Fleet::where('fleet_number', 194)->first(); // California
        $fleet1 = Fleet::where('fleet_number', 1)->first(); // Central New York

        // Fleet 194: 20 total (15 sailing + 5 non-sailing)
        $user1 = User::factory()->create();
        Member::create([
            'user_id' => $user1->id,
            'district_id' => $fleet194->district_id,
            'fleet_id' => $fleet194->id,
            'year' => 2025,
        ]);

        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->forUser($user1)->sailing()->create(['date' => "2025-01-{$i}"]);
        }
        for ($i = 11; $i <= 13; $i++) {
            Flash::factory()->forUser($user1)->maintenance()->create(['date' => "2025-01-{$i}"]);
        }

        $user2 = User::factory()->create();
        Member::create([
            'user_id' => $user2->id,
            'district_id' => $fleet194->district_id,
            'fleet_id' => $fleet194->id,
            'year' => 2025,
        ]);

        for ($i = 1; $i <= 5; $i++) {
            Flash::factory()->forUser($user2)->sailing()->create(['date' => "2025-02-{$i}"]);
        }
        Flash::factory()->forUser($user2)->maintenance()->create(['date' => '2025-02-06']);
        Flash::factory()->forUser($user2)->maintenance()->create(['date' => '2025-02-07']);

        // Fleet 1: 20 total (20 sailing + 0 non-sailing) - should rank higher
        $user3 = User::factory()->create();
        Member::create([
            'user_id' => $user3->id,
            'district_id' => $fleet1->district_id,
            'fleet_id' => $fleet1->id,
            'year' => 2025,
        ]);

        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->forUser($user3)->sailing()->create(['date' => "2025-01-{$i}"]);
        }

        $user4 = User::factory()->create();
        Member::create([
            'user_id' => $user4->id,
            'district_id' => $fleet1->district_id,
            'fleet_id' => $fleet1->id,
            'year' => 2025,
        ]);

        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->forUser($user4)->sailing()->create(['date' => "2025-02-{$i}"]);
        }

        $response = $this->get('/leaderboard?tab=fleet');

        $response->assertStatus(200);
        // Fleet 1 should appear before Fleet 194 (more sailing days)
        $response->assertSeeInOrder(['Fleet 1', 'Fleet 194']);
    }

    public function test_district_leaderboard_tie_breaking(): void
    {
        // Get districts from seeded data
        $california = District::where('name', 'California')->first();
        $centralNewYork = District::where('name', 'Central New York')->first();

        // Get fleets
        $fleet194 = Fleet::where('fleet_number', 194)->first(); // California
        $fleet1 = Fleet::where('fleet_number', 1)->first(); // Central New York

        // California: 20 total (15 sailing + 5 non-sailing)
        $user1 = User::factory()->create();
        Member::create([
            'user_id' => $user1->id,
            'district_id' => $california->id,
            'fleet_id' => $fleet194->id,
            'year' => 2025,
        ]);

        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->forUser($user1)->sailing()->create(['date' => "2025-01-{$i}"]);
        }
        for ($i = 11; $i <= 13; $i++) {
            Flash::factory()->forUser($user1)->maintenance()->create(['date' => "2025-01-{$i}"]);
        }

        $user2 = User::factory()->create();
        Member::create([
            'user_id' => $user2->id,
            'district_id' => $california->id,
            'fleet_id' => $fleet194->id,
            'year' => 2025,
        ]);

        for ($i = 1; $i <= 5; $i++) {
            Flash::factory()->forUser($user2)->sailing()->create(['date' => "2025-02-{$i}"]);
        }
        Flash::factory()->forUser($user2)->maintenance()->create(['date' => '2025-02-06']);
        Flash::factory()->forUser($user2)->maintenance()->create(['date' => '2025-02-07']);

        // Central New York: 20 total (20 sailing + 0 non-sailing) - should rank higher
        $user3 = User::factory()->create();
        Member::create([
            'user_id' => $user3->id,
            'district_id' => $centralNewYork->id,
            'fleet_id' => $fleet1->id,
            'year' => 2025,
        ]);

        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->forUser($user3)->sailing()->create(['date' => "2025-01-{$i}"]);
        }

        $user4 = User::factory()->create();
        Member::create([
            'user_id' => $user4->id,
            'district_id' => $centralNewYork->id,
            'fleet_id' => $fleet1->id,
            'year' => 2025,
        ]);

        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->forUser($user4)->sailing()->create(['date' => "2025-02-{$i}"]);
        }

        $response = $this->get('/leaderboard?tab=district');

        $response->assertStatus(200);
        // Central New York should appear before California (more sailing days)
        $response->assertSeeInOrder(['Central New York', 'California']);
    }

    public function test_pagination_preserves_tab_parameter(): void
    {
        // Create enough users to trigger pagination (more than 15)
        $users = [];
        for ($i = 1; $i <= 20; $i++) {
            $user = User::factory()->create();
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => '2025-01-15',
            ]);
            $users[] = $user;
        }

        // Test sailor tab pagination
        $response = $this->get('/leaderboard?tab=sailor&page=2');
        $response->assertStatus(200);
        $response->assertSee('tab=sailor');

        // Test fleet tab pagination
        $district = District::create(['name' => 'Test District']);
        $fleet = Fleet::create([
            'district_id' => $district->id,
            'fleet_number' => 999,
            'fleet_name' => 'Test Fleet',
        ]);
        foreach ($users as $user) {
            Member::create([
                'user_id' => $user->id,
                'district_id' => $district->id,
                'fleet_id' => $fleet->id,
                'year' => 2025,
            ]);
        }

        $response = $this->get('/leaderboard?tab=fleet');
        $response->assertStatus(200);
        // Check that pagination links include tab parameter
        $response->assertSee('tab=fleet');

        // Test district tab pagination
        $response = $this->get('/leaderboard?tab=district');
        $response->assertStatus(200);
        $response->assertSee('tab=district');
    }
}
