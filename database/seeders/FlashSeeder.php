<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\Flash;
use App\Models\Fleet;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Seeder;

class FlashSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all districts and fleets for random assignment
        $districts = District::all();
        $fleets = Fleet::all();
        $currentYear = now()->year;

        // Generate 50 users with varying activity levels
        for ($i = 1; $i <= 50; $i++) {
            $user = User::factory()->create();

            // Randomly assign district/fleet membership
            // 70% affiliated, 30% unaffiliated
            if (rand(1, 100) <= 70 && $districts->isNotEmpty()) {
                $district = $districts->random();
                $districtFleets = $fleets->where('district_id', $district->id);

                // 80% chance of having a fleet if district has fleets
                $fleet = null;
                if ($districtFleets->isNotEmpty() && rand(1, 100) <= 80) {
                    $fleet = $districtFleets->random();
                }

                Member::create([
                    'user_id' => $user->id,
                    'district_id' => $district->id,
                    'fleet_id' => $fleet?->id,
                    'year' => $currentYear,
                ]);
            } else {
                // Unaffiliated user
                Member::create([
                    'user_id' => $user->id,
                    'district_id' => null,
                    'fleet_id' => null,
                    'year' => $currentYear,
                ]);
            }

            // Generate activity with varying levels (0-60 sailing, 0-10 non-sailing)
            $sailingDays = $this->generateWeightedActivityCount(0, 60);
            $nonSailingDays = rand(0, 10);

            $this->createFlashesForUser($user, $sailingDays, $nonSailingDays);
        }
    }

    /**
     * Generate weighted activity count favoring realistic sailing patterns
     * More users with 0-10 days, moderate at 10-25, fewer at 25-50, rare above 50
     */
    private function generateWeightedActivityCount(int $min, int $max): int
    {
        $rand = rand(1, 100);

        if ($rand <= 20) {
            // 20% chance: 0 days (inactive/new users)
            return 0;
        } elseif ($rand <= 50) {
            // 30% chance: 1-10 days (casual sailors)
            return rand(1, 10);
        } elseif ($rand <= 75) {
            // 25% chance: 11-25 days (regular sailors, bronze/silver tier)
            return rand(11, 25);
        } elseif ($rand <= 90) {
            // 15% chance: 26-40 days (active sailors, approaching/achieving gold)
            return rand(26, 40);
        } elseif ($rand <= 97) {
            // 7% chance: 41-50 days (very active sailors, gold tier)
            return rand(41, 50);
        } else {
            // 3% chance: 51-60 days (extremely active sailors)
            return rand(51, min(60, $max));
        }
    }

    /**
     * Create flashes (activities) for a user
     */
    private function createFlashesForUser(User $user, int $sailingDays, int $nonSailingDays): void
    {
        $usedDates = [];
        $currentYear = now()->year;

        // Create sailing activities (only current year for leaderboard relevance)
        for ($i = 0; $i < $sailingDays; $i++) {
            $attempts = 0;
            do {
                $date = fake()->dateTimeBetween("$currentYear-01-01", 'now')->format('Y-m-d');
                $attempts++;

                // Prevent infinite loops if user requests more days than available in year
                if ($attempts > 100) {
                    break;
                }
            } while (in_array($date, $usedDates));

            if ($attempts <= 100) {
                $usedDates[] = $date;

                Flash::factory()
                    ->forUser($user)
                    ->onDate($date)
                    ->create();
            }
        }

        // Split non-sailing days between maintenance and race committee
        $maintenanceCount = (int) floor($nonSailingDays / 2);
        $raceCommitteeCount = $nonSailingDays - $maintenanceCount;

        // Create maintenance activities
        for ($i = 0; $i < $maintenanceCount; $i++) {
            $attempts = 0;
            do {
                $date = fake()->dateTimeBetween("$currentYear-01-01", 'now')->format('Y-m-d');
                $attempts++;

                if ($attempts > 100) {
                    break;
                }
            } while (in_array($date, $usedDates));

            if ($attempts <= 100) {
                $usedDates[] = $date;

                Flash::factory()
                    ->forUser($user)
                    ->onDate($date)
                    ->maintenance()
                    ->create();
            }
        }

        // Create race committee activities
        for ($i = 0; $i < $raceCommitteeCount; $i++) {
            $attempts = 0;
            do {
                $date = fake()->dateTimeBetween("$currentYear-01-01", 'now')->format('Y-m-d');
                $attempts++;

                if ($attempts > 100) {
                    break;
                }
            } while (in_array($date, $usedDates));

            if ($attempts <= 100) {
                $usedDates[] = $date;

                Flash::factory()
                    ->forUser($user)
                    ->onDate($date)
                    ->raceCommittee()
                    ->create();
            }
        }
    }
}
