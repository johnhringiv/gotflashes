<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_date_is_required(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'activity_type' => 'sailing',
            'event_type' => 'practice',
        ]);

        $response->assertSessionHasErrors('dates');
    }

    public function test_activity_type_is_required(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => ['2025-01-01'],
        ]);

        $response->assertSessionHasErrors('activity_type');
    }

    public function test_activity_type_must_be_valid(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => ['2025-01-01'],
            'activity_type' => 'invalid_type',
        ]);

        $response->assertSessionHasErrors('activity_type');
    }

    public function test_sailing_activity_requires_event_type(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => ['2025-01-01'],
            'activity_type' => 'sailing',
        ]);

        $response->assertSessionHasErrors('event_type');
    }

    public function test_event_type_must_be_valid_when_provided(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => ['2025-01-01'],
            'activity_type' => 'sailing',
            'event_type' => 'invalid_event',
        ]);

        $response->assertSessionHasErrors('event_type');
    }

    public function test_non_sailing_activity_cannot_have_event_type(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => ['2025-01-01'],
            'activity_type' => 'maintenance',
            'event_type' => 'regatta',
        ]);

        $response->assertSessionHasErrors('event_type');
    }

    public function test_date_cannot_be_in_future(): void
    {
        $user = User::factory()->create();

        $futureDate = now()->addDays(2)->format('Y-m-d');

        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => [$futureDate],
            'activity_type' => 'sailing',
            'event_type' => 'practice',
        ]);

        $response->assertSessionHasErrors('dates.0');
    }

    public function test_date_can_be_today(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => [now()->format('Y-m-d')],
            'activity_type' => 'sailing',
            'event_type' => 'practice',
        ]);

        $response->assertRedirect(route('flashes.index'));
        $response->assertSessionHasNoErrors();
    }

    public function test_date_can_be_tomorrow_for_timezone_tolerance(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => [now()->addDay()->format('Y-m-d')],
            'activity_type' => 'sailing',
            'event_type' => 'practice',
        ]);

        $response->assertRedirect(route('flashes.index'));
        $response->assertSessionHasNoErrors();
    }

    public function test_location_is_optional(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => ['2025-01-01'],
            'activity_type' => 'sailing',
            'event_type' => 'practice',
        ]);

        $response->assertRedirect(route('flashes.index'));
        $response->assertSessionHasNoErrors();
    }

    public function test_location_can_be_provided(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => ['2025-01-01'],
            'activity_type' => 'sailing',
            'event_type' => 'practice',
            'location' => 'Lake Example',
        ]);

        $response->assertRedirect(route('flashes.index'));
        $this->assertDatabaseHas('flashes', [
            'location' => 'Lake Example',
        ]);
    }

    public function test_sail_number_is_optional(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => ['2025-01-01'],
            'activity_type' => 'sailing',
            'event_type' => 'practice',
        ]);

        $response->assertRedirect(route('flashes.index'));
        $response->assertSessionHasNoErrors();
    }

    public function test_sail_number_must_be_integer(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => ['2025-01-01'],
            'activity_type' => 'sailing',
            'event_type' => 'practice',
            'sail_number' => 'not-a-number',
        ]);

        $response->assertSessionHasErrors('sail_number');
    }

    public function test_notes_is_optional(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => ['2025-01-01'],
            'activity_type' => 'sailing',
            'event_type' => 'practice',
        ]);

        $response->assertRedirect(route('flashes.index'));
        $response->assertSessionHasNoErrors();
    }

    public function test_notes_can_be_provided(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => ['2025-01-01'],
            'activity_type' => 'sailing',
            'event_type' => 'practice',
            'notes' => 'Great day on the water!',
        ]);

        $response->assertRedirect(route('flashes.index'));
        $this->assertDatabaseHas('flashes', [
            'notes' => 'Great day on the water!',
        ]);
    }

    public function test_valid_event_types_are_accepted(): void
    {
        $user = User::factory()->create();

        $validTypes = ['regatta', 'club_race', 'practice', 'leisure'];

        foreach ($validTypes as $index => $type) {
            $response = $this->actingAs($user)->post(route('flashes.store'), [
                'dates' => [now()->subDays($index)->format('Y-m-d')],
                'activity_type' => 'sailing',
                'event_type' => $type,
            ]);

            $response->assertRedirect(route('flashes.index'));
            $response->assertSessionHasNoErrors();
        }
    }

    public function test_valid_activity_types_are_accepted(): void
    {
        $user = User::factory()->create();

        // Sailing
        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => ['2025-01-01'],
            'activity_type' => 'sailing',
            'event_type' => 'practice',
        ]);
        $response->assertSessionHasNoErrors();

        // Maintenance
        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => ['2025-01-02'],
            'activity_type' => 'maintenance',
        ]);
        $response->assertSessionHasNoErrors();

        // Race Committee
        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => ['2025-01-03'],
            'activity_type' => 'race_committee',
        ]);
        $response->assertSessionHasNoErrors();
    }
}
