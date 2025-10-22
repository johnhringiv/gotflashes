<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Fleet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LeaderboardController extends Controller
{
    /**
     * Display the leaderboard for the specified year.
     */
    public function index(Request $request): View
    {
        $tab = $request->get('tab', 'sailor');
        $year = $request->get('year', now()->year);

        // Validate tab parameter
        if (! in_array($tab, ['sailor', 'fleet', 'district'])) {
            $tab = 'sailor';
        }

        if ($tab === 'sailor') {
            $leaderboard = $this->getSailorLeaderboard($year);
        } elseif ($tab === 'fleet') {
            $leaderboard = $this->getFleetLeaderboard($year);
        } else {
            $leaderboard = $this->getDistrictLeaderboard($year);
        }

        return view('leaderboard.index', [
            'leaderboard' => $leaderboard,
            'currentTab' => $tab,
            'currentYear' => $year,
        ]);
    }

    /**
     * Get individual sailor leaderboard.
     * Uses members table to get year-end district/fleet affiliations.
     */
    private function getSailorLeaderboard(int $year)
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
    private function getFleetLeaderboard(int $year)
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
    private function getDistrictLeaderboard(int $year)
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
