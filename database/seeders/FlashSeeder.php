<?php

namespace Database\Seeders;

use App\Models\Flash;
use App\Models\User;
use Illuminate\Database\Seeder;

class FlashSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create 3 test users with specific details
        $john = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Smith',
            'email' => 'john@example.com',
            'city' => 'San Diego',
            'state' => 'CA',
            'district' => '11',
            'fleet_number' => 50,
            'yacht_club' => 'San Diego Yacht Club',
        ]);

        $sarah = User::factory()->create([
            'first_name' => 'Sarah',
            'last_name' => 'Johnson',
            'email' => 'sarah@example.com',
            'city' => 'Chicago',
            'state' => 'IL',
            'district' => '8',
            'fleet_number' => 123,
            'yacht_club' => 'Chicago Yacht Club',
        ]);

        $mike = User::factory()->create([
            'first_name' => 'Mike',
            'last_name' => 'Williams',
            'email' => 'mike@example.com',
            'city' => 'Norfolk',
            'state' => 'VA',
            'district' => '4',
            'fleet_number' => 67,
            'yacht_club' => 'Fishing Bay Yacht Club',
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
