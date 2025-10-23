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
     */
    private function getFleetLeaderboard(int $year): LengthAwarePaginator
    {
        $yearString = (string) $year;

        // Join members table to get year-end fleet affiliations
        return DB::table('fleets')
            ->select([
                'fleets.id',
                'fleets.fleet_number',
                'fleets.fleet_name',
                DB::raw('COUNT(DISTINCT members.user_id) as member_count'),
                DB::raw("SUM(
                    (SELECT count(*) FROM flashes
                     WHERE members.user_id = flashes.user_id
                     AND strftime('%Y', date) = ?
                     AND activity_type = 'sailing')
                    +
                    MIN(
                        (SELECT count(*) FROM flashes
                         WHERE members.user_id = flashes.user_id
                         AND strftime('%Y', date) = ?
                         AND activity_type IN ('maintenance', 'race_committee')),
                        5
                    )
                ) as total_flashes"),
                DB::raw("SUM(
                    (SELECT count(*) FROM flashes
                     WHERE members.user_id = flashes.user_id
                     AND strftime('%Y', date) = ?
                     AND activity_type = 'sailing')
                ) as total_sailing"),
                DB::raw("MIN(
                    (SELECT MIN(created_at) FROM flashes
                     WHERE members.user_id = flashes.user_id
                     AND strftime('%Y', date) = ?)
                ) as first_entry_date"),
            ])
            ->join('members', 'fleets.id', '=', 'members.fleet_id')
            ->where('members.year', $year)
            ->addBinding([$yearString, $yearString, $yearString, $yearString], 'select')
            ->whereExists(function ($query) use ($yearString) {
                $query->from('flashes')
                    ->whereColumn('members.user_id', 'flashes.user_id')
                    ->whereRaw("strftime('%Y', date) = ?", [$yearString]);
            })
            ->groupBy('fleets.id', 'fleets.fleet_number', 'fleets.fleet_name')
            ->orderByDesc('total_flashes')
            ->orderByDesc('total_sailing')
            ->orderBy('first_entry_date')
            ->paginate(15);
    }

    /**
     * Get district leaderboard (grouped by district).
     * Uses members table to determine year-end district affiliations.
     */
    private function getDistrictLeaderboard(int $year): LengthAwarePaginator
    {
        $yearString = (string) $year;

        // Join members table to get year-end district affiliations
        return DB::table('districts')
            ->select([
                'districts.id',
                'districts.name',
                DB::raw('COUNT(DISTINCT members.user_id) as member_count'),
                DB::raw("SUM(
                    (SELECT count(*) FROM flashes
                     WHERE members.user_id = flashes.user_id
                     AND strftime('%Y', date) = ?
                     AND activity_type = 'sailing')
                    +
                    MIN(
                        (SELECT count(*) FROM flashes
                         WHERE members.user_id = flashes.user_id
                         AND strftime('%Y', date) = ?
                         AND activity_type IN ('maintenance', 'race_committee')),
                        5
                    )
                ) as total_flashes"),
                DB::raw("SUM(
                    (SELECT count(*) FROM flashes
                     WHERE members.user_id = flashes.user_id
                     AND strftime('%Y', date) = ?
                     AND activity_type = 'sailing')
                ) as total_sailing"),
                DB::raw("MIN(
                    (SELECT MIN(created_at) FROM flashes
                     WHERE members.user_id = flashes.user_id
                     AND strftime('%Y', date) = ?)
                ) as first_entry_date"),
            ])
            ->join('members', 'districts.id', '=', 'members.district_id')
            ->where('members.year', $year)
            ->addBinding([$yearString, $yearString, $yearString, $yearString], 'select')
            ->whereExists(function ($query) use ($yearString) {
                $query->from('flashes')
                    ->whereColumn('members.user_id', 'flashes.user_id')
                    ->whereRaw("strftime('%Y', date) = ?", [$yearString]);
            })
            ->groupBy('districts.id', 'districts.name')
            ->orderByDesc('total_flashes')
            ->orderByDesc('total_sailing')
            ->orderBy('first_entry_date')
            ->paginate(15);
    }
}
