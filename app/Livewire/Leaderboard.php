<?php

namespace App\Livewire;

use App\Models\District;
use App\Models\Fleet;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Leaderboard extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = 'sailor';

    public int $currentYear;

    public function mount()
    {
        // Validate tab from URL
        if (! in_array($this->tab, ['sailor', 'fleet', 'district'])) {
            $this->tab = 'sailor';
        }

        $this->currentYear = now()->year;
    }

    public function switchTab(string $tab): void
    {
        if (! in_array($tab, ['sailor', 'fleet', 'district'])) {
            return;
        }

        $this->tab = $tab;
        $this->resetPage(); // Reset pagination when switching tabs
    }

    public function updatedTab(): void
    {
        $this->resetPage(); // Reset pagination when tab changes via URL
    }

    public function render()
    {
        $leaderboard = match ($this->tab) {
            'sailor' => $this->getSailorLeaderboard($this->currentYear),
            'fleet' => $this->getFleetLeaderboard($this->currentYear),
            'district' => $this->getDistrictLeaderboard($this->currentYear),
            default => $this->getSailorLeaderboard($this->currentYear),
        };

        return view('livewire.leaderboard', [
            'leaderboard' => $leaderboard,
        ]);
    }

    /**
     * Get individual sailor leaderboard.
     * Uses members table to get year-end district/fleet affiliations.
     */
    private function getSailorLeaderboard(int $year): LengthAwarePaginator
    {
        return User::select('users.*', 'members.district_id', 'members.fleet_id')
            ->leftJoin('members', function ($join) use ($year) {
                $join->on('users.id', '=', 'members.user_id')
                    ->where('members.year', '=', $year);
            })
            ->withFlashesCount($year)
            ->whereExists(function ($query) use ($year) {
                $query->from('flashes')
                    ->whereColumn('users.id', 'flashes.user_id')
                    ->whereYear('date', $year);
            })
            ->orderByDesc('flashes_count')      // Primary: Most total flashes
            ->orderByDesc('sailing_count')      // Tie-breaker 1: Most sailing days
            ->orderBy('first_entry_date')       // Tie-breaker 2: Earliest entry
            ->orderBy('first_name')             // Tie-breaker 3: Alphabetical
            ->orderBy('last_name')
            ->paginate(15);
    }

    /**
     * Get fleet leaderboard (grouped by fleet).
     * Uses members table to determine year-end fleet affiliations.
     * Optimized with JOIN and pre-aggregation instead of correlated subqueries.
     */
    private function getFleetLeaderboard(int $year): LengthAwarePaginator
    {
        return $this->getAggregatedLeaderboard(
            table: 'fleets',
            selectColumns: ['fleets.id', 'fleets.fleet_number', 'fleets.fleet_name'],
            joinColumn: 'members.fleet_id',
            groupByColumns: ['fleets.id', 'fleets.fleet_number', 'fleets.fleet_name'],
            year: $year
        );
    }

    /**
     * Get district leaderboard (grouped by district).
     * Uses members table to determine year-end district affiliations.
     * Optimized with JOIN and pre-aggregation instead of correlated subqueries.
     */
    private function getDistrictLeaderboard(int $year): LengthAwarePaginator
    {
        return $this->getAggregatedLeaderboard(
            table: 'districts',
            selectColumns: ['districts.id', 'districts.name'],
            joinColumn: 'members.district_id',
            groupByColumns: ['districts.id', 'districts.name'],
            year: $year
        );
    }

    /**
     * Generic aggregated leaderboard query builder.
     * Handles both fleet and district leaderboards with pre-aggregated flash counts.
     *
     * @param  string  $table  The main table to query (fleets or districts)
     * @param  array  $selectColumns  Base columns to select (id, name/number fields)
     * @param  string  $joinColumn  The members table column to join on
     * @param  array  $groupByColumns  Columns to group by
     * @param  int  $year  The year to filter by
     */
    private function getAggregatedLeaderboard(
        string $table,
        array $selectColumns,
        string $joinColumn,
        array $groupByColumns,
        int $year
    ): LengthAwarePaginator {
        $yearString = (string) $year;

        // Build the user flash aggregation subquery once (shared across all aggregations)
        $userFlashesSubquery = DB::table('flashes')
            ->select([
                'user_id',
                DB::raw("SUM(CASE WHEN activity_type = 'sailing' THEN 1 ELSE 0 END) as sailing_count"),
                DB::raw("SUM(CASE WHEN activity_type IN ('maintenance', 'race_committee') THEN 1 ELSE 0 END) as non_sailing_count"),
                DB::raw('MIN(created_at) as first_entry_date'),
            ])
            ->whereRaw("strftime('%Y', date) = ?", [$yearString])
            ->groupBy('user_id');

        // Build aggregated counts (same logic for fleet and district)
        $aggregatedColumns = [
            DB::raw('COUNT(DISTINCT members.user_id) as member_count'),
            // Total flashes: sailing (unlimited) + MIN(non-sailing, 5) per user
            DB::raw('SUM(
                sailing_count + CASE WHEN non_sailing_count > 5 THEN 5 ELSE non_sailing_count END
            ) as total_flashes'),
            DB::raw('SUM(sailing_count) as total_sailing'),
            DB::raw('MIN(first_entry_date) as first_entry_date'),
        ];

        return DB::table($table)
            ->select(array_merge($selectColumns, $aggregatedColumns))
            ->join('members', "{$table}.id", '=', $joinColumn)
            ->joinSub($userFlashesSubquery, 'user_flashes', 'members.user_id', '=', 'user_flashes.user_id')
            ->where('members.year', $year)
            ->groupBy($groupByColumns)
            ->orderByDesc('total_flashes')
            ->orderByDesc('total_sailing')
            ->orderBy('first_entry_date')
            ->paginate(15);
    }
}
