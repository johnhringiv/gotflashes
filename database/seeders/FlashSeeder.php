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
        // Get some existing districts and fleets (assumes FleetsSeeder has run)
        $californiaDistrict = District::where('name', 'California')->first();
        $centralAtlanticDistrict = District::where('name', 'Central Atlantic')->first();
        $canadaDistrict = District::where('name', 'Central Canada')->first();

        // Get specific fleets
        $fleet194 = Fleet::where('fleet_number', 194)->first(); // Mission Bay (California)
        $fleet1 = Fleet::where('fleet_number', 1)->first(); // Annapolis area
        $canadaFleet = $canadaDistrict?->fleets()->first();

        // Create 3 test users with specific details
        $john = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Smith',
            'email' => 'john@example.com',
            'city' => 'San Diego',
            'state' => 'CA',
            'yacht_club' => 'San Diego Yacht Club',
        ]);

        // Create membership for John in California / Fleet 194
        if ($californiaDistrict && $fleet194) {
            Member::create([
                'user_id' => $john->id,
                'district_id' => $californiaDistrict->id,
                'fleet_id' => $fleet194->id,
                'year' => now()->year,
            ]);
        }

        $sarah = User::factory()->create([
            'first_name' => 'Sarah',
            'last_name' => 'Johnson',
            'email' => 'sarah@example.com',
            'city' => 'Chicago',
            'state' => 'IL',
            'yacht_club' => 'Chicago Yacht Club',
        ]);

        // Create membership for Sarah in Central Atlantic / Fleet 1
        if ($centralAtlanticDistrict && $fleet1) {
            Member::create([
                'user_id' => $sarah->id,
                'district_id' => $centralAtlanticDistrict->id,
                'fleet_id' => $fleet1->id,
                'year' => now()->year,
            ]);
        }

        $mike = User::factory()->create([
            'first_name' => 'Mike',
            'last_name' => 'Williams',
            'email' => 'mike@example.com',
            'city' => 'Norfolk',
            'state' => 'VA',
            'yacht_club' => 'Fishing Bay Yacht Club',
        ]);

        // Create membership for Mike as unaffiliated
        Member::create([
            'user_id' => $mike->id,
            'district_id' => null,
            'fleet_id' => null,
            'year' => now()->year,
        ]);

        // Create flashes for each user
        $this->createFlashesForUser($john);
        $this->createFlashesForUser($sarah);
        $this->createFlashesForUser($mike);
    }

    private function createFlashesForUser(User $user): void
    {
        $usedDates = [];

        // Create 12-15 sailing activities
        $sailingCount = rand(12, 15);
        for ($i = 0; $i < $sailingCount; $i++) {
            do {
                $date = fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
            } while (in_array($date, $usedDates));

            $usedDates[] = $date;

            Flash::factory()
                ->forUser($user)
                ->onDate($date)
                ->create();
        }

        // Create 1-2 maintenance activities
        $maintenanceCount = rand(1, 2);
        for ($i = 0; $i < $maintenanceCount; $i++) {
            do {
                $date = fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
            } while (in_array($date, $usedDates));

            $usedDates[] = $date;

            Flash::factory()
                ->forUser($user)
                ->onDate($date)
                ->maintenance()
                ->create();
        }

        // Create 1-2 race committee activities
        $raceCommitteeCount = rand(1, 2);
        for ($i = 0; $i < $raceCommitteeCount; $i++) {
            do {
                $date = fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
            } while (in_array($date, $usedDates));

            $usedDates[] = $date;

            Flash::factory()
                ->forUser($user)
                ->onDate($date)
                ->raceCommittee()
                ->create();
        }
    }
}
