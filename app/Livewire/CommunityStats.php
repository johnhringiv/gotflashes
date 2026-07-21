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
    private const CACHE_VERSION = 13;

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
            'goalIsDefault' => $this->goalIsDefault($this->selectedYear),
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

        if ($goal !== null) {
            return (int) $goal;
        }

        // Fall back to the configured default so a fresh deploy shows a target.
        $default = config('community.default_goal');

        return $default !== null ? (int) $default : null;
    }

    private function goalIsDefault(int $year): bool
    {
        return Setting::get("community_goal_{$year}") === null;
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
            'busiestDay' => $this->getBusiestDay($year),
            'funFacts' => $this->getFunFacts($year),
        ];
    }

    // Finest-grained flash category (stack order): sailing event types, then
    // the two non-sailing activity types.
    private const FLASH_CATEGORIES = ['regatta', 'club_race', 'practice', 'leisure', 'maintenance', 'race_committee'];

    private function ageGroupKey(?int $age): string
    {
        // $age is calendar-year age (statsYear - birthYear) = the age the sailor
        // reaches by Dec 31 of the stats year. This is a deliberate convention, not
        // an approximation: it matches how one-design age divisions ("U32") are
        // defined and stays stable regardless of when the page is viewed, unlike an
        // exact age-as-of-today that would shift a sailor across a division mid-season.
        //
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

    // Displayed group orders for the demographic charts. Members whose gender is
    // undisclosed, or whose age is unknown (missing/typo'd DOB), are redistributed
    // into these groups in proportion to the disclosed population (see
    // redistributeUnknown()), so no Unknown/Undisclosed series can single them out.
    private const GENDER_GROUPS = ['male', 'female', 'non_binary'];

    private const AGE_GROUPS = ['youth', 'u32', 'mid', 'masters'];

    // Raw dimension keys for a member (the sentinel marks undisclosed/unknown).
    private function rawGenderKey(?string $gender): string
    {
        return in_array($gender, self::GENDER_GROUPS, true) ? $gender : 'prefer_not_to_say';
    }

    private function ageKeyFromDob(?string $dob, int $year): string
    {
        return $this->ageGroupKey($dob ? $year - (int) substr($dob, 0, 4) : null);
    }

    /**
     * Proportionally impute a dimension's "unknown" members into the displayed
     * groups, so the charts carry no Unknown/Undisclosed series and members who
     * opted out (or have bad data) can't be re-identified from a lone small cell.
     * Deterministic largest-remainder (Hamilton) allocation keyed on user id:
     * reproducible across renders, integer counts, no fabricated fractions.
     *
     * @param  array<int, string>  $raw  userId => raw group key
     * @param  string  $unknownKey  the sentinel to redistribute
     * @param  list<string>  $displayGroups  displayed groups, in order
     * @return array<int, string> userId => resolved group key
     */
    private function redistributeUnknown(array $raw, string $unknownKey, array $displayGroups): array
    {
        $known = array_fill_keys($displayGroups, 0);
        $unknownIds = [];
        foreach ($raw as $uid => $group) {
            if (isset($known[$group])) {
                $known[$group]++;
            } else {
                $unknownIds[] = $uid; // the unknown sentinel (or any stray value)
            }
        }

        $totalKnown = array_sum($known);
        // Nothing disclosed to base proportions on (real data never hits this),
        // or nothing to redistribute — leave the input untouched.
        if ($totalKnown === 0 || $unknownIds === []) {
            return $raw;
        }

        sort($unknownIds); // stable, deterministic assignment order
        $n = count($unknownIds);

        // Largest-remainder quotas proportional to the disclosed distribution.
        $quota = $remainder = [];
        $seated = 0;
        foreach ($displayGroups as $group) {
            $exact = $known[$group] / $totalKnown * $n;
            $quota[$group] = (int) floor($exact);
            $remainder[$group] = $exact - $quota[$group];
            $seated += $quota[$group];
        }
        // Hand out leftover seats to the largest remainders (ties → display order).
        $byRemainder = $displayGroups;
        usort($byRemainder, fn ($a, $b) => ($remainder[$b] <=> $remainder[$a])
            ?: (array_search($a, $displayGroups) <=> array_search($b, $displayGroups)));
        foreach (array_slice($byRemainder, 0, $n - $seated) as $group) {
            $quota[$group]++;
        }

        $resolved = $raw;
        $i = 0;
        foreach ($displayGroups as $group) {
            for ($k = 0; $k < $quota[$group]; $k++) {
                $resolved[$unknownIds[$i++]] = $group;
            }
        }

        return $resolved;
    }

    /**
     * Per-(date, category, gender, age-group) flash counts, plus the dimension
     * values present, for the client-side-filterable cumulative chart. The
     * frontend re-aggregates as toggles change, so counts stay at daily
     * increments here (not cumulative). Undisclosed gender / unknown age are
     * redistributed per member so those series never appear.
     */
    private function getFlashFilterData(int $year): array
    {
        $flashes = DB::table('flashes as f')
            ->join('users as u', 'u.id', '=', 'f.user_id')
            ->selectRaw("f.user_id as user_id, DATE(f.date) as day, CASE WHEN f.activity_type = 'sailing' THEN f.event_type ELSE f.activity_type END as category, u.gender as gender, u.date_of_birth as dob")
            ->whereRaw("strftime('%Y', f.date) = ?", [(string) $year])
            ->get();

        // Resolve each member's gender/age once, redistributing undisclosed/unknown.
        $rawGender = $rawAge = [];
        foreach ($flashes as $flash) {
            $rawGender[$flash->user_id] = $this->rawGenderKey($flash->gender);
            $rawAge[$flash->user_id] = $this->ageKeyFromDob($flash->dob, $year);
        }
        $gender = $this->redistributeUnknown($rawGender, 'prefer_not_to_say', self::GENDER_GROUPS);
        $age = $this->redistributeUnknown($rawAge, 'unknown', self::AGE_GROUPS);

        $agg = [];
        foreach ($flashes as $flash) {
            if (! in_array($flash->category, self::FLASH_CATEGORIES, true)) {
                continue;
            }
            $key = "{$flash->day}\x1f{$flash->category}\x1f{$gender[$flash->user_id]}\x1f{$age[$flash->user_id]}";
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
            ->selectRaw("id as user_id, DATE(created_at) as day, CAST(strftime('%Y', created_at) AS INTEGER) as created_year, gender, date_of_birth as dob")
            ->whereRaw('CAST(strftime(\'%Y\', created_at) AS INTEGER) <= ?', [$year])
            ->get();

        // Resolve each member's gender/age once, redistributing undisclosed/unknown.
        $rawGender = $rawAge = [];
        foreach ($users as $user) {
            $rawGender[$user->user_id] = $this->rawGenderKey($user->gender);
            $rawAge[$user->user_id] = $this->ageKeyFromDob($user->dob, $year);
        }
        $gender = $this->redistributeUnknown($rawGender, 'prefer_not_to_say', self::GENDER_GROUPS);
        $age = $this->redistributeUnknown($rawAge, 'unknown', self::AGE_GROUPS);

        $agg = [];
        $perDate = [];
        foreach ($users as $user) {
            $date = ((int) $user->created_year < $year) ? "{$year}-01-01" : $user->day;
            $key = "{$date}\x1f{$gender[$user->user_id]}\x1f{$age[$user->user_id]}";
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
    // Canonical expression for the "5 non-sailing days per year" cap — shared
    // with AdminSettings so the admin goal page can never desync from /stats.
    // Expects `sailing_count`/`non_sailing_count` columns in the enclosing query.
    public const QUALIFYING_SQL = 'sailing_count + CASE WHEN non_sailing_count > 5 THEN 5 ELSE non_sailing_count END';

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
    // "Prefer not to say" / unset carry no series of their own — those members
    // are redistributed into the displayed genders (see redistributeUnknown()),
    // so a tiny undisclosed cell can never single someone out on a public page.
    private const GENDER_LABELS = [
        'female' => 'Female',
        'male' => 'Male',
        'non_binary' => 'Non-binary',
    ];

    /**
     * Age distribution of sailors active in the year, bucketed by Lightning
     * Class age division and split by gender. Youth and U32 are the class's
     * growth segments; Masters is 55+. Undisclosed gender and unknown age
     * (implausible/typo'd or missing birth dates) are redistributed into the
     * displayed groups, so there is no Unknown/Undisclosed cell. Only genders
     * present are returned.
     */
    private function getAgeDistribution(int $year): array
    {
        $rows = DB::query()
            ->fromSub($this->userFlashesSubquery($year), 'user_flashes')
            ->join('users', 'users.id', '=', 'user_flashes.user_id')
            ->selectRaw('users.id as user_id, users.date_of_birth as dob, users.gender as gender')
            ->get();

        // Resolve each active sailor's gender/age once, redistributing undisclosed
        // gender and unknown age into the displayed groups.
        $rawGender = $rawAge = [];
        foreach ($rows as $r) {
            $rawGender[$r->user_id] = $this->rawGenderKey($r->gender);
            $rawAge[$r->user_id] = $this->ageKeyFromDob($r->dob, $year);
        }
        $genderMap = $this->redistributeUnknown($rawGender, 'prefer_not_to_say', self::GENDER_GROUPS);
        $ageMap = $this->redistributeUnknown($rawAge, 'unknown', self::AGE_GROUPS);
        $sailors = $rows->map(fn ($r) => (object) [
            'ageGroup' => $ageMap[$r->user_id],
            'gender' => $genderMap[$r->user_id],
        ]);

        // Ordered young → old. "Open" was dropped — in sailing "open" means the
        // all-comers division, not an age band. No Unknown bucket: unknown ages
        // are redistributed into these divisions.
        $divisions = [
            ['label' => 'Youth', 'range' => '20 & under', 'key' => 'youth'],
            ['label' => 'U32', 'range' => '21–32', 'key' => 'u32'],
            ['label' => '33–54', 'range' => '33–54', 'key' => 'mid'],
            ['label' => 'Masters', 'range' => '55 & over', 'key' => 'masters'],
        ];

        // Genders actually present, in canonical order.
        $present = array_values(array_filter(
            array_keys(self::GENDER_LABELS),
            fn ($g) => $sailors->contains('gender', $g)
        ));

        // Degenerate all-undisclosed year: redistribution had no disclosed group
        // to impute into, so surface the undisclosed sailors rather than an empty
        // chart. Only reachable if every active sailor that year is undisclosed.
        if ($present === [] && $sailors->isNotEmpty()) {
            $present = ['prefer_not_to_say'];
        }

        $genderLabel = fn (string $g): string => self::GENDER_LABELS[$g] ?? 'Undisclosed';

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
            'genders' => array_map(fn ($g) => ['key' => $g, 'label' => $genderLabel($g)], $present),
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
    /**
     * Busiest day on the water: the date with the most distinct sailing sailors.
     * Shown as a caption on the activity-heatmap card. Null below 2 sailors.
     *
     * @return array{date: string, sailors: int}|null
     */
    private function getBusiestDay(int $year): ?array
    {
        $busiest = DB::table('flashes')
            ->selectRaw('DATE(date) as day, COUNT(DISTINCT user_id) as sailors')
            ->where('activity_type', 'sailing')
            ->whereRaw("strftime('%Y', date) = ?", [(string) $year])
            ->groupBy('day')
            ->orderByDesc('sailors')
            ->orderBy('day')
            ->first();

        if (! $busiest || $busiest->sailors < 2) {
            return null;
        }

        return [
            'date' => Carbon::parse($busiest->day)->format('F j'),
            'sailors' => (int) $busiest->sailors,
        ];
    }

    private function getFunFacts(int $year): array
    {
        $facts = [];

        // Season opener — the first sailing day of the year, and who logged it.
        $opener = DB::table('flashes as f')
            ->join('users as u', 'u.id', '=', 'f.user_id')
            ->selectRaw('u.first_name, u.last_name, DATE(f.date) as day')
            ->where('f.activity_type', 'sailing')
            ->whereRaw("strftime('%Y', f.date) = ?", [(string) $year])
            ->orderBy('f.date')
            ->orderBy('f.created_at')
            ->first();

        if ($opener) {
            $facts[] = [
                'title' => 'Season opener',
                'value' => Carbon::parse($opener->day)->format('F j'),
                'detail' => "{$opener->first_name} {$opener->last_name} logged the season's first sailing day",
            ];
        }

        // Longest sailing streak — the most consecutive calendar days a sailor
        // logged a sailing flash; a tie credits everyone. Streak logic is clearer
        // in PHP than SQL.
        $streak = $this->getLongestSailingStreak($year);
        if ($streak && $streak['days'] >= 3) {
            $names = $streak['names'];
            $days = $streak['days'];
            if (count($names) === 1) {
                $detail = "{$names[0]} sailed {$days} days in a row";
            } elseif (count($names) === 2) {
                $detail = "{$names[0]} and {$names[1]} each sailed {$days} days in a row";
            } else {
                $detail = "{$names[0]} and ".(count($names) - 1)." others each sailed {$days} days in a row";
            }
            $facts[] = [
                'title' => 'Longest sailing streak',
                'value' => "{$days} days",
                'detail' => $detail,
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

    /**
     * The longest run of consecutive calendar days a sailor logged a sailing
     * flash, and the names of everyone tied at that length (so a tie credits all
     * of them, not an arbitrary pick). Consecutive-day detection is a simple
     * linear scan in PHP (awkward in portable SQL).
     *
     * @return array{days: int, names: list<string>}|null
     */
    private function getLongestSailingStreak(int $year): ?array
    {
        $rows = DB::table('flashes as f')
            ->join('users as u', 'u.id', '=', 'f.user_id')
            ->selectRaw('f.user_id, u.first_name, u.last_name, DATE(f.date) as day')
            ->where('f.activity_type', 'sailing')
            ->whereRaw("strftime('%Y', f.date) = ?", [(string) $year])
            ->groupBy('f.user_id', 'day')
            ->orderBy('f.user_id')
            ->orderBy('day')
            ->get();

        // Longest consecutive-day run per sailor.
        $perUser = [];
        $currentUser = null;
        $currentLen = 0;
        $prevTs = null;

        foreach ($rows as $row) {
            $ts = strtotime($row->day);
            if ($row->user_id !== $currentUser) {
                $currentUser = $row->user_id;
                $currentLen = 1;
            } elseif ($ts - $prevTs === 86400) {
                $currentLen++;
            } else {
                $currentLen = 1;
            }
            $prevTs = $ts;

            $name = trim("{$row->first_name} {$row->last_name}");
            if (! isset($perUser[$row->user_id]) || $currentLen > $perUser[$row->user_id]['len']) {
                $perUser[$row->user_id] = ['len' => $currentLen, 'name' => $name];
            }
        }

        if ($perUser === []) {
            return null;
        }

        $max = max(array_column($perUser, 'len'));
        $names = array_values(array_map(
            fn ($u) => $u['name'],
            array_filter($perUser, fn ($u) => $u['len'] === $max)
        ));
        usort($names, 'strcasecmp');

        return ['days' => $max, 'names' => $names];
    }
}
