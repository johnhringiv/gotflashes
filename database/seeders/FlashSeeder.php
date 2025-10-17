<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Flash;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FlashSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test users
        $users = [
            [
                'first_name' => 'John',
                'last_name' => 'Smith',
                'email' => 'john@example.com',
                'password' => Hash::make('password'),
                'date_of_birth' => '1985-06-15',
                'gender' => 'male',
                'address_line1' => '123 Harbor St',
                'city' => 'San Diego',
                'state' => 'CA',
                'zip_code' => '92101',
                'country' => 'United States',
                'district' => 'District 11',
                'fleet_number' => 50,
                'yacht_club' => 'San Diego Yacht Club',
            ],
            [
                'first_name' => 'Sarah',
                'last_name' => 'Johnson',
                'email' => 'sarah@example.com',
                'password' => Hash::make('password'),
                'date_of_birth' => '1992-03-22',
                'gender' => 'female',
                'address_line1' => '456 Lake Dr',
                'city' => 'Chicago',
                'state' => 'IL',
                'zip_code' => '60601',
                'country' => 'United States',
                'district' => 'District 8',
                'fleet_number' => 123,
                'yacht_club' => 'Chicago Yacht Club',
            ],
            [
                'first_name' => 'Mike',
                'last_name' => 'Williams',
                'email' => 'mike@example.com',
                'password' => Hash::make('password'),
                'date_of_birth' => '1978-11-08',
                'gender' => 'male',
                'address_line1' => '789 Marina Blvd',
                'city' => 'Norfolk',
                'state' => 'VA',
                'zip_code' => '23510',
                'country' => 'United States',
                'district' => 'District 4',
                'fleet_number' => 67,
                'yacht_club' => 'Fishing Bay Yacht Club',
            ],
        ];

        foreach ($users as $userData) {
            $user = User::create($userData);

            // Create various flash activities for each user
            $this->createFlashesForUser($user);
        }
    }

    private function createFlashesForUser(User $user): void
    {
        $activityTypes = ['sailing', 'sailing', 'sailing', 'sailing', 'maintenance', 'race_committee'];
        $eventTypes = ['regatta', 'club_race', 'practice', 'leisure'];
        $locations = ['Lake Norman, NC', 'San Diego Bay, CA', 'Lake Michigan, IL', 'Fishing Bay, VA'];

        // Create 15-20 activities for current year
        $numActivities = rand(15, 20);
        $dates = [];

        for ($i = 0; $i < $numActivities; $i++) {
            // Generate random date in current year
            do {
                $date = now()->startOfYear()->addDays(rand(0, 300));
            } while (in_array($date->format('Y-m-d'), $dates));

            $dates[] = $date->format('Y-m-d');

            $activityType = $activityTypes[array_rand($activityTypes)];
            $isSailing = $activityType === 'sailing';

            Flash::create([
                'user_id' => $user->id,
                'date' => $date,
                'activity_type' => $activityType,
                'event_type' => $isSailing ? $eventTypes[array_rand($eventTypes)] : null,
                'location' => rand(0, 1) ? $locations[array_rand($locations)] : null,
                'sail_number' => rand(0, 1) ? rand(10000, 15999) : null,
                'notes' => rand(0, 2) ? null : 'Great day on the water with ' . rand(10, 20) . ' knot winds.',
            ]);
        }
    }
}
