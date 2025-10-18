<?php

namespace App\Http\Controllers;

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
     */
    private function getSailorLeaderboard(int $year)
    {
        return User::select('users.*')
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
     * Get fleet leaderboard (grouped by fleet_number).
     */
    private function getFleetLeaderboard(int $year)
    {
        $yearString = (string) $year;

        // Use SQL GROUP BY for efficient aggregation in the database
        return DB::table('users')
            ->select([
                'fleet_number',
                DB::raw('COUNT(DISTINCT users.id) as member_count'),
                DB::raw("SUM(
                    (SELECT count(*) FROM flashes
                     WHERE users.id = flashes.user_id
                     AND strftime('%Y', date) = ?
                     AND activity_type = 'sailing')
                    +
                    MIN(
                        (SELECT count(*) FROM flashes
                         WHERE users.id = flashes.user_id
                         AND strftime('%Y', date) = ?
                         AND activity_type IN ('maintenance', 'race_committee')),
                        5
                    )
                ) as total_flashes"),
                DB::raw("SUM(
                    (SELECT count(*) FROM flashes
                     WHERE users.id = flashes.user_id
                     AND strftime('%Y', date) = ?
                     AND activity_type = 'sailing')
                ) as total_sailing"),
                DB::raw("MIN(
                    (SELECT MIN(created_at) FROM flashes
                     WHERE users.id = flashes.user_id
                     AND strftime('%Y', date) = ?)
                ) as first_entry_date"),
            ])
            ->addBinding([$yearString, $yearString, $yearString, $yearString], 'select')
            ->whereExists(function ($query) use ($yearString) {
                $query->from('flashes')
                    ->whereColumn('users.id', 'flashes.user_id')
                    ->whereRaw("strftime('%Y', date) = ?", [$yearString]);
            })
            ->whereNotNull('fleet_number')
            ->groupBy('fleet_number')
            ->orderByDesc('total_flashes')
            ->orderByDesc('total_sailing')
            ->orderBy('first_entry_date')
            ->paginate(15);
    }

    /**
     * Get district leaderboard (grouped by district).
     */
    private function getDistrictLeaderboard(int $year)
    {
        $yearString = (string) $year;

        // Use SQL GROUP BY for efficient aggregation in the database
        return DB::table('users')
            ->select([
                'district',
                DB::raw('COUNT(DISTINCT users.id) as member_count'),
                DB::raw("SUM(
                    (SELECT count(*) FROM flashes
                     WHERE users.id = flashes.user_id
                     AND strftime('%Y', date) = ?
                     AND activity_type = 'sailing')
                    +
                    MIN(
                        (SELECT count(*) FROM flashes
                         WHERE users.id = flashes.user_id
                         AND strftime('%Y', date) = ?
                         AND activity_type IN ('maintenance', 'race_committee')),
                        5
                    )
                ) as total_flashes"),
                DB::raw("SUM(
                    (SELECT count(*) FROM flashes
                     WHERE users.id = flashes.user_id
                     AND strftime('%Y', date) = ?
                     AND activity_type = 'sailing')
                ) as total_sailing"),
                DB::raw("MIN(
                    (SELECT MIN(created_at) FROM flashes
                     WHERE users.id = flashes.user_id
                     AND strftime('%Y', date) = ?)
                ) as first_entry_date"),
            ])
            ->addBinding([$yearString, $yearString, $yearString, $yearString], 'select')
            ->whereExists(function ($query) use ($yearString) {
                $query->from('flashes')
                    ->whereColumn('users.id', 'flashes.user_id')
                    ->whereRaw("strftime('%Y', date) = ?", [$yearString]);
            })
            ->whereNotNull('district')
            ->groupBy('district')
            ->orderByDesc('total_flashes')
            ->orderByDesc('total_sailing')
            ->orderBy('first_entry_date')
            ->paginate(15);
    }
}
