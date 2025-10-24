<?php

namespace Tests\Unit;

use App\Models\Flash;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_flash_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $flash = Flash::factory()->forUser($user)->create();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $flash->user());
        $this->assertEquals($user->id, $flash->user->id);
    }

    public function test_date_is_cast_to_date(): void
    {
        $user = User::factory()->create();
        $flash = Flash::factory()->forUser($user)->onDate('2025-01-01')->create();

        $this->assertInstanceOf(\Carbon\Carbon::class, $flash->date);
    }

    public function test_sail_number_is_cast_to_integer(): void
    {
        $user = User::factory()->create();
        $flash = Flash::factory()->forUser($user)->create([
            'sail_number' => '12345',
        ]);

        $this->assertIsInt($flash->sail_number);
        $this->assertEquals(12345, $flash->sail_number);
    }

    public function test_flash_can_be_created_with_all_fields(): void
    {
        $user = User::factory()->create();
        $flash = Flash::factory()->forUser($user)->create([
            'date' => '2025-01-01',
            'event_type' => 'regatta',
            'location' => 'Lake Example',
            'sail_number' => 12345,
            'notes' => 'Great day on the water!',
        ]);

        $this->assertEquals($user->id, $flash->user_id);
        $this->assertEquals('2025-01-01', $flash->date->format('Y-m-d'));
        $this->assertEquals('sailing', $flash->activity_type);
        $this->assertEquals('regatta', $flash->event_type);
        $this->assertEquals('Lake Example', $flash->location);
        $this->assertEquals(12345, $flash->sail_number);
        $this->assertEquals('Great day on the water!', $flash->notes);
    }

    public function test_flash_can_be_created_with_minimal_fields(): void
    {
        $user = User::factory()->create();
        $flash = Flash::factory()->forUser($user)->create([
            'date' => '2025-01-01',
            'event_type' => null,
            'location' => null,
            'sail_number' => null,
            'notes' => null,
        ]);

        $this->assertEquals($user->id, $flash->user_id);
        $this->assertEquals('2025-01-01', $flash->date->format('Y-m-d'));
        $this->assertEquals('sailing', $flash->activity_type);
        $this->assertNull($flash->event_type);
        $this->assertNull($flash->location);
        $this->assertNull($flash->sail_number);
        $this->assertNull($flash->notes);
    }

    public function test_flash_is_editable_when_within_date_range(): void
    {
        $user = User::factory()->create();
        $minDate = now()->startOfYear();
        $maxDate = now()->addDay();

        // Flash within range
        $flash = Flash::factory()->forUser($user)->create([
            'date' => now()->format('Y-m-d'),
        ]);

        $this->assertTrue($flash->isEditable($minDate, $maxDate));
    }

    public function test_flash_is_not_editable_when_before_min_date(): void
    {
        $user = User::factory()->create();
        $minDate = now()->startOfYear();
        $maxDate = now()->addDay();

        // Flash before minDate (previous year)
        $flash = Flash::factory()->forUser($user)->create([
            'date' => now()->subYear()->format('Y-m-d'),
        ]);

        $this->assertFalse($flash->isEditable($minDate, $maxDate));
    }

    public function test_flash_is_not_editable_when_after_max_date(): void
    {
        $user = User::factory()->create();
        $minDate = now()->startOfYear();
        $maxDate = now()->addDay();

        // Flash after maxDate (future)
        $flash = Flash::factory()->forUser($user)->create([
            'date' => now()->addDays(2)->format('Y-m-d'),
        ]);

        $this->assertFalse($flash->isEditable($minDate, $maxDate));
    }

    public function test_flash_is_editable_at_boundary_dates(): void
    {
        $user = User::factory()->create();
        $minDate = now()->startOfYear();
        $maxDate = now()->addDay();

        // Flash exactly at minDate
        $flashAtMin = Flash::factory()->forUser($user)->create([
            'date' => $minDate->format('Y-m-d'),
        ]);
        $this->assertTrue($flashAtMin->isEditable($minDate, $maxDate));

        // Flash exactly at maxDate
        $flashAtMax = Flash::factory()->forUser($user)->create([
            'date' => $maxDate->format('Y-m-d'),
        ]);
        $this->assertTrue($flashAtMax->isEditable($minDate, $maxDate));
    }
}
