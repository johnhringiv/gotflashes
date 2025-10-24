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
        $leaderboard = $this->getLeaderboard($this->tab, $this->currentYear);

        return view('livewire.leaderboard', [
            'leaderboard' => $leaderboard,
        ]);
    }

    /**
     * Get leaderboard for the specified tab.
     * All three tabs use optimized JOIN-based queries with pre-aggregated flash counts.
     */
    private function getLeaderboard(string $tab, int $year): LengthAwarePaginator
    {
        $yearString = (string) $year;

        // Build the user flash aggregation subquery (shared across all tabs)
        $userFlashesSubquery = DB::table('flashes')
            ->select([
                'user_id',
                DB::raw("SUM(CASE WHEN activity_type = 'sailing' THEN 1 ELSE 0 END) as sailing_count"),
                DB::raw("SUM(CASE WHEN activity_type IN ('maintenance', 'race_committee') THEN 1 ELSE 0 END) as non_sailing_count"),
                DB::raw('MIN(created_at) as first_entry_date'),
            ])
            ->whereRaw("strftime('%Y', date) = ?", [$yearString])
            ->groupBy('user_id');

        return match ($tab) {
            'sailor' => $this->buildSailorQuery($userFlashesSubquery, $year),
            'fleet' => $this->buildGroupedQuery(
                table: 'fleets',
                selectColumns: ['fleets.id', 'fleets.fleet_number', 'fleets.fleet_name'],
                joinColumn: 'members.fleet_id',
                groupByColumns: ['fleets.id', 'fleets.fleet_number', 'fleets.fleet_name'],
                userFlashesSubquery: $userFlashesSubquery,
                year: $year
            ),
            'district' => $this->buildGroupedQuery(
                table: 'districts',
                selectColumns: ['districts.id', 'districts.name'],
                joinColumn: 'members.district_id',
                groupByColumns: ['districts.id', 'districts.name'],
                userFlashesSubquery: $userFlashesSubquery,
                year: $year
            ),
            default => $this->buildSailorQuery($userFlashesSubquery, $year),
        };
    }

    /**
     * Build individual sailor leaderboard query.
     * Uses pre-aggregated flash counts with JOIN (no correlated subqueries).
     */
    private function buildSailorQuery($userFlashesSubquery, int $year): LengthAwarePaginator
    {
        return DB::table('users')
            ->select([
                'users.*',
                'members.district_id',
                'members.fleet_id',
                DB::raw("first_name || ' ' || last_name as name"),  // Computed name accessor
                DB::raw('sailing_count + CASE WHEN non_sailing_count > 5 THEN 5 ELSE non_sailing_count END as flashes_count'),
                'user_flashes.sailing_count',
                'user_flashes.first_entry_date',
            ])
            ->joinSub($userFlashesSubquery, 'user_flashes', 'users.id', '=', 'user_flashes.user_id')
            ->leftJoin('members', function ($join) use ($year) {
                $join->on('users.id', '=', 'members.user_id')
                    ->where('members.year', '=', $year);
            })
            ->orderByDesc('flashes_count')      // Primary: Most total flashes
            ->orderByDesc('sailing_count')      // Tie-breaker 1: Most sailing days
            ->orderBy('first_entry_date')       // Tie-breaker 2: Earliest entry
            ->orderBy('first_name')             // Tie-breaker 3: Alphabetical
            ->orderBy('last_name')
            ->paginate(15);
    }

    /**
     * Build grouped leaderboard query for fleet or district.
     * Aggregates flash counts across multiple users.
     */
    private function buildGroupedQuery(
        string $table,
        array $selectColumns,
        string $joinColumn,
        array $groupByColumns,
        $userFlashesSubquery,
        int $year
    ): LengthAwarePaginator {
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
