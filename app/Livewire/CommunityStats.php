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

    // Bump when the cached stats shape changes, so old payloads are ignored
    // rather than 500-ing the view after a deploy.
    private const CACHE_VERSION = 12;

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
        $priorTotal = $stats['priorTotal'];

        return view('livewire.community-stats', [
            'stats' => $stats,
            'goal' => $goal,
            'goalPercent' => $goal ? (int) round(min(100, $stats['counters']['totalQualifying'] / $goal * 100)) : null,
            'priorTotal' => $priorTotal,
            // Prior-year total as a fraction of the goal, for the benchmark line
            'priorPercent' => ($goal && $priorTotal > 0) ? min(100, $priorTotal / $goal * 100) : null,
            'availableYears' => $this->getAvailableYears(),
            'chartJson' => json_encode($this->chartPayload($stats), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT),
        ]);
    }

    public static function clearCache(int $year): void
    {
        Cache::forget(self::cacheKey($year));
    }

    private static function cacheKey(int $year): string
    {
        return 'community-stats-v'.self::CACHE_VERSION."-{$year}";
    }

    /**
     * Years available in the selector: every year with flash activity, plus
     * the current year (so the page works before the first flash of a year).
     *
     * @return array<int>
     */
    private function getAvailableYears(): array
    {
        // The app launched in start_year (2026); pre-launch years have no
        // in-app per-day data and are never selectable.
        $launchYear = (int) config('app.start_year', 2026);

        return DB::table('flashes')
            ->selectRaw('DISTINCT strftime("%Y", date) as year')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->push(now()->year)
            ->filter(fn ($year) => $year >= $launchYear)
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();
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
            'monthsToShow' => $stats['monthsToShow'],
            'heatmap' => $stats['heatmap'],
            'sailorGrowth' => $stats['sailorGrowth'],
            'flashFilter' => $stats['flashFilter'],
            'ages' => $stats['ages'],
            'funnel' => $stats['funnel'],
        ];
    }

    private function statsForYear(int $year): array
    {
        return Cache::remember(
            self::cacheKey($year),
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
            'priorTotal' => $this->getPriorYearTotal($year),
            'heatmap' => $this->getActivityHeatmap($year),
            'sailorGrowth' => $this->getSailorGrowthData($year),
            'flashFilter' => $this->getFlashFilterData($year),
            'ages' => $this->getAgeDistribution($year),
            'funnel' => $this->getAwardFunnel($year),
            'funFacts' => $this->getFunFacts($year),
        ];
    }

    // Finest-grained flash category (stack order): sailing event types, then
    // the two non-sailing activity types.
    private const FLASH_CATEGORIES = ['regatta', 'club_race', 'practice', 'leisure', 'maintenance', 'race_committee'];

    private function ageGroupKey(?int $age): string
    {
        // Babies and young children are legitimately brought aboard, so there is
        // no lower age floor. Only a missing DOB or an implausible age (a future
        // or absurd birth year from bad data) reads as Unknown, rather than being
        // forced into a real division.
        if ($age === null || $age < 0 || $age > 100) {
            return 'unknown';
        }
        if ($age <= 20) {
            return 'youth';
        }
        if ($age <= 32) {
            return 'u32';
        }
        if ($age <= 54) {
            return 'mid';
        }

        return 'masters';
    }

    /**
     * Per-(date, category, gender, age-group) flash counts, plus the dimension
     * values present, for the client-side-filterable cumulative chart. The
     * frontend re-aggregates as toggles change, so counts stay at daily
     * increments here (not cumulative).
     */
    private function getFlashFilterData(int $year): array
    {
        $flashes = DB::table('flashes as f')
            ->join('users as u', 'u.id', '=', 'f.user_id')
            ->selectRaw("DATE(f.date) as day, CASE WHEN f.activity_type = 'sailing' THEN f.event_type ELSE f.activity_type END as category, u.gender as gender, u.date_of_birth as dob")
            ->whereRaw("strftime('%Y', f.date) = ?", [(string) $year])
            ->get();

        $agg = [];
        foreach ($flashes as $flash) {
            if (! in_array($flash->category, self::FLASH_CATEGORIES, true)) {
                continue;
            }
            $gender = in_array($flash->gender, ['male', 'female', 'non_binary'], true) ? $flash->gender : 'prefer_not_to_say';
            $age = $flash->dob ? $year - (int) substr($flash->dob, 0, 4) : null;
            $ageGroup = $this->ageGroupKey($age);
            $key = "{$flash->day}\x1f{$flash->category}\x1f{$gender}\x1f{$ageGroup}";
            $agg[$key] = ($agg[$key] ?? 0) + 1;
        }

        $rows = [];
        $seenGenders = $seenAges = $seenCats = [];
        foreach ($agg as $key => $count) {
            [$day, $category, $gender, $ageGroup] = explode("\x1f", $key);
            $rows[] = ['date' => $day, 'category' => $category, 'gender' => $gender, 'ageGroup' => $ageGroup, 'count' => $count];
            $seenGenders[$gender] = $seenAges[$ageGroup] = $seenCats[$category] = true;
        }

        $genderLabels = ['male' => 'Male', 'female' => 'Female', 'non_binary' => 'Non-binary', 'prefer_not_to_say' => 'Undisclosed'];
        $ageLabels = ['youth' => 'Youth', 'u32' => 'U32', 'mid' => '33–54', 'masters' => 'Masters', 'unknown' => 'Unknown'];
        $catLabels = ['regatta' => 'Regatta', 'club_race' => 'Club Race', 'practice' => 'Practice', 'leisure' => 'Day Sailing', 'maintenance' => 'Maintenance', 'race_committee' => 'Race Committee'];

        $present = fn (array $order, array $labels, array $seen) => array_values(array_map(
            fn ($k) => ['key' => $k, 'label' => $labels[$k]],
            array_filter($order, fn ($k) => isset($seen[$k]))
        ));

        return [
            'rows' => $rows,
            'genders' => $present(array_keys($genderLabels), $genderLabels, $seenGenders),
            'ageGroups' => $present(array_keys($ageLabels), $ageLabels, $seenAges),
            'categories' => $present(self::FLASH_CATEGORIES, $catLabels, $seenCats),
        ];
    }

    /**
     * Sailor growth at date resolution, broken down by gender and age group so
     * the frontend can stack by either. Accounts created before the year collapse
     * to a year-start baseline; created_at is a full timestamp so points track
     * actual signup dates. Also returns cumulative totals for the table twin.
     */
    private function getSailorGrowthData(int $year): array
    {
        $users = DB::table('users')
            ->selectRaw("DATE(created_at) as day, CAST(strftime('%Y', created_at) AS INTEGER) as created_year, gender, date_of_birth as dob")
            ->whereRaw('CAST(strftime(\'%Y\', created_at) AS INTEGER) <= ?', [$year])
            ->get();

        $agg = [];
        $perDate = [];
        foreach ($users as $user) {
            $gender = in_array($user->gender, ['male', 'female', 'non_binary'], true) ? $user->gender : 'prefer_not_to_say';
            $age = $user->dob ? $year - (int) substr($user->dob, 0, 4) : null;
            $ageGroup = $this->ageGroupKey($age);
            $date = ((int) $user->created_year < $year) ? "{$year}-01-01" : $user->day;
            $key = "{$date}\x1f{$gender}\x1f{$ageGroup}";
            $agg[$key] = ($agg[$key] ?? 0) + 1;
            $perDate[$date] = ($perDate[$date] ?? 0) + 1;
        }

        $rows = [];
        $seenGenders = $seenAges = [];
        foreach ($agg as $key => $count) {
            [$date, $gender, $ageGroup] = explode("\x1f", $key);
            $rows[] = ['date' => $date, 'gender' => $gender, 'ageGroup' => $ageGroup, 'count' => $count];
            $seenGenders[$gender] = $seenAges[$ageGroup] = true;
        }

        ksort($perDate);
        $running = 0;
        $totals = [];
        foreach ($perDate as $date => $count) {
            $running += $count;
            $totals[] = ['date' => $date, 'total' => $running];
        }

        $genderLabels = ['male' => 'Male', 'female' => 'Female', 'non_binary' => 'Non-binary', 'prefer_not_to_say' => 'Undisclosed'];
        $ageLabels = ['youth' => 'Youth', 'u32' => 'U32', 'mid' => '33–54', 'masters' => 'Masters', 'unknown' => 'Unknown'];
        $present = fn (array $order, array $labels, array $seen) => array_values(array_map(
            fn ($k) => ['key' => $k, 'label' => $labels[$k]],
            array_filter($order, fn ($k) => isset($seen[$k]))
        ));

        return [
            'rows' => $rows,
            'genders' => $present(array_keys($genderLabels), $genderLabels, $seenGenders),
            'ageGroups' => $present(array_keys($ageLabels), $ageLabels, $seenAges),
            'totals' => $totals,
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
     * The prior year's community total, for the benchmark line. Pre-launch
     * years (no in-app data) fall back to the hardcoded historical aggregate
     * from the old manual process (config/community.php).
     */
    private function getPriorYearTotal(int $year): int
    {
        $prior = $year - 1;
        $historical = config('community.historical_totals', []);

        return (int) ($historical[$prior] ?? $this->getQualifyingTotal($prior));
    }

    /**
     * Community-wide qualifying total for a year (sailing days plus up to 5
     * non-sailing days per sailor). Used for the prior-year benchmark line.
     */
    private function getQualifyingTotal(int $year): int
    {
        $row = DB::query()
            ->fromSub($this->userFlashesSubquery($year), 'user_flashes')
            ->selectRaw('COALESCE(SUM('.self::QUALIFYING_SQL.'), 0) as total')
            ->first();

        return (int) ($row->total ?? 0);
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

    // Gender display labels + canonical order (also the stack order, bottom→top).
    // "Prefer not to say" / unset are deliberately excluded from the public
    // gender split — it isn't a gender identity, and tiny undisclosed cells
    // risk being identifying on a public page.
    private const GENDER_LABELS = [
        'female' => 'Female',
        'male' => 'Male',
        'non_binary' => 'Non-binary',
    ];

    /**
     * Age distribution of sailors active in the year, bucketed by Lightning
     * Class age division and split by gender. Youth and U32 are the class's
     * growth segments; Masters is 55+. Implausible (too old/young, i.e. typo'd
     * birth dates) or missing birth dates land in an "Unknown" bucket, shown
     * only when non-empty. Only genders present are returned.
     */
    private function getAgeDistribution(int $year): array
    {
        $sailors = DB::query()
            ->fromSub($this->userFlashesSubquery($year), 'user_flashes')
            ->join('users', 'users.id', '=', 'user_flashes.user_id')
            ->selectRaw('users.date_of_birth as dob, users.gender as gender')
            ->get()
            ->map(fn ($r) => (object) [
                'ageGroup' => $this->ageGroupKey($r->dob ? $year - (int) substr($r->dob, 0, 4) : null),
                // Normalise blank/unknown gender values to "undisclosed"
                'gender' => in_array($r->gender, ['female', 'male', 'non_binary'], true) ? $r->gender : 'prefer_not_to_say',
            ]);

        // Ordered young → old. "Open" was dropped — in sailing "open" means the
        // all-comers division, not an age band.
        $divisions = [
            ['label' => 'Youth', 'range' => '20 & under', 'key' => 'youth'],
            ['label' => 'U32', 'range' => '21–32', 'key' => 'u32'],
            ['label' => '33–54', 'range' => '33–54', 'key' => 'mid'],
            ['label' => 'Masters', 'range' => '55 & over', 'key' => 'masters'],
            ['label' => 'Unknown', 'range' => 'Age not given', 'key' => 'unknown'],
        ];

        // Genders actually present, in canonical order
        $present = array_values(array_filter(
            array_keys(self::GENDER_LABELS),
            fn ($g) => $sailors->contains('gender', $g)
        ));

        // Only keep the Unknown bucket if a shown-gender sailor lands in it.
        // (It is the last division, so filtering it never leaves a keyed gap.)
        $divisions = array_filter($divisions, fn ($d) => $d['key'] !== 'unknown'
            || $sailors->contains(fn ($s) => $s->ageGroup === 'unknown' && in_array($s->gender, $present, true)));

        $counts = [];
        foreach ($divisions as $d) {
            $inDivision = $sailors->where('ageGroup', $d['key']);
            $counts[$d['label']] = [];
            foreach ($present as $g) {
                $counts[$d['label']][$g] = $inDivision->where('gender', $g)->count();
            }
        }

        return [
            'labels' => array_column($divisions, 'label'),
            'ranges' => array_column($divisions, 'range'),
            'genders' => array_map(fn ($g) => ['key' => $g, 'label' => self::GENDER_LABELS[$g]], $present),
            'counts' => $counts,
        ];
    }

    /**
     * Award-progress funnel: cumulative "reached at least this stage" counts,
     * from registered accounts down through activation and each award tier, so
     * the drop-off at each step is visible.
     *
     * @return array<array{label: string, count: int}>
     */
    private function getAwardFunnel(int $year): array
    {
        $registered = DB::table('users')
            ->whereRaw("strftime('%Y', created_at) <= ?", [(string) $year])
            ->count();

        $row = DB::query()
            ->fromSub($this->userFlashesSubquery($year), 'user_flashes')
            ->selectRaw('COUNT(*) as active')
            ->selectRaw('SUM(CASE WHEN '.self::QUALIFYING_SQL.' >= 10 THEN 1 ELSE 0 END) as reached10')
            ->selectRaw('SUM(CASE WHEN '.self::QUALIFYING_SQL.' >= 25 THEN 1 ELSE 0 END) as reached25')
            ->selectRaw('SUM(CASE WHEN '.self::QUALIFYING_SQL.' >= 50 THEN 1 ELSE 0 END) as reached50')
            ->first();

        return [
            ['label' => 'Registered', 'count' => $registered],
            ['label' => 'Logged a day', 'count' => (int) ($row->active ?? 0)],
            ['label' => '10-day award', 'count' => (int) ($row->reached10 ?? 0)],
            ['label' => '25-day award', 'count' => (int) ($row->reached25 ?? 0)],
            ['label' => '50-day award', 'count' => (int) ($row->reached50 ?? 0)],
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

        // Most flashes logged at once — the largest single bulk entry, i.e. the
        // most flashes one sailor created in a single save (multi-date logging
        // shares one created_at timestamp).
        $batch = DB::table('flashes as f')
            ->join('users as u', 'u.id', '=', 'f.user_id')
            ->selectRaw('u.first_name, u.last_name, COUNT(*) as n')
            ->whereRaw("strftime('%Y', f.date) = ?", [(string) $year])
            ->groupBy('f.user_id', 'f.created_at')
            ->orderByDesc('n')
            ->orderBy('f.created_at')
            ->first();

        if ($batch && $batch->n >= 2) {
            $facts[] = [
                'title' => 'Most flashes logged at once',
                'value' => "{$batch->n} flashes",
                'detail' => "Logged in a single entry by {$batch->first_name} {$batch->last_name}",
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
