<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Flash>
 */
class FlashFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'activity_type' => 'sailing',
            'event_type' => fake()->randomElement(['regatta', 'club_race', 'practice', 'leisure']),
            'location' => fake()->optional()->city(),
            'sail_number' => fake()->optional()->numberBetween(1, 17000),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the flash is for maintenance activity.
     */
    public function maintenance(): static
    {
        return $this->state(fn (array $attributes) => [
            'activity_type' => 'maintenance',
            'event_type' => null,
        ]);
    }

    /**
     * Indicate that the flash is for race committee activity.
     */
    public function raceCommittee(): static
    {
        return $this->state(fn (array $attributes) => [
            'activity_type' => 'race_committee',
            'event_type' => null,
        ]);
    }

    /**
     * Indicate that the flash is for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Indicate that the flash is for a specific date.
     */
    public function onDate(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => $date,
        ]);
    }
}
