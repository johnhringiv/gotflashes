<?php

namespace Database\Factories;

use App\Models\District;
use App\Models\Fleet;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Member>
 */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'district_id' => District::factory(),
            'fleet_id' => Fleet::factory(),
            'year' => now()->year,
        ];
    }
}
