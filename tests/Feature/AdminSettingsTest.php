<?php

namespace Tests\Feature;

use App\Models\Flash;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-06-15');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin/settings')->assertRedirect('/login');
    }

    public function test_non_admins_get_403(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin/settings')->assertStatus(403);
    }

    public function test_admins_can_view_settings_page(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get('/admin/settings');

        $response->assertStatus(200);
        $response->assertSee('Community goal for 2026');
    }

    public function test_admin_can_save_goal(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test('admin-settings')
            ->set('goal', 2000)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertSame('2000', Setting::get('community_goal_2026'));
    }

    public function test_saving_goal_clears_stats_cache_for_that_year(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        // Matches CommunityStats::cacheKey() (versioned)
        Cache::put('community-stats-v13-2026', ['stale' => true], 900);

        Livewire::actingAs($admin)
            ->test('admin-settings')
            ->set('goal', 500)
            ->call('save');

        $this->assertNull(Cache::get('community-stats-v13-2026'));
    }

    public function test_goal_validation_rejects_non_positive_values(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test('admin-settings')
            ->set('goal', 0)
            ->call('save')
            ->assertHasErrors(['goal']);
    }

    public function test_goal_can_be_cleared(): void
    {
        Setting::set('community_goal_2026', '1000');
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test('admin-settings')
            ->set('goal', null)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull(Setting::get('community_goal_2026'));
    }

    public function test_goal_loads_for_selected_year(): void
    {
        Setting::set('community_goal_2026', '1500');
        Setting::set('community_goal_2025', '800');
        $admin = User::factory()->create(['is_admin' => true]);
        Flash::factory()->forUser($admin)->sailing()->onDate('2025-05-01')->create();

        Livewire::actingAs($admin)
            ->test('admin-settings')
            ->assertSet('goal', 1500)
            ->set('selectedYear', 2025)
            ->assertSet('goal', 800);
    }

    public function test_shows_progress_toward_goal(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Flash::factory()->forUser($admin)->sailing()->onDate('2026-05-01')->create();
        Setting::set('community_goal_2026', '100');

        Livewire::actingAs($admin)
            ->test('admin-settings')
            ->assertSee('1%');
    }

    public function test_shows_prior_year_historical_reference(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $expected = (int) config('community.historical_totals')[2025];

        Livewire::actingAs($admin)
            ->test('admin-settings')
            ->assertSee("2025 finished at {$expected} days")
            ->assertSee('pre-launch process');
    }

    public function test_available_years_excludes_pre_launch_years(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // A flash dated before START_YEAR (2026) must not become a selectable year:
        // the public stats page floors at the launch year, and getQualifyingTotal()
        // would silently report 0 for such a year.
        Flash::factory()->forUser($admin)->sailing()->onDate('2025-07-01')->create();
        Flash::factory()->forUser($admin)->sailing()->onDate('2026-07-01')->create();

        $years = Livewire::actingAs($admin)
            ->test('admin-settings')
            ->viewData('availableYears');

        $this->assertContains(2026, $years);
        $this->assertNotContains(2025, $years);
    }
}
