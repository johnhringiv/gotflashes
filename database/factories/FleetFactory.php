<?php

namespace Database\Factories;

use App\Models\District;
use App\Models\Fleet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Fleet>
 */
class FleetFactory extends Factory
{
    protected $model = Fleet::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'district_id' => District::factory(),
            'fleet_number' => fake()->numberBetween(1, 999),
            'fleet_name' => fake()->city().' Fleet',
        ];
    }
}
