<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Note: Districts and fleets are seeded automatically by the migration
        // This seeder only runs FlashSeeder for local development test data
        $this->call([
            FlashSeeder::class,
        ]);
    }
}
