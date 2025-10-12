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
                'name' => 'John Smith',
                'email' => 'john@example.com',
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Sarah Johnson',
                'email' => 'sarah@example.com',
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Mike Williams',
                'email' => 'mike@example.com',
                'password' => Hash::make('password'),
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
        $yachtClubs = ['San Diego Yacht Club', 'Chicago Yacht Club', 'Fishing Bay Yacht Club', 'Lake Norman Yacht Club'];
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

            Flash::create([
                'user_id' => $user->id,
                'date' => $date,
                'activity_type' => $activityType,
                'event_type' => $activityType === 'sailing' ? $eventTypes[array_rand($eventTypes)] : null,
                'yacht_club' => rand(0, 1) ? $yachtClubs[array_rand($yachtClubs)] : null,
                'fleet_number' => rand(0, 1) ? rand(1, 50) : null,
                'location' => rand(0, 1) ? $locations[array_rand($locations)] : null,
                'sail_number' => rand(0, 1) ? rand(10000, 15999) : null,
                'notes' => rand(0, 2) ? null : 'Great day on the water with ' . rand(10, 20) . ' knot winds.',
            ]);
        }
    }
}
