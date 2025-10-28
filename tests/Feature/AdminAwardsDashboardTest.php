<?php

namespace Tests\Feature;

use App\Models\AwardFulfillment;
use App\Models\Flash;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminAwardsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_users_cannot_access_dashboard(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->get('/admin/awards');

        $response->assertStatus(403);
    }

    public function test_admin_users_can_access_dashboard(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get('/admin/awards');

        $response->assertStatus(200);
    }

    public function test_dashboard_shows_earned_awards(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // Create a user with 10 sailing days
        $user = User::factory()->create();
        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }

        Livewire::actingAs($admin)
            ->test('admin-awards-dashboard')
            ->assertSee($user->name)
            ->assertSee('10'); // Award tier
    }

    public function test_dashboard_shows_processing_awards(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $user = User::factory()->create();
        for ($i = 1; $i <= 25; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }

        // Create fulfillment record with processing status
        AwardFulfillment::create([
            'user_id' => $user->id,
            'year' => now()->year,
            'award_tier' => 25,
            'status' => 'processing',
        ]);

        Livewire::actingAs($admin)
            ->test('admin-awards-dashboard')
            ->assertSee($user->name)
            ->assertSee('Processing');
    }

    public function test_dashboard_shows_sent_awards(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $user = User::factory()->create();
        for ($i = 1; $i <= 50; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }

        // Create fulfillment record with sent status
        AwardFulfillment::create([
            'user_id' => $user->id,
            'year' => now()->year,
            'award_tier' => 50,
            'status' => 'sent',
        ]);

        Livewire::actingAs($admin)
            ->test('admin-awards-dashboard')
            ->assertSee($user->name)
            ->assertSee('Sent');
    }

    public function test_bulk_mark_as_processing_creates_fulfillment_records(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $user = User::factory()->create();
        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }

        Livewire::actingAs($admin)
            ->test('admin-awards-dashboard')
            ->set('selectedAwards', ["{$user->id}-10"])
            ->call('confirmMarkAsProcessing')
            ->assertSet('confirmingAction', 'processing')
            ->call('bulkMarkAsProcessing')
            ->assertSet('confirmingAction', null);

        // Verify database record was created
        $this->assertDatabaseHas('award_fulfillments', [
            'user_id' => $user->id,
            'year' => now()->year,
            'award_tier' => 10,
            'status' => 'processing',
        ]);
    }

    public function test_bulk_mark_as_sent_updates_fulfillment_records(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $user = User::factory()->create();
        for ($i = 1; $i <= 25; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }

        // Create processing fulfillment
        AwardFulfillment::create([
            'user_id' => $user->id,
            'year' => now()->year,
            'award_tier' => 25,
            'status' => 'processing',
        ]);

        Livewire::actingAs($admin)
            ->test('admin-awards-dashboard')
            ->set('selectedAwards', ["{$user->id}-25"])
            ->call('confirmMarkAsSent')
            ->assertSet('confirmingAction', 'sent')
            ->assertSet('showEarnedToSentWarning', false) // No warning for processing awards
            ->call('bulkMarkAsSent')
            ->assertSet('confirmingAction', null);

        // Verify status was updated
        $this->assertDatabaseHas('award_fulfillments', [
            'user_id' => $user->id,
            'year' => now()->year,
            'award_tier' => 25,
            'status' => 'sent',
        ]);
    }

    public function test_discrepancy_shown_when_user_drops_below_threshold(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $user = User::factory()->create();

        // User originally had 25 days
        for ($i = 1; $i <= 25; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }

        // Award was processed for 25-day tier
        AwardFulfillment::create([
            'user_id' => $user->id,
            'year' => now()->year,
            'award_tier' => 25,
            'status' => 'processing',
        ]);

        // User deletes some activities and now has only 20 days
        Flash::where('user_id', $user->id)
            ->where('date', '>=', now()->startOfYear()->addDays(21))
            ->delete();

        // Dashboard should show the discrepancy
        Livewire::actingAs($admin)
            ->test('admin-awards-dashboard')
            ->assertSee($user->name)
            ->assertSee('⚠'); // Warning icon for discrepancy
    }

    public function test_year_filter_changes_displayed_awards(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $user1 = User::factory()->create(['first_name' => 'Current', 'last_name' => 'Year']);
        $user2 = User::factory()->create(['first_name' => 'Previous', 'last_name' => 'Year']);

        // User 1: Create flashes for current year (10 days)
        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->create([
                'user_id' => $user1->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }

        // User 2: Create flashes for previous year (25 days)
        for ($i = 1; $i <= 25; $i++) {
            Flash::factory()->create([
                'user_id' => $user2->id,
                'date' => now()->subYear()->startOfYear()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }

        // Test current year shows user1 but not user2
        Livewire::actingAs($admin)
            ->test('admin-awards-dashboard')
            ->set('selectedYear', now()->year)
            ->assertSee('Current Year')
            ->assertDontSee('Previous Year');

        // Test previous year shows user2 but not user1
        Livewire::actingAs($admin)
            ->test('admin-awards-dashboard')
            ->set('selectedYear', now()->subYear()->year)
            ->assertSee('Previous Year')
            ->assertDontSee('Current Year');
    }

    public function test_status_filter_works(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $user1 = User::factory()->create(['first_name' => 'Earned', 'last_name' => 'User']);
        $user2 = User::factory()->create(['first_name' => 'Processing', 'last_name' => 'User']);

        // User 1: Earned (not in database) - 10 days
        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->create([
                'user_id' => $user1->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }

        // User 2: Processing - 25 days
        for ($i = 1; $i <= 25; $i++) {
            Flash::factory()->create([
                'user_id' => $user2->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }
        // Mark all tiers as processing for user2 (10, 25)
        foreach ([10, 25] as $tier) {
            AwardFulfillment::create([
                'user_id' => $user2->id,
                'year' => now()->year,
                'award_tier' => $tier,
                'status' => 'processing',
            ]);
        }

        // Filter by earned - should show user1 only
        Livewire::actingAs($admin)
            ->test('admin-awards-dashboard')
            ->set('statusFilter', 'earned')
            ->assertSee('Earned User')
            ->assertDontSee('Processing User');

        // Filter by processing - should show user2 only
        Livewire::actingAs($admin)
            ->test('admin-awards-dashboard')
            ->set('statusFilter', 'processing')
            ->assertSee('Processing User')
            ->assertDontSee('Earned User');
    }

    public function test_search_filter_works(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $user1 = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
        $user2 = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);

        // Both users earn 10-day awards
        foreach ([$user1, $user2] as $user) {
            for ($i = 1; $i <= 10; $i++) {
                Flash::factory()->create([
                    'user_id' => $user->id,
                    'date' => now()->startOfYear()->addDays($i),
                    'activity_type' => 'sailing',
                ]);
            }
        }

        // Search for John
        Livewire::actingAs($admin)
            ->test('admin-awards-dashboard')
            ->set('searchQuery', 'John')
            ->assertSee('John Doe')
            ->assertDontSee('Jane Smith');
    }

    public function test_admin_navigation_link_visible_to_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get('/');

        $response->assertSee('Awards Admin');
    }

    public function test_admin_navigation_link_not_visible_to_non_admin(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->get('/');

        $response->assertDontSee('Awards Admin');
    }

    public function test_earned_to_sent_shows_warning(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $user = User::factory()->create();
        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }

        Livewire::actingAs($admin)
            ->test('admin-awards-dashboard')
            ->set('selectedAwards', ["{$user->id}-10"])
            ->call('confirmMarkAsSent')
            ->assertSet('showEarnedToSentWarning', true); // Warning shown for earned awards
    }

    public function test_earned_awards_can_be_marked_sent_directly(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $user = User::factory()->create();
        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }

        Livewire::actingAs($admin)
            ->test('admin-awards-dashboard')
            ->set('selectedAwards', ["{$user->id}-10"])
            ->call('confirmMarkAsSent')
            ->assertSet('showEarnedToSentWarning', true)
            ->call('bulkMarkAsSent');

        // Verify database record was created with sent status
        $this->assertDatabaseHas('award_fulfillments', [
            'user_id' => $user->id,
            'year' => now()->year,
            'award_tier' => 10,
            'status' => 'sent',
        ]);
    }

    public function test_pending_filter_shows_earned_and_processing(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $user1 = User::factory()->create(['first_name' => 'Earned', 'last_name' => 'User']);
        $user2 = User::factory()->create(['first_name' => 'Processing', 'last_name' => 'User']);
        $user3 = User::factory()->create(['first_name' => 'Sent', 'last_name' => 'User']);

        // User 1: Earned (10 days)
        for ($i = 1; $i <= 10; $i++) {
            Flash::factory()->create([
                'user_id' => $user1->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }

        // User 2: Processing (25 days)
        for ($i = 1; $i <= 25; $i++) {
            Flash::factory()->create([
                'user_id' => $user2->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }
        AwardFulfillment::create([
            'user_id' => $user2->id,
            'year' => now()->year,
            'award_tier' => 25,
            'status' => 'processing',
        ]);

        // User 3: Sent (50 days - mark all tiers as sent)
        for ($i = 1; $i <= 50; $i++) {
            Flash::factory()->create([
                'user_id' => $user3->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'sailing',
            ]);
        }
        // Mark all tiers as sent
        foreach ([10, 25, 50] as $tier) {
            AwardFulfillment::create([
                'user_id' => $user3->id,
                'year' => now()->year,
                'award_tier' => $tier,
                'status' => 'sent',
            ]);
        }

        // Pending filter (default) should show earned and processing, not sent
        Livewire::actingAs($admin)
            ->test('admin-awards-dashboard')
            ->assertSet('statusFilter', 'pending') // Verify default
            ->assertSee('Earned User')
            ->assertSee('Processing User')
            ->assertDontSee('Sent User');
    }

    public function test_pending_filter_is_default(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test('admin-awards-dashboard')
            ->assertSet('statusFilter', 'pending');
    }
}
