<?php

namespace App\Livewire;

use App\Models\District;
use App\Models\Fleet;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Public community statistics page (/stats).
 *
 * All aggregate data for a year is computed in statsForYear() and cached for
 * 15 minutes. Chart data is passed to D3 (resources/js/stats-charts.js) via a
 * JSON script tag on initial load, and via the 'community-stats-updated'
 * browser event when the year changes.
 */
class CommunityStats extends Component
{
    private const CACHE_MINUTES = 15;

    #[Url]
    public int $selectedYear;

    public function mount(): void
    {
        $available = $this->getAvailableYears();

        if (! isset($this->selectedYear) || ! in_array($this->selectedYear, $available)) {
            $this->selectedYear = now()->year;
        }
    }

    public function updatedSelectedYear(): void
    {
        if (! in_array($this->selectedYear, $this->getAvailableYears())) {
            $this->selectedYear = now()->year;
        }

        $stats = $this->statsForYear($this->selectedYear);

        $this->dispatch('community-stats-updated', payload: $this->chartPayload($stats));
    }

    public function render()
    {
        $stats = $this->statsForYear($this->selectedYear);
        $goal = $this->getCommunityGoal($this->selectedYear);

        return view('livewire.community-stats', [
            'stats' => $stats,
            'goal' => $goal,
            'goalPercent' => $goal ? (int) round(min(100, $stats['counters']['totalQualifying'] / $goal * 100)) : null,
            'availableYears' => $this->getAvailableYears(),
            'chartJson' => json_encode($this->chartPayload($stats), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT),
        ]);
    }

    public static function clearCache(int $year): void
    {
        Cache::forget("community-stats-{$year}");
    }

    /**
     * Years available in the selector: every year with flash activity, plus
     * the current year (so the page works before the first flash of a year).
     *
     * @return array<int>
     */
    private function getAvailableYears(): array
    {
        $years = DB::table('flashes')
            ->selectRaw('DISTINCT strftime("%Y", date) as year')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->push(now()->year)
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();

        return $years;
    }

    private function getCommunityGoal(int $year): ?int
    {
        $goal = Setting::get("community_goal_{$year}");

        return $goal !== null ? (int) $goal : null;
    }

    /**
     * The subset of statsForYear() that the D3 charts consume.
     */
    private function chartPayload(array $stats): array
    {
        return [
            'year' => $stats['year'],
            'previousYear' => $stats['year'] - 1,
            'monthsToShow' => $stats['monthsToShow'],
            'monthly' => $stats['monthly'],
            'heatmap' => $stats['heatmap'],
            'eventMix' => $stats['eventMix'],
            'signups' => $stats['signups'],
            'ages' => $stats['ages'],
            'funnel' => $stats['funnel'],
        ];
    }

    private function statsForYear(int $year): array
    {
        return Cache::remember(
            "community-stats-{$year}",
            now()->addMinutes(self::CACHE_MINUTES),
            fn () => $this->buildStats($year)
        );
    }

    private function buildStats(int $year): array
    {
        return [
            'year' => $year,
            'monthsToShow' => $year === now()->year ? now()->month : 12,
            'counters' => $this->getKeyCounters($year),
            'monthly' => [
                'current' => $this->getFlashesByMonth($year),
                'previous' => $this->getFlashesByMonth($year - 1),
            ],
            'heatmap' => $this->getActivityHeatmap($year),
            'eventMix' => $this->getEventTypeMix($year),
            'signups' => $this->getSignupsByMonth($year),
            'ages' => $this->getAgeDistribution($year),
            'funnel' => $this->getAwardFunnel($year),
            'funFacts' => $this->getFunFacts($year),
        ];
    }

    /**
     * Per-user flash aggregation for a year (same pattern as the leaderboard):
     * sailing_count and non_sailing_count per user, for qualifying-total math.
     */
    private function userFlashesSubquery(int $year)
    {
        return DB::table('flashes')
            ->select([
                'user_id',
                DB::raw("SUM(CASE WHEN activity_type = 'sailing' THEN 1 ELSE 0 END) as sailing_count"),
                DB::raw("SUM(CASE WHEN activity_type IN ('maintenance', 'race_committee') THEN 1 ELSE 0 END) as non_sailing_count"),
            ])
            ->whereRaw("strftime('%Y', date) = ?", [(string) $year])
            ->groupBy('user_id');
    }

    /**
     * Most recent membership per user (carry-forward: latest year <= target),
     * matching the leaderboard's membership resolution.
     */
    private function recentMembershipSubquery(int $year)
    {
        return DB::table('members as m1')
            ->select('m1.*')
            ->joinSub(
                DB::table('members')
                    ->select('user_id', DB::raw('MAX(year) as max_year'))
                    ->where('year', '<=', $year)
                    ->groupBy('user_id'),
                'm2',
                function ($join) {
                    $join->on('m1.user_id', '=', 'm2.user_id')
                        ->on('m1.year', '=', 'm2.max_year');
                }
            );
    }

    // Qualifying total per user: sailing days + at most 5 non-sailing days.
    private const QUALIFYING_SQL = 'sailing_count + CASE WHEN non_sailing_count > 5 THEN 5 ELSE non_sailing_count END';

    private function getKeyCounters(int $year): array
    {
        $totals = DB::query()
            ->fromSub($this->userFlashesSubquery($year), 'user_flashes')
            ->selectRaw('COUNT(*) as sailors')
            ->selectRaw('COALESCE(SUM('.self::QUALIFYING_SQL.'), 0) as total_qualifying')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.self::QUALIFYING_SQL.' >= 10 THEN 1 ELSE 0 END), 0) as achievers')
            ->first();

        // The sentinel "None" fleet / "Unaffiliated/None" district are real rows
        // (affiliations are never null) but don't count as active groups.
        $groups = DB::query()
            ->fromSub($this->userFlashesSubquery($year), 'user_flashes')
            ->joinSub($this->recentMembershipSubquery($year), 'recent_members', 'user_flashes.user_id', '=', 'recent_members.user_id')
            ->selectRaw('COUNT(DISTINCT CASE WHEN recent_members.fleet_id != ? THEN recent_members.fleet_id END) as fleets', [Fleet::noneId()])
            ->selectRaw('COUNT(DISTINCT CASE WHEN recent_members.district_id != ? THEN recent_members.district_id END) as districts', [District::noneId()])
            ->first();

        return [
            'totalQualifying' => (int) $totals->total_qualifying,
            'activeSailors' => (int) $totals->sailors,
            'activeFleets' => (int) ($groups->fleets ?? 0),
            'activeDistricts' => (int) ($groups->districts ?? 0),
            'awardAchievers' => (int) $totals->achievers,
        ];
    }

    /**
     * Flash counts per month (all activity types).
     *
     * @return array<int> 12 entries, index 0 = January
     */
    private function getFlashesByMonth(int $year): array
    {
        $rows = DB::table('flashes')
            ->selectRaw("CAST(strftime('%m', date) AS INTEGER) as month, COUNT(*) as count")
            ->whereRaw("strftime('%Y', date) = ?", [(string) $year])
            ->groupBy('month')
            ->pluck('count', 'month');

        return array_map(fn ($m) => (int) ($rows[$m] ?? 0), range(1, 12));
    }

    /**
     * Flash count per date, for the contribution-style heatmap.
     *
     * @return array<string, int> 'Y-m-d' => count
     */
    private function getActivityHeatmap(int $year): array
    {
        return DB::table('flashes')
            ->selectRaw('DATE(date) as day, COUNT(*) as count')
            ->whereRaw("strftime('%Y', date) = ?", [(string) $year])
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('count', 'day')
            ->map(fn ($count) => (int) $count)
            ->toArray();
    }

    /**
     * Sailing event-type counts per month, for the stacked mix chart.
     *
     * @return array<int, array<string, int>> month (1-12) => type => count
     */
    private function getEventTypeMix(int $year): array
    {
        $rows = DB::table('flashes')
            ->selectRaw("CAST(strftime('%m', date) AS INTEGER) as month, event_type, COUNT(*) as count")
            ->where('activity_type', 'sailing')
            ->whereNotNull('event_type')
            ->whereRaw("strftime('%Y', date) = ?", [(string) $year])
            ->groupBy('month', 'event_type')
            ->get();

        $mix = [];
        foreach (range(1, 12) as $month) {
            $mix[$month] = ['regatta' => 0, 'club_race' => 0, 'practice' => 0, 'leisure' => 0];
        }
        foreach ($rows as $row) {
            if (isset($mix[$row->month][$row->event_type])) {
                $mix[$row->month][$row->event_type] = (int) $row->count;
            }
        }

        return $mix;
    }

    /**
     * New account signups per month.
     *
     * @return array<int> 12 entries, index 0 = January
     */
    private function getSignupsByMonth(int $year): array
    {
        $rows = DB::table('users')
            ->selectRaw("CAST(strftime('%m', created_at) AS INTEGER) as month, COUNT(*) as count")
            ->whereRaw("strftime('%Y', created_at) = ?", [(string) $year])
            ->groupBy('month')
            ->pluck('count', 'month');

        return array_map(fn ($m) => (int) ($rows[$m] ?? 0), range(1, 12));
    }

    /**
     * Age distribution of sailors active in the year, bucketed by decade.
     * Implausible ages (outside 8-100, i.e. typo'd birth dates) are excluded.
     */
    private function getAgeDistribution(int $year): array
    {
        $ages = DB::query()
            ->fromSub($this->userFlashesSubquery($year), 'user_flashes')
            ->join('users', 'users.id', '=', 'user_flashes.user_id')
            ->whereNotNull('users.date_of_birth')
            ->selectRaw("? - CAST(strftime('%Y', users.date_of_birth) AS INTEGER) as age", [$year])
            ->pluck('age')
            ->filter(fn ($age) => $age >= 8 && $age <= 100);

        $buckets = [
            'Under 20' => fn ($a) => $a < 20,
            '20s' => fn ($a) => $a >= 20 && $a < 30,
            '30s' => fn ($a) => $a >= 30 && $a < 40,
            '40s' => fn ($a) => $a >= 40 && $a < 50,
            '50s' => fn ($a) => $a >= 50 && $a < 60,
            '60s' => fn ($a) => $a >= 60 && $a < 70,
            '70+' => fn ($a) => $a >= 70,
        ];

        $labels = array_keys($buckets);
        $counts = array_map(fn ($test) => $ages->filter($test)->count(), array_values($buckets));

        return ['labels' => $labels, 'counts' => $counts];
    }

    /**
     * How many active sailors sit in each award-progress band.
     *
     * @return array<array{label: string, count: int}>
     */
    private function getAwardFunnel(int $year): array
    {
        $row = DB::query()
            ->fromSub($this->userFlashesSubquery($year), 'user_flashes')
            ->selectRaw('SUM(CASE WHEN '.self::QUALIFYING_SQL.' < 10 THEN 1 ELSE 0 END) as tier0')
            ->selectRaw('SUM(CASE WHEN '.self::QUALIFYING_SQL.' BETWEEN 10 AND 24 THEN 1 ELSE 0 END) as tier10')
            ->selectRaw('SUM(CASE WHEN '.self::QUALIFYING_SQL.' BETWEEN 25 AND 49 THEN 1 ELSE 0 END) as tier25')
            ->selectRaw('SUM(CASE WHEN '.self::QUALIFYING_SQL.' >= 50 THEN 1 ELSE 0 END) as tier50')
            ->first();

        return [
            ['label' => 'Getting started (1–9 days)', 'count' => (int) ($row->tier0 ?? 0)],
            ['label' => '10-day award (10–24)', 'count' => (int) ($row->tier10 ?? 0)],
            ['label' => '25-day award (25–49)', 'count' => (int) ($row->tier25 ?? 0)],
            ['label' => '50-day award + burgee (50+)', 'count' => (int) ($row->tier50 ?? 0)],
        ];
    }

    /**
     * Dynamically generated highlight cards. Facts without enough data are
     * skipped rather than rendered empty.
     *
     * @return array<array{title: string, value: string, detail: string}>
     */
    private function getFunFacts(int $year): array
    {
        $facts = [];

        // Busiest day on the water (distinct sailors with a sailing flash)
        $busiest = DB::table('flashes')
            ->selectRaw('DATE(date) as day, COUNT(DISTINCT user_id) as sailors')
            ->where('activity_type', 'sailing')
            ->whereRaw("strftime('%Y', date) = ?", [(string) $year])
            ->groupBy('day')
            ->orderByDesc('sailors')
            ->orderBy('day')
            ->first();

        if ($busiest && $busiest->sailors >= 2) {
            $facts[] = [
                'title' => 'Busiest day on the water',
                'value' => Carbon::parse($busiest->day)->format('F j'),
                'detail' => "{$busiest->sailors} sailors logged a sailing day",
            ];
        }

        // Most active fleet by qualifying days
        $topFleet = DB::query()
            ->fromSub($this->userFlashesSubquery($year), 'user_flashes')
            ->joinSub($this->recentMembershipSubquery($year), 'recent_members', 'user_flashes.user_id', '=', 'recent_members.user_id')
            ->join('fleets', 'fleets.id', '=', 'recent_members.fleet_id')
            ->where('fleets.id', '!=', Fleet::noneId())
            ->select('fleets.fleet_number', 'fleets.fleet_name')
            ->selectRaw('SUM('.self::QUALIFYING_SQL.') as total')
            ->groupBy('fleets.id', 'fleets.fleet_number', 'fleets.fleet_name')
            ->orderByDesc('total')
            ->first();

        if ($topFleet && $topFleet->total > 0) {
            $facts[] = [
                'title' => 'Most active fleet',
                'value' => "Fleet {$topFleet->fleet_number}",
                'detail' => "{$topFleet->fleet_name} — {$topFleet->total} qualifying days",
            ];
        }

        // Most popular location (normalized: locations are free text)
        $locations = DB::table('flashes')
            ->selectRaw('TRIM(location) as raw, COUNT(*) as count')
            ->whereRaw("TRIM(COALESCE(location, '')) != ''")
            ->whereRaw("strftime('%Y', date) = ?", [(string) $year])
            ->groupBy('raw')
            ->get();

        $grouped = $locations->groupBy(fn ($row) => mb_strtolower($row->raw));
        $top = $grouped->map(fn ($variants) => (object) [
            'display' => $variants->sortByDesc('count')->first()->raw,
            'total' => $variants->sum('count'),
        ])->sortByDesc('total')->first();

        if ($top && $top->total >= 3) {
            $facts[] = [
                'title' => 'Most popular location',
                'value' => $top->display,
                'detail' => "{$top->total} days logged there",
            ];
        }

        // Favorite day of the week
        $dowRow = DB::table('flashes')
            ->selectRaw("CAST(strftime('%w', date) AS INTEGER) as dow, COUNT(*) as count")
            ->whereRaw("strftime('%Y', date) = ?", [(string) $year])
            ->groupBy('dow')
            ->orderByDesc('count')
            ->first();

        if ($dowRow && $dowRow->count >= 3) {
            $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            $facts[] = [
                'title' => 'Favorite day of the week',
                'value' => "{$days[$dowRow->dow]}s",
                'detail' => "{$dowRow->count} days logged on {$days[$dowRow->dow]}s",
            ];
        }

        // Newest sailor (joined during the selected year)
        $newest = DB::table('users')
            ->select('first_name', 'last_name', 'created_at')
            ->whereRaw("strftime('%Y', created_at) = ?", [(string) $year])
            ->orderByDesc('created_at')
            ->first();

        if ($newest) {
            $facts[] = [
                'title' => 'Newest sailor',
                'value' => "{$newest->first_name} {$newest->last_name}",
                'detail' => 'Joined '.Carbon::parse($newest->created_at)->format('F j, Y'),
            ];
        }

        // The 50+ club
        $fiftyClub = DB::query()
            ->fromSub($this->userFlashesSubquery($year), 'user_flashes')
            ->selectRaw('SUM(CASE WHEN '.self::QUALIFYING_SQL.' >= 50 THEN 1 ELSE 0 END) as count')
            ->first();

        if ($fiftyClub && $fiftyClub->count > 0) {
            $facts[] = [
                'title' => 'The 50+ club',
                'value' => "{$fiftyClub->count} ".($fiftyClub->count == 1 ? 'sailor' : 'sailors'),
                'detail' => 'Earned all three awards this year',
            ];
        }

        return $facts;
    }
}
