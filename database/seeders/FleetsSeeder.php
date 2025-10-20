<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FleetsSeeder extends Seeder
{
    /**
     * Seed the application's database with Lightning Class fleets and districts.
     */
    public function run(): void
    {
        $csvFile = database_path('seeders/data/fleets.csv');

        if (! file_exists($csvFile)) {
            $this->command->error("CSV file not found: {$csvFile}");

            return;
        }

        $rows = array_map('str_getcsv', file($csvFile));
        $header = array_shift($rows); // Remove header row

        $districts = [];
        $fleets = [];

        foreach ($rows as $row) {
            if (count($row) < 3) {
                continue;
            }

            [$district, $fleetNumber, $fleetName] = $row;

            // Collect unique districts
            if (! in_array($district, $districts)) {
                $districts[] = $district;
            }

            // Collect fleet data
            $fleets[] = [
                'district' => $district,
                'fleet_number' => (int) $fleetNumber,
                'fleet_name' => $fleetName,
            ];
        }

        // Sort districts alphabetically
        sort($districts);

        $this->command->info('Found '.count($districts).' districts and '.count($fleets).' fleets');
        $this->command->info('Districts: '.implode(', ', $districts));
        $this->command->info('Fleet numbers range: '.min(array_column($fleets, 'fleet_number')).' - '.max(array_column($fleets, 'fleet_number')));

        // Clear existing data
        DB::table('fleets')->delete();
        DB::table('districts')->delete();

        // Insert districts
        $districtIds = [];
        foreach ($districts as $district) {
            $id = DB::table('districts')->insertGetId([
                'name' => $district,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $districtIds[$district] = $id;
        }

        $this->command->info('Inserted '.count($districtIds).' districts');

        // Insert fleets
        $fleetsInserted = 0;
        foreach ($fleets as $fleet) {
            DB::table('fleets')->insert([
                'district_id' => $districtIds[$fleet['district']],
                'fleet_number' => $fleet['fleet_number'],
                'fleet_name' => $fleet['fleet_name'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $fleetsInserted++;
        }

        $this->command->info('Inserted '.$fleetsInserted.' fleets');
        $this->command->info('Seeding completed successfully!');
    }
}
