<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Flash;
use App\Models\Fleet;
use App\Models\Member;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommunityStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-06-15');
    }

    public function test_stats_page_loads_for_guests(): void
    {
        $response = $this->get('/stats');

        $response->assertStatus(200);
        $response->assertViewIs('stats.index');
        $response->assertSee('Community Stats');
    }

    public function test_stats_page_shows_key_counters(): void
    {
        $user = User::factory()->create();
        Flash::factory()->forUser($user)->sailing()->onDate('2026-05-01')->create();
        Flash::factory()->forUser($user)->sailing()->onDate('2026-05-02')->create();

        $component = Livewire::test('community-stats');
        $counters = $component->viewData('stats')['counters'];

        $this->assertSame(2, $counters['totalQualifying']);
        $this->assertSame(1, $counters['activeSailors']);
        $this->assertSame(0, $counters['awardAchievers']);
    }

    public function test_non_sailing_days_are_capped_at_five_in_qualifying_total(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 3) as $day) {
            Flash::factory()->forUser($user)->sailing()->onDate("2026-05-0{$day}")->create();
        }
        foreach (range(10, 17) as $day) {
            Flash::factory()->forUser($user)->maintenance()->onDate("2026-05-{$day}")->create();
        }

        $counters = Livewire::test('community-stats')->viewData('stats')['counters'];

        // 3 sailing + 8 non-sailing capped at 5 = 8
        $this->assertSame(8, $counters['totalQualifying']);
    }

    public function test_award_achievers_counts_sailors_with_ten_plus_qualifying_days(): void
    {
        $achiever = User::factory()->create();
        foreach (range(1, 12) as $day) {
            $date = sprintf('2026-05-%02d', $day);
            Flash::factory()->forUser($achiever)->sailing()->onDate($date)->create();
        }

        $casual = User::factory()->create();
        Flash::factory()->forUser($casual)->sailing()->onDate('2026-05-01')->create();

        $counters = Livewire::test('community-stats')->viewData('stats')['counters'];

        $this->assertSame(1, $counters['awardAchievers']);
        $this->assertSame(2, $counters['activeSailors']);
    }

    public function test_active_fleets_and_districts_counted_via_membership(): void
    {
        $fleet = Fleet::query()->firstOrFail();

        $user = User::factory()->create();
        Member::create([
            'user_id' => $user->id,
            'district_id' => $fleet->district_id,
            'fleet_id' => $fleet->id,
            'year' => 2026,
        ]);
        Flash::factory()->forUser($user)->sailing()->onDate('2026-05-01')->create();

        $counters = Livewire::test('community-stats')->viewData('stats')['counters'];

        $this->assertSame(1, $counters['activeFleets']);
        $this->assertSame(1, $counters['activeDistricts']);
    }

    public function test_sentinel_none_fleet_and_district_are_not_counted_as_active(): void
    {
        $noneFleetId = Fleet::noneId();
        $noneDistrictId = District::noneId();

        $user = User::factory()->create();
        Member::create([
            'user_id' => $user->id,
            'district_id' => $noneDistrictId,
            'fleet_id' => $noneFleetId,
            'year' => 2026,
        ]);
        Flash::factory()->forUser($user)->sailing()->onDate('2026-05-01')->create();

        $counters = Livewire::test('community-stats')->viewData('stats')['counters'];

        $this->assertSame(0, $counters['activeFleets']);
        $this->assertSame(0, $counters['activeDistricts']);
        $this->assertSame(1, $counters['activeSailors']);
    }

    public function test_monthly_chart_data_includes_current_and_previous_year(): void
    {
        $user = User::factory()->create();
        Flash::factory()->forUser($user)->sailing()->onDate('2026-05-01')->create();
        Flash::factory()->forUser($user)->sailing()->onDate('2026-05-02')->create();
        Flash::factory()->forUser($user)->sailing()->onDate('2025-03-10')->create();

        $stats = Livewire::test('community-stats')->viewData('stats');

        $this->assertSame(2, $stats['monthly']['current'][4]); // May 2026
        $this->assertSame(1, $stats['monthly']['previous'][2]); // March 2025
    }

    public function test_event_mix_counts_sailing_days_by_type_and_month(): void
    {
        $user = User::factory()->create();
        Flash::factory()->forUser($user)->sailing()->onDate('2026-05-01')->create(['event_type' => 'regatta']);
        Flash::factory()->forUser($user)->sailing()->onDate('2026-05-02')->create(['event_type' => 'leisure']);
        Flash::factory()->forUser($user)->maintenance()->onDate('2026-05-03')->create();

        $stats = Livewire::test('community-stats')->viewData('stats');

        $this->assertSame(1, $stats['eventMix'][5]['regatta']);
        $this->assertSame(1, $stats['eventMix'][5]['leisure']);
        $this->assertSame(0, $stats['eventMix'][5]['practice']);
    }

    public function test_heatmap_contains_flash_counts_per_day(): void
    {
        $userOne = User::factory()->create();
        $userTwo = User::factory()->create();
        Flash::factory()->forUser($userOne)->sailing()->onDate('2026-05-01')->create();
        Flash::factory()->forUser($userTwo)->sailing()->onDate('2026-05-01')->create();

        $stats = Livewire::test('community-stats')->viewData('stats');

        $this->assertSame(2, $stats['heatmap']['2026-05-01']);
    }

    public function test_age_distribution_excludes_implausible_ages(): void
    {
        $sailor = User::factory()->create(['date_of_birth' => '1990-06-01']); // 36 in 2026 → Open
        $typo = User::factory()->create(['date_of_birth' => '2026-01-01']); // age 0 — excluded
        Flash::factory()->forUser($sailor)->sailing()->onDate('2026-05-01')->create();
        Flash::factory()->forUser($typo)->sailing()->onDate('2026-05-02')->create();

        $ages = Livewire::test('community-stats')->viewData('stats')['ages'];

        $this->assertSame(1, array_sum($ages['counts']));
        $this->assertSame(1, $ages['counts'][array_search('Open', $ages['labels'])]);
    }

    public function test_age_distribution_buckets_by_class_division(): void
    {
        // One sailor per division: Youth (≤20), U32 (21–32), Open (33–54), Masters (55+)
        $ages = [
            'Youth' => 2011,   // 15
            'U32' => 2000,     // 26
            'Open' => 1985,    // 41
            'Masters' => 1960, // 66
        ];
        foreach ($ages as $birthYear) {
            $u = User::factory()->create(['date_of_birth' => "{$birthYear}-06-01"]);
            Flash::factory()->forUser($u)->sailing()->onDate('2026-05-01')->create();
        }

        $dist = Livewire::test('community-stats')->viewData('stats')['ages'];

        $this->assertSame(['Youth', 'U32', 'Open', 'Masters'], $dist['labels']);
        foreach (['Youth', 'U32', 'Open', 'Masters'] as $division) {
            $this->assertSame(1, $dist['counts'][array_search($division, $dist['labels'])], "{$division} bucket");
        }
    }

    public function test_award_funnel_buckets_sailors_by_qualifying_days(): void
    {
        $starter = User::factory()->create();
        Flash::factory()->forUser($starter)->sailing()->onDate('2026-05-01')->create();

        $achiever = User::factory()->create();
        foreach (range(1, 11) as $day) {
            $date = sprintf('2026-04-%02d', $day);
            Flash::factory()->forUser($achiever)->sailing()->onDate($date)->create();
        }

        $funnel = Livewire::test('community-stats')->viewData('stats')['funnel'];

        $this->assertSame(1, $funnel[0]['count']); // 1-9 days
        $this->assertSame(1, $funnel[1]['count']); // 10-24 days
        $this->assertSame(0, $funnel[2]['count']);
        $this->assertSame(0, $funnel[3]['count']);
    }

    public function test_goal_display_with_goal_set(): void
    {
        $user = User::factory()->create();
        Flash::factory()->forUser($user)->sailing()->onDate('2026-05-01')->create();
        Setting::set('community_goal_2026', '100');

        Livewire::test('community-stats')
            ->assertSee('100')
            ->assertSee('1% of the 100-day 2026 goal');
    }

    public function test_prior_year_benchmark_shown_when_goal_and_prior_data_exist(): void
    {
        $user = User::factory()->create();
        Flash::factory()->forUser($user)->sailing()->onDate('2026-05-01')->create();
        Flash::factory()->forUser($user)->sailing()->onDate('2025-05-01')->create();
        Flash::factory()->forUser($user)->sailing()->onDate('2025-05-02')->create();
        Setting::set('community_goal_2026', '100');

        Livewire::test('community-stats')
            ->assertSet('selectedYear', 2026)
            ->assertSee('2025 finished at 2 days');
    }

    public function test_goal_achieved_state(): void
    {
        $user = User::factory()->create();
        Flash::factory()->forUser($user)->sailing()->onDate('2026-05-01')->create();
        Flash::factory()->forUser($user)->sailing()->onDate('2026-05-02')->create();
        Setting::set('community_goal_2026', '2');

        Livewire::test('community-stats')
            ->assertSee('Goal achieved!');
    }

    public function test_admins_see_set_goal_hint_when_no_goal_is_set(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Flash::factory()->forUser($admin)->sailing()->onDate('2026-05-01')->create();

        Livewire::actingAs($admin)
            ->test('community-stats')
            ->assertSee('No community goal set for 2026');
    }

    public function test_guests_do_not_see_set_goal_hint(): void
    {
        $user = User::factory()->create();
        Flash::factory()->forUser($user)->sailing()->onDate('2026-05-01')->create();

        Livewire::test('community-stats')
            ->assertDontSee('No community goal set for 2026');
    }

    public function test_year_change_dispatches_chart_update_event(): void
    {
        $user = User::factory()->create();
        Flash::factory()->forUser($user)->sailing()->onDate('2026-05-01')->create();
        Flash::factory()->forUser($user)->sailing()->onDate('2025-05-01')->create();

        Livewire::test('community-stats')
            ->set('selectedYear', 2025)
            ->assertSet('selectedYear', 2025)
            ->assertDispatched('community-stats-updated');
    }

    public function test_invalid_year_falls_back_to_current_year(): void
    {
        Livewire::withQueryParams(['selectedYear' => 1999])
            ->test('community-stats')
            ->assertSet('selectedYear', 2026);
    }

    public function test_empty_year_shows_empty_state(): void
    {
        Livewire::test('community-stats')
            ->assertSee('No activity logged for 2026 yet');
    }

    public function test_fun_facts_include_busiest_day_with_two_or_more_sailors(): void
    {
        $userOne = User::factory()->create();
        $userTwo = User::factory()->create();
        Flash::factory()->forUser($userOne)->sailing()->onDate('2026-05-16')->create();
        Flash::factory()->forUser($userTwo)->sailing()->onDate('2026-05-16')->create();

        Livewire::test('community-stats')
            ->assertSee('Busiest day on the water')
            ->assertSee('May 16');
    }

    public function test_fun_facts_omit_free_text_location(): void
    {
        $user = User::factory()->create();
        Flash::factory()->forUser($user)->sailing()->onDate('2026-05-01')->create(['location' => 'Pymatuning']);
        Flash::factory()->forUser($user)->sailing()->onDate('2026-05-02')->create(['location' => 'Pymatuning']);
        Flash::factory()->forUser($user)->sailing()->onDate('2026-05-03')->create(['location' => 'Pymatuning']);

        Livewire::test('community-stats')
            ->assertDontSee('Most popular location');
    }

    public function test_fun_facts_include_most_flashes_logged_at_once(): void
    {
        $user = User::factory()->create();
        $bulk = now()->setDate(2026, 3, 21)->setTime(18, 26, 23);

        // Four flashes created in a single entry (shared created_at)
        foreach (['2026-05-01', '2026-05-02', '2026-05-03', '2026-05-04'] as $date) {
            Flash::factory()->forUser($user)->sailing()->onDate($date)->create([
                'created_at' => $bulk,
                'updated_at' => $bulk,
            ]);
        }

        Livewire::test('community-stats')
            ->assertSee('Most flashes logged at once')
            ->assertSee('4 flashes');
    }

    public function test_chart_json_payload_is_embedded(): void
    {
        $user = User::factory()->create();
        Flash::factory()->forUser($user)->sailing()->onDate('2026-05-01')->create();

        $this->get('/stats')
            ->assertSee('community-stats-data', false)
            ->assertSee('"monthsToShow"', false);
    }
}
