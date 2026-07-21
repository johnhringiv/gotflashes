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

    public function test_flash_filter_data_breaks_down_by_dimensions(): void
    {
        $male = User::factory()->create(['gender' => 'male', 'date_of_birth' => '2000-01-01']); // U32 in 2026
        Flash::factory()->forUser($male)->sailing()->onDate('2026-05-01')->create(['event_type' => 'regatta']);

        $female = User::factory()->create(['gender' => 'female', 'date_of_birth' => '1960-01-01']); // Masters
        Flash::factory()->forUser($female)->maintenance()->onDate('2026-05-01')->create();

        $filter = Livewire::test('community-stats')->viewData('stats')['flashFilter'];

        $this->assertContains('male', array_column($filter['genders'], 'key'));
        $this->assertContains('female', array_column($filter['genders'], 'key'));
        $this->assertContains('u32', array_column($filter['ageGroups'], 'key'));
        $this->assertContains('masters', array_column($filter['ageGroups'], 'key'));

        $row = collect($filter['rows'])->firstWhere(fn ($r) => $r['category'] === 'regatta');
        $this->assertSame('male', $row['gender']);
        $this->assertSame('u32', $row['ageGroup']);
        $this->assertSame(1, $row['count']);
    }

    public function test_sailor_growth_totals_are_a_running_total_by_date(): void
    {
        User::factory()->create(['created_at' => '2026-02-05 10:00:00', 'gender' => 'male', 'date_of_birth' => '2000-01-01']);
        User::factory()->create(['created_at' => '2026-02-05 12:00:00', 'gender' => 'female', 'date_of_birth' => '1960-01-01']);
        User::factory()->create(['created_at' => '2026-04-10 09:00:00', 'gender' => 'male', 'date_of_birth' => '2000-01-01']);

        $growth = Livewire::test('community-stats')->viewData('stats')['sailorGrowth'];
        $byDate = collect($growth['totals'])->keyBy('date');

        $this->assertSame(2, $byDate['2026-02-05']['total']);
        $this->assertSame(3, $byDate['2026-04-10']['total']);

        // Broken down for stacking by gender and age
        $this->assertContains('male', array_column($growth['genders'], 'key'));
        $this->assertContains('female', array_column($growth['genders'], 'key'));
        $this->assertContains('u32', array_column($growth['ageGroups'], 'key'));
        $this->assertContains('masters', array_column($growth['ageGroups'], 'key'));
        $feb5Male = collect($growth['rows'])->first(fn ($r) => $r['date'] === '2026-02-05' && $r['gender'] === 'male');
        $this->assertSame('u32', $feb5Male['ageGroup']);
        $this->assertSame(1, $feb5Male['count']);
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

    public function test_age_distribution_redistributes_implausible_ages(): void
    {
        // Babies are legitimately brought aboard, so there is no lower age floor;
        // only a missing DOB or an implausible age (an absurd birth year from bad
        // data) reads as unknown. Unknown ages are redistributed into the displayed
        // divisions proportionally, so there is no Unknown bucket and everyone is
        // counted. Here the only disclosed division is 33–54, so the lone bad-data
        // sailor lands there rather than inflating Youth or surfacing as Unknown.
        $sailor = User::factory()->create(['date_of_birth' => '1990-06-01', 'gender' => 'male']); // 36 in 2026 → 33–54
        $implausible = User::factory()->create(['date_of_birth' => '1900-01-02', 'gender' => 'male']); // age > 100 → unknown
        Flash::factory()->forUser($sailor)->sailing()->onDate('2026-05-01')->create();
        Flash::factory()->forUser($implausible)->sailing()->onDate('2026-05-02')->create();

        $ages = Livewire::test('community-stats')->viewData('stats')['ages'];

        $total = array_sum(array_map('array_sum', $ages['counts']));
        $this->assertSame(2, $total); // both counted
        $this->assertNotContains('Unknown', $ages['labels']); // no Unknown bucket
        $this->assertSame(2, $ages['counts']['33–54']['male']); // bad-data sailor redistributed here
    }

    public function test_age_distribution_counts_young_children_as_youth(): void
    {
        // A genuine young child (age 3) counts as Youth, not Unknown.
        $child = User::factory()->create(['date_of_birth' => '2023-06-01', 'gender' => 'female']);
        Flash::factory()->forUser($child)->sailing()->onDate('2026-05-01')->create();

        $ages = Livewire::test('community-stats')->viewData('stats')['ages'];

        $this->assertSame(1, $ages['counts']['Youth']['female']);
        $this->assertNotContains('Unknown', $ages['labels']);
    }

    public function test_age_distribution_buckets_by_class_division(): void
    {
        // One sailor per division; "Open" is intentionally gone (a misnomer for age).
        $birthYears = ['Youth' => 2011, 'U32' => 2000, '33–54' => 1985, 'Masters' => 1960];
        foreach ($birthYears as $birthYear) {
            $u = User::factory()->create(['date_of_birth' => "{$birthYear}-06-01", 'gender' => 'female']);
            Flash::factory()->forUser($u)->sailing()->onDate('2026-05-01')->create();
        }

        $dist = Livewire::test('community-stats')->viewData('stats')['ages'];

        $this->assertSame(['Youth', 'U32', '33–54', 'Masters'], $dist['labels']);
        $this->assertNotContains('Open', $dist['labels']);
        foreach (['Youth', 'U32', '33–54', 'Masters'] as $division) {
            $this->assertSame(1, $dist['counts'][$division]['female'], "{$division} bucket");
        }
    }

    public function test_age_distribution_redistributes_undisclosed_gender(): void
    {
        $female = User::factory()->create(['date_of_birth' => '2000-06-01', 'gender' => 'female']);
        $male = User::factory()->create(['date_of_birth' => '2000-06-01', 'gender' => 'male']);
        $undisclosed = User::factory()->create(['date_of_birth' => '2000-06-01', 'gender' => 'prefer_not_to_say']);
        foreach ([$female, $male, $undisclosed] as $u) {
            Flash::factory()->forUser($u)->sailing()->onDate('2026-05-01')->create();
        }

        $dist = Livewire::test('community-stats')->viewData('stats')['ages'];

        $genderKeys = array_column($dist['genders'], 'key');
        $this->assertContains('female', $genderKeys);
        $this->assertContains('male', $genderKeys);
        // "prefer not to say" carries no series — it is redistributed into the shown
        // genders (not dropped), so all three sailors are still counted in U32.
        $this->assertNotContains('prefer_not_to_say', $genderKeys);
        $this->assertArrayNotHasKey('prefer_not_to_say', $dist['counts']['U32']);
        $this->assertSame(3, $dist['counts']['U32']['male'] + $dist['counts']['U32']['female']);
    }

    public function test_age_distribution_surfaces_undisclosed_when_no_disclosed_gender(): void
    {
        // Degenerate year: every active sailor is undisclosed, so there is no
        // disclosed group to redistribute into. The chart must still show them
        // rather than silently rendering zero everywhere.
        foreach (['2000-06-01', '1985-06-01'] as $dob) {
            $u = User::factory()->create(['date_of_birth' => $dob, 'gender' => 'prefer_not_to_say']);
            Flash::factory()->forUser($u)->sailing()->onDate('2026-05-01')->create();
        }

        $dist = Livewire::test('community-stats')->viewData('stats')['ages'];

        $total = array_sum(array_map('array_sum', $dist['counts']));
        $this->assertSame(2, $total); // both counted, not dropped
        $this->assertContains('prefer_not_to_say', array_column($dist['genders'], 'key'));
    }

    public function test_award_funnel_is_cumulative_registered_to_tiers(): void
    {
        // 3 registered: one never logs, one logs 1 day, one reaches 10+ days
        User::factory()->create(); // never logged

        $starter = User::factory()->create();
        Flash::factory()->forUser($starter)->sailing()->onDate('2026-05-01')->create();

        $achiever = User::factory()->create();
        foreach (range(1, 11) as $day) {
            Flash::factory()->forUser($achiever)->sailing()->onDate(sprintf('2026-04-%02d', $day))->create();
        }

        $funnel = Livewire::test('community-stats')->viewData('stats')['funnel'];

        $this->assertSame('Registered', $funnel[0]['label']);
        $this->assertSame(3, $funnel[0]['count']);  // all registered
        $this->assertSame(2, $funnel[1]['count']);  // logged a day (active)
        $this->assertSame(1, $funnel[2]['count']);  // reached 10+
        $this->assertSame(0, $funnel[3]['count']);  // reached 25+
        $this->assertSame(0, $funnel[4]['count']);  // reached 50+
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

    public function test_prior_year_benchmark_uses_hardcoded_historical_total(): void
    {
        // The app launched in 2026, so the 2025 benchmark comes from the
        // hardcoded pre-launch aggregate (config/community.php), NOT the DB.
        $user = User::factory()->create();
        Flash::factory()->forUser($user)->sailing()->onDate('2026-05-01')->create();
        Setting::set('community_goal_2026', '2000');

        $expected = number_format((int) config('community.historical_totals')[2025]);

        Livewire::test('community-stats')
            ->assertSet('selectedYear', 2026)
            ->assertSee("2025 finished at {$expected} days");
    }

    public function test_prelaunch_years_are_not_selectable(): void
    {
        $user = User::factory()->create();
        Flash::factory()->forUser($user)->sailing()->onDate('2026-05-01')->create();
        // Even if pre-launch rows somehow exist, they must not be offered.
        Flash::factory()->forUser($user)->sailing()->onDate('2025-05-01')->create();

        $years = Livewire::test('community-stats')->viewData('availableYears');

        $this->assertContains(2026, $years);
        $this->assertNotContains(2025, $years);
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

    public function test_super_admins_see_default_goal_hint_when_no_explicit_goal(): void
    {
        // With no year-specific goal set, the page uses the config default and
        // nudges a site admin to set one; award-admins don't get the nudge.
        $siteAdmin = User::factory()->create(['is_super_admin' => true]);
        Flash::factory()->forUser($siteAdmin)->sailing()->onDate('2026-05-01')->create();

        Livewire::actingAs($siteAdmin)
            ->test('community-stats')
            ->assertSee('Showing the default')
            ->assertSee('set a 2026 goal in Settings');
    }

    public function test_award_admins_do_not_see_default_goal_hint(): void
    {
        $awardAdmin = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);
        Flash::factory()->forUser($awardAdmin)->sailing()->onDate('2026-05-01')->create();

        Livewire::actingAs($awardAdmin)
            ->test('community-stats')
            ->assertDontSee('Showing the default');
    }

    public function test_guests_do_not_see_default_goal_hint(): void
    {
        $user = User::factory()->create();
        Flash::factory()->forUser($user)->sailing()->onDate('2026-05-01')->create();

        Livewire::test('community-stats')
            ->assertDontSee('Showing the default');
    }

    public function test_year_change_dispatches_chart_update_event(): void
    {
        // Switch between two post-launch years (2025 is pre-launch, unselectable)
        $user = User::factory()->create();
        Flash::factory()->forUser($user)->sailing()->onDate('2026-05-01')->create();
        Flash::factory()->forUser($user)->sailing()->onDate('2027-05-01')->create();

        Livewire::test('community-stats')
            ->set('selectedYear', 2027)
            ->assertSet('selectedYear', 2027)
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

    public function test_busiest_day_shown_in_heatmap_caption(): void
    {
        $userOne = User::factory()->create();
        $userTwo = User::factory()->create();
        Flash::factory()->forUser($userOne)->sailing()->onDate('2026-05-16')->create();
        Flash::factory()->forUser($userTwo)->sailing()->onDate('2026-05-16')->create();

        Livewire::test('community-stats')
            ->assertSee('Busiest day')
            ->assertSee('May 16')
            ->assertSee('2 sailors on the water');
    }

    public function test_fun_facts_include_season_opener(): void
    {
        $early = User::factory()->create(['first_name' => 'Ada', 'last_name' => 'Early']);
        $late = User::factory()->create();
        Flash::factory()->forUser($early)->sailing()->onDate('2026-03-05')->create();
        Flash::factory()->forUser($late)->sailing()->onDate('2026-06-10')->create();

        Livewire::test('community-stats')
            ->assertSee('Season opener')
            ->assertSee('March 5')
            ->assertSee('Ada Early');
    }

    public function test_fun_facts_include_longest_sailing_streak(): void
    {
        $user = User::factory()->create(['first_name' => 'Streaky', 'last_name' => 'Sam']);
        foreach (['2026-06-01', '2026-06-02', '2026-06-03', '2026-06-04'] as $date) {
            Flash::factory()->forUser($user)->sailing()->onDate($date)->create();
        }

        Livewire::test('community-stats')
            ->assertSee('Longest sailing streak')
            ->assertSee('Streaky Sam sailed 4 days in a row');
    }

    public function test_longest_streak_credits_all_tied_sailors(): void
    {
        $ann = User::factory()->create(['first_name' => 'Ann', 'last_name' => 'Aye']);
        $ben = User::factory()->create(['first_name' => 'Ben', 'last_name' => 'Bee']);
        foreach (['2026-06-01', '2026-06-02', '2026-06-03'] as $date) {
            Flash::factory()->forUser($ann)->sailing()->onDate($date)->create();
            Flash::factory()->forUser($ben)->sailing()->onDate($date)->create();
        }

        Livewire::test('community-stats')
            ->assertSee('Ann Aye and Ben Bee each sailed 3 days in a row');
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
            ->assertSee('"heatmap"', false);
    }
}
