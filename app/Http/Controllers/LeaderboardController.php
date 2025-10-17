<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaderboardController extends Controller
{
    /**
     * Display the leaderboard for 2025.
     */
    public function index(Request $request): View
    {
        $tab = $request->get('tab', 'sailor');

        // Validate tab parameter
        if (! in_array($tab, ['sailor', 'fleet', 'district'])) {
            $tab = 'sailor';
        }

        if ($tab === 'sailor') {
            $leaderboard = $this->getSailorLeaderboard();
        } elseif ($tab === 'fleet') {
            $leaderboard = $this->getFleetLeaderboard();
        } else {
            $leaderboard = $this->getDistrictLeaderboard();
        }

        return view('leaderboard.index', [
            'leaderboard' => $leaderboard,
            'currentTab' => $tab,
        ]);
    }

    /**
     * Get individual sailor leaderboard.
     */
    private function getSailorLeaderboard()
    {
        return User::select('users.*')
            ->selectSub(function ($query) {
                // Calculate total: sailing days (unlimited) + non-sailing days (capped at 5)
                // Non-sailing days = maintenance + race_committee activities
                // Uses MIN() to cap non-sailing days at maximum of 5 per year
                $query->selectRaw('
                    (SELECT count(*) FROM flashes
                     WHERE users.id = flashes.user_id
                     AND strftime(\'%Y\', date) = \'2025\'
                     AND activity_type = \'sailing\')
                    +
                    MIN(
                        (SELECT count(*) FROM flashes
                         WHERE users.id = flashes.user_id
                         AND strftime(\'%Y\', date) = \'2025\'
                         AND activity_type IN (\'maintenance\', \'race_committee\')),
                        5
                    )
                ');
            }, 'flashes_2025_count')
            ->whereExists(function ($query) {
                $query->from('flashes')
                    ->whereColumn('users.id', 'flashes.user_id')
                    ->whereYear('date', 2025);
            })
            ->orderByDesc('flashes_2025_count')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate(15);
    }

    /**
     * Get fleet leaderboard (grouped by fleet_number).
     */
    private function getFleetLeaderboard()
    {
        return User::select('fleet_number')
            ->selectRaw('COUNT(DISTINCT users.id) as member_count')
            ->selectSub(function ($query) {
                $query->selectRaw('
                    SUM(
                        (SELECT count(*) FROM flashes
                         WHERE users.id = flashes.user_id
                         AND strftime(\'%Y\', date) = \'2025\'
                         AND activity_type = \'sailing\')
                        +
                        MIN(
                            (SELECT count(*) FROM flashes
                             WHERE users.id = flashes.user_id
                             AND strftime(\'%Y\', date) = \'2025\'
                             AND activity_type IN (\'maintenance\', \'race_committee\')),
                            5
                        )
                    )
                ');
            }, 'total_flashes')
            ->whereExists(function ($query) {
                $query->from('flashes')
                    ->whereColumn('users.id', 'flashes.user_id')
                    ->whereYear('date', 2025);
            })
            ->whereNotNull('fleet_number')
            ->groupBy('fleet_number')
            ->orderByDesc('total_flashes')
            ->orderBy('fleet_number')
            ->paginate(15);
    }

    /**
     * Get district leaderboard (grouped by district).
     */
    private function getDistrictLeaderboard()
    {
        return User::select('district')
            ->selectRaw('COUNT(DISTINCT users.id) as member_count')
            ->selectSub(function ($query) {
                $query->selectRaw('
                    SUM(
                        (SELECT count(*) FROM flashes
                         WHERE users.id = flashes.user_id
                         AND strftime(\'%Y\', date) = \'2025\'
                         AND activity_type = \'sailing\')
                        +
                        MIN(
                            (SELECT count(*) FROM flashes
                             WHERE users.id = flashes.user_id
                             AND strftime(\'%Y\', date) = \'2025\'
                             AND activity_type IN (\'maintenance\', \'race_committee\')),
                            5
                        )
                    )
                ');
            }, 'total_flashes')
            ->whereExists(function ($query) {
                $query->from('flashes')
                    ->whereColumn('users.id', 'flashes.user_id')
                    ->whereYear('date', 2025);
            })
            ->whereNotNull('district')
            ->groupBy('district')
            ->orderByDesc('total_flashes')
            ->orderBy('district')
            ->paginate(15);
    }
}
