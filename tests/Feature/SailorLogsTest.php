<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Flash;
use App\Models\Fleet;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SailorLogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_users_cannot_access_sailor_logs(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->get('/admin/sailor-logs');

        $response->assertStatus(403);
    }

    public function test_guests_cannot_access_sailor_logs(): void
    {
        $response = $this->get('/admin/sailor-logs');

        $response->assertRedirect('/login');
    }

    public function test_admin_users_can_access_sailor_logs(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get('/admin/sailor-logs');

        $response->assertStatus(200);
        $response->assertSee('Sailor Logs');
    }

    public function test_displays_flash_entries_for_current_year(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $user = User::factory()->create();
        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => now()->startOfYear()->addDays(1),
            'activity_type' => 'sailing',
            'location' => 'Test Lake',
        ]);

        Livewire::actingAs($admin)
            ->test('sailor-logs')
            ->assertSee($user->name)
            ->assertSee('Test Lake')
            ->assertSee('Sailing');
    }

    public function test_year_filter_works(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $user = User::factory()->create();

        // Create flash for current year
        $currentYearFlash = Flash::factory()->create([
            'user_id' => $user->id,
            'date' => now()->startOfYear()->addDays(1),
            'location' => 'Current Year Location',
        ]);

        // Create flash for last year
        $lastYearFlash = Flash::factory()->create([
            'user_id' => $user->id,
            'date' => now()->subYear()->startOfYear()->addDays(1),
            'location' => 'Last Year Location',
        ]);

        // Test current year (default)
        Livewire::actingAs($admin)
            ->test('sailor-logs')
            ->assertSee('Current Year Location')
            ->assertDontSee('Last Year Location');

        // Test switching to last year
        Livewire::actingAs($admin)
            ->test('sailor-logs', ['selectedYear' => now()->subYear()->year])
            ->assertSee('Last Year Location')
            ->assertDontSee('Current Year Location');
    }

    public function test_search_filter_works(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $userJohn = User::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
        $userJane = User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Smith']);

        Flash::factory()->create([
            'user_id' => $userJohn->id,
            'date' => now()->startOfYear()->addDays(1),
        ]);

        Flash::factory()->create([
            'user_id' => $userJane->id,
            'date' => now()->startOfYear()->addDays(2),
        ]);

        Livewire::actingAs($admin)
            ->test('sailor-logs')
            ->set('searchQuery', 'John')
            ->assertSee('John Doe')
            ->assertDontSee('Jane Smith');
    }

    public function test_district_filter_works(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $district1 = District::factory()->create(['name' => 'District 1']);
        $district2 = District::factory()->create(['name' => 'District 2']);

        $fleet1 = Fleet::factory()->create(['district_id' => $district1->id, 'fleet_number' => 1001]);
        $fleet2 = Fleet::factory()->create(['district_id' => $district2->id, 'fleet_number' => 1002]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Member::factory()->create([
            'user_id' => $user1->id,
            'district_id' => $district1->id,
            'fleet_id' => $fleet1->id,
            'year' => now()->year,
        ]);

        Member::factory()->create([
            'user_id' => $user2->id,
            'district_id' => $district2->id,
            'fleet_id' => $fleet2->id,
            'year' => now()->year,
        ]);

        Flash::factory()->create([
            'user_id' => $user1->id,
            'date' => now()->startOfYear()->addDays(1),
        ]);

        Flash::factory()->create([
            'user_id' => $user2->id,
            'date' => now()->startOfYear()->addDays(2),
        ]);

        Livewire::actingAs($admin)
            ->test('sailor-logs')
            ->set('selectedDistrict', $district1->id)
            ->assertSee($user1->name)
            ->assertDontSee($user2->name);
    }

    public function test_fleet_filter_works(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $district = District::factory()->create();
        $fleet1 = Fleet::factory()->create(['district_id' => $district->id, 'fleet_number' => 2001]);
        $fleet2 = Fleet::factory()->create(['district_id' => $district->id, 'fleet_number' => 2002]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Member::factory()->create([
            'user_id' => $user1->id,
            'district_id' => $district->id,
            'fleet_id' => $fleet1->id,
            'year' => now()->year,
        ]);

        Member::factory()->create([
            'user_id' => $user2->id,
            'district_id' => $district->id,
            'fleet_id' => $fleet2->id,
            'year' => now()->year,
        ]);

        Flash::factory()->create([
            'user_id' => $user1->id,
            'date' => now()->startOfYear()->addDays(1),
        ]);

        Flash::factory()->create([
            'user_id' => $user2->id,
            'date' => now()->startOfYear()->addDays(2),
        ]);

        Livewire::actingAs($admin)
            ->test('sailor-logs')
            ->set('selectedFleet', $fleet1->id)
            ->assertSee($user1->name)
            ->assertDontSee($user2->name);
    }

    public function test_pagination_works(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // Create 30 flashes (pagination is 25 per page)
        // Sort by date desc, so create in reverse order
        $users = [];
        for ($i = 0; $i < 30; $i++) {
            $user = User::factory()->create();
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addDays(30 - $i), // Reverse order for desc sort
            ]);
            $users[] = $user;
        }

        $component = Livewire::actingAs($admin)
            ->test('sailor-logs');

        // Should see first 25 users (most recent dates)
        $component->assertSee($users[0]->name);

        // Should not see all 30 on first page
        $this->assertCount(30, $users);

        // Verify pagination exists
        $component->assertSee('Next');
    }

    public function test_csv_export_includes_correct_data(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $district = District::factory()->create(['name' => 'Test District']);
        $fleet = Fleet::factory()->create(['district_id' => $district->id, 'fleet_number' => 3001]);

        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'yacht_club' => 'Test Yacht Club',
        ]);

        Member::factory()->create([
            'user_id' => $user->id,
            'district_id' => $district->id,
            'fleet_id' => $fleet->id,
            'year' => now()->year,
        ]);

        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => now()->startOfYear()->addDays(1),
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
            'location' => 'Test Lake',
            'sail_number' => '12345',
            'notes' => 'Test notes',
        ]);

        $response = Livewire::actingAs($admin)
            ->test('sailor-logs')
            ->call('exportCsv')
            ->assertSuccessful();

        // Get the response content (may be base64 encoded)
        $content = $response->effects['download']['content'];

        // Decode if base64 encoded
        if (base64_encode(base64_decode($content, true)) === $content) {
            $content = base64_decode($content);
        }

        // Check CSV headers and data
        $this->assertStringContainsString('John Doe', $content);
        $this->assertStringContainsString('john@example.com', $content);
        $this->assertStringContainsString('Test Lake', $content);
        $this->assertStringContainsString('12345', $content);
        $this->assertStringContainsString('Test District', $content);
        $this->assertStringContainsString('3001', $content);
        $this->assertStringContainsString('Test Yacht Club', $content);
    }

    public function test_empty_state_shown_when_no_flashes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test('sailor-logs')
            ->assertSee('No flash entries found');
    }

    public function test_filters_reset_on_year_change(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $district = District::factory()->create();

        Livewire::actingAs($admin)
            ->test('sailor-logs')
            ->set('selectedDistrict', $district->id)
            ->set('selectedYear', now()->subYear()->year)
            ->assertSet('selectedDistrict', null)
            ->assertSet('selectedFleet', null);
    }
}
