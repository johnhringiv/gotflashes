<?php

namespace Tests\Feature;

use App\Models\Flash;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_flashes_index(): void
    {
        $response = $this->get(route('flashes.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_flashes_index(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('flashes.index'));

        $response->assertStatus(200);
    }

    public function test_users_only_see_their_own_flashes(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $flash1 = Flash::factory()->forUser($user1)->onDate('2025-01-01')->create();
        $flash2 = Flash::factory()->forUser($user2)->onDate('2025-01-02')->create();

        $response = $this->actingAs($user1)->get(route('flashes.index'));

        // Verify user1 sees their own flash
        $response->assertStatus(200);
        $this->assertCount(1, $user1->fresh()->flashes);
        $this->assertEquals('2025-01-01', $user1->flashes->first()->date->format('Y-m-d'));
    }

    public function test_users_can_create_flash_with_minimal_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'date' => '2025-01-01',
            'activity_type' => 'sailing',
            'event_type' => 'practice',
        ]);

        $response->assertRedirect(route('flashes.index'));
        $response->assertSessionHas('success');

        $flash = $user->flashes()->first();
        $this->assertNotNull($flash);
        $this->assertEquals('2025-01-01', $flash->date->format('Y-m-d'));
        $this->assertEquals('sailing', $flash->activity_type);
        $this->assertEquals('practice', $flash->event_type);
    }

    public function test_users_can_create_flash_with_all_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'date' => '2025-01-01',
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
            'location' => 'Lake Example',
            'sail_number' => 12345,
            'notes' => 'Great sailing day!',
        ]);

        $response->assertRedirect(route('flashes.index'));
        $response->assertSessionHas('success');

        $flash = $user->flashes()->first();
        $this->assertNotNull($flash);
        $this->assertEquals('2025-01-01', $flash->date->format('Y-m-d'));
        $this->assertEquals('sailing', $flash->activity_type);
        $this->assertEquals('regatta', $flash->event_type);
        $this->assertEquals('Lake Example', $flash->location);
        $this->assertEquals(12345, $flash->sail_number);
        $this->assertEquals('Great sailing day!', $flash->notes);
    }

    public function test_users_cannot_create_duplicate_dates(): void
    {
        $user = User::factory()->create();

        Flash::factory()->forUser($user)->onDate('2025-01-01')->create();

        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'date' => '2025-01-01',
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
        ]);

        $response->assertSessionHasErrors('date');
    }

    public function test_users_cannot_create_future_dates(): void
    {
        $user = User::factory()->create();

        $futureDate = now()->addDays(2)->format('Y-m-d');

        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'date' => $futureDate,
            'activity_type' => 'sailing',
            'event_type' => 'practice',
        ]);

        $response->assertSessionHasErrors('date');
    }

    public function test_users_can_create_todays_date(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'date' => now()->format('Y-m-d'),
            'activity_type' => 'sailing',
            'event_type' => 'practice',
        ]);

        $response->assertRedirect(route('flashes.index'));
        $response->assertSessionHas('success');
    }

    public function test_sailing_activity_requires_event_type(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'date' => '2025-01-01',
            'activity_type' => 'sailing',
        ]);

        $response->assertSessionHasErrors('event_type');
    }

    public function test_maintenance_activity_does_not_require_event_type(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'date' => '2025-01-01',
            'activity_type' => 'maintenance',
        ]);

        $response->assertRedirect(route('flashes.index'));
        $response->assertSessionHas('success');
    }

    public function test_race_committee_activity_does_not_require_event_type(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'date' => '2025-01-01',
            'activity_type' => 'race_committee',
        ]);

        $response->assertRedirect(route('flashes.index'));
        $response->assertSessionHas('success');
    }

    public function test_users_can_view_edit_form_for_own_flash(): void
    {
        $user = User::factory()->create();
        $flash = Flash::factory()->forUser($user)->onDate('2025-01-01')->create();

        $response = $this->actingAs($user)->get(route('flashes.edit', $flash));

        $response->assertStatus(200);
        $response->assertSee('2025-01-01');
    }

    public function test_users_cannot_view_edit_form_for_others_flash(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $flash = Flash::factory()->forUser($user1)->create();

        $response = $this->actingAs($user2)->get(route('flashes.edit', $flash));

        $response->assertForbidden();
    }

    public function test_users_can_update_their_own_flash(): void
    {
        $user = User::factory()->create();
        $flash = Flash::factory()->forUser($user)->create([
            'date' => '2025-01-01',
            'event_type' => 'practice',
            'notes' => 'Original notes',
        ]);

        $response = $this->actingAs($user)->put(route('flashes.update', $flash), [
            'date' => '2025-01-02',
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
            'notes' => 'Updated notes',
        ]);

        $response->assertRedirect(route('flashes.index'));
        $response->assertSessionHas('success');

        $flash->refresh();
        $this->assertEquals('2025-01-02', $flash->date->format('Y-m-d'));
        $this->assertEquals('sailing', $flash->activity_type);
        $this->assertEquals('regatta', $flash->event_type);
        $this->assertEquals('Updated notes', $flash->notes);
    }

    public function test_users_cannot_update_others_flash(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $flash = Flash::factory()->forUser($user1)->create();

        $response = $this->actingAs($user2)->put(route('flashes.update', $flash), [
            'date' => '2025-01-02',
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
        ]);

        $response->assertForbidden();
    }

    public function test_users_can_delete_their_own_flash(): void
    {
        $user = User::factory()->create();
        $flash = Flash::factory()->forUser($user)->create();

        $response = $this->actingAs($user)->delete(route('flashes.destroy', $flash));

        $response->assertRedirect(route('flashes.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('flashes', [
            'id' => $flash->id,
        ]);
    }

    public function test_users_cannot_delete_others_flash(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $flash = Flash::factory()->forUser($user1)->create();

        $response = $this->actingAs($user2)->delete(route('flashes.destroy', $flash));

        $response->assertForbidden();

        $this->assertDatabaseHas('flashes', [
            'id' => $flash->id,
        ]);
    }

    public function test_guests_cannot_create_flash(): void
    {
        $response = $this->post(route('flashes.store'), [
            'date' => '2025-01-01',
            'activity_type' => 'sailing',
            'event_type' => 'practice',
        ]);

        $response->assertRedirect(route('login'));
    }
}
