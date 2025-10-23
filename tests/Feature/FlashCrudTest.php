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
            'dates' => ['2025-01-01'],
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
            'dates' => ['2025-01-01'],
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

    public function test_users_can_create_multiple_flashes_at_once(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => ['2025-01-01', '2025-01-02', '2025-01-03'],
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
            'location' => 'Lake Example',
            'sail_number' => 12345,
            'notes' => 'Three day regatta!',
        ]);

        $response->assertRedirect(route('flashes.index'));
        $response->assertSessionHas('success', '3 flashes logged successfully!');

        // Verify all three flashes were created
        $this->assertCount(3, $user->flashes);

        $dates = $user->flashes->pluck('date')->map(fn ($d) => $d->format('Y-m-d'))->sort()->values();
        $this->assertEquals(['2025-01-01', '2025-01-02', '2025-01-03'], $dates->toArray());

        // Verify all have the same activity data
        foreach ($user->flashes as $flash) {
            $this->assertEquals('sailing', $flash->activity_type);
            $this->assertEquals('regatta', $flash->event_type);
            $this->assertEquals('Lake Example', $flash->location);
            $this->assertEquals(12345, $flash->sail_number);
            $this->assertEquals('Three day regatta!', $flash->notes);
        }
    }

    public function test_single_date_success_message(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => ['2025-01-01'],
            'activity_type' => 'sailing',
            'event_type' => 'practice',
        ]);

        $response->assertSessionHas('success', 'Flash logged successfully!');
    }

    public function test_multiple_dates_success_message(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => ['2025-01-01', '2025-01-02'],
            'activity_type' => 'sailing',
            'event_type' => 'practice',
        ]);

        $response->assertSessionHas('success', '2 flashes logged successfully!');
    }

    public function test_users_cannot_create_duplicate_dates(): void
    {
        $user = User::factory()->create();

        Flash::factory()->forUser($user)->onDate('2025-01-01')->create();

        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => ['2025-01-01'],
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
        ]);

        $response->assertSessionHasErrors('dates');
        $this->assertStringContainsString('2025-01-01', session()->get('errors')->first('dates'));
    }

    public function test_all_or_nothing_validation_rejects_entire_batch_if_any_date_is_duplicate(): void
    {
        $user = User::factory()->create();

        // Create an existing flash for Jan 2nd
        Flash::factory()->forUser($user)->onDate('2025-01-02')->create();

        // Try to create 3 flashes, where the middle one is a duplicate
        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => ['2025-01-01', '2025-01-02', '2025-01-03'],
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
        ]);

        // Should fail with error mentioning the duplicate date
        $response->assertSessionHasErrors('dates');
        $this->assertStringContainsString('2025-01-02', session()->get('errors')->first('dates'));

        // Verify NO new flashes were created (all-or-nothing)
        $this->assertCount(1, $user->flashes);
        $this->assertEquals('2025-01-02', $user->flashes->first()->date->format('Y-m-d'));
    }

    public function test_all_or_nothing_validation_with_multiple_duplicates(): void
    {
        $user = User::factory()->create();

        // Create two existing flashes
        Flash::factory()->forUser($user)->onDate('2025-01-01')->create();
        Flash::factory()->forUser($user)->onDate('2025-01-03')->create();

        // Try to create 3 flashes, where two are duplicates
        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => ['2025-01-01', '2025-01-02', '2025-01-03'],
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
        ]);

        // Should fail with error mentioning both duplicate dates
        $response->assertSessionHasErrors('dates');
        $error = session()->get('errors')->first('dates');
        $this->assertStringContainsString('2025-01-01', $error);
        $this->assertStringContainsString('2025-01-03', $error);

        // Verify NO new flashes were created (still only 2)
        $this->assertCount(2, $user->flashes);
    }

    public function test_users_cannot_create_future_dates(): void
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

    public function test_users_can_create_todays_date(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => [now()->format('Y-m-d')],
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
            'dates' => ['2025-01-01'],
            'activity_type' => 'sailing',
        ]);

        $response->assertSessionHasErrors('event_type');
    }

    public function test_maintenance_activity_does_not_require_event_type(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => ['2025-01-01'],
            'activity_type' => 'maintenance',
        ]);

        $response->assertRedirect(route('flashes.index'));
        $response->assertSessionHas('success');
    }

    public function test_race_committee_activity_does_not_require_event_type(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => ['2025-01-01'],
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

    public function test_warning_message_shown_when_logging_sixth_non_sailing_day(): void
    {
        $user = User::factory()->create();

        // Create 5 non-sailing days (maintenance and race committee)
        for ($i = 1; $i <= 3; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'maintenance',
            ]);
        }
        for ($i = 4; $i <= 5; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'race_committee',
            ]);
        }

        // Log 6th non-sailing day (this one doesn't count toward awards)
        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => [now()->startOfYear()->addDays(6)->format('Y-m-d')],
            'activity_type' => 'maintenance',
        ]);

        $response->assertRedirect(route('flashes.index'));
        $response->assertSessionHas('warning');
        $this->assertStringContainsString('5 non-sailing days', session('warning'));
        $this->assertStringContainsString('counting toward awards', session('warning'));

        // Verify the activity was still created
        $this->assertDatabaseCount('flashes', 6);
    }

    public function test_success_message_shown_for_first_five_non_sailing_days(): void
    {
        $user = User::factory()->create();

        // Create 4 non-sailing days
        for ($i = 1; $i <= 4; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'maintenance',
            ]);
        }

        // Log 5th non-sailing day (this one still counts toward awards)
        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => [now()->startOfYear()->addDays(5)->format('Y-m-d')],
            'activity_type' => 'race_committee',
        ]);

        $response->assertRedirect(route('flashes.index'));
        $response->assertSessionHas('success', 'Flash logged successfully!');
        $response->assertSessionMissing('warning');
    }

    public function test_warning_message_appears_for_maintenance_activity(): void
    {
        $user = User::factory()->create();

        // Create 5 non-sailing days
        for ($i = 1; $i <= 5; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'maintenance',
            ]);
        }

        // Log 6th maintenance day
        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => [now()->startOfYear()->addDays(6)->format('Y-m-d')],
            'activity_type' => 'maintenance',
        ]);

        $response->assertSessionHas('warning');
    }

    public function test_warning_message_appears_for_race_committee_activity(): void
    {
        $user = User::factory()->create();

        // Create 5 non-sailing days
        for ($i = 1; $i <= 5; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'race_committee',
            ]);
        }

        // Log 6th race committee day
        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => [now()->startOfYear()->addDays(6)->format('Y-m-d')],
            'activity_type' => 'race_committee',
        ]);

        $response->assertSessionHas('warning');
    }

    public function test_no_warning_for_sailing_activities(): void
    {
        $user = User::factory()->create();

        // Create 5 non-sailing days
        for ($i = 1; $i <= 5; $i++) {
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => now()->startOfYear()->addDays($i),
                'activity_type' => 'maintenance',
            ]);
        }

        // Log sailing day (should not trigger warning even with 5 non-sailing days)
        $response = $this->actingAs($user)->post(route('flashes.store'), [
            'dates' => [now()->startOfYear()->addDays(6)->format('Y-m-d')],
            'activity_type' => 'sailing',
            'event_type' => 'practice',
        ]);

        $response->assertSessionHas('success', 'Flash logged successfully!');
        $response->assertSessionMissing('warning');
    }

    public function test_existing_dates_includes_current_year_only_when_not_january(): void
    {
        // Mock now() to be February 15th
        $this->travelTo(now()->setMonth(2)->setDay(15));

        $user = User::factory()->create();

        // Create flashes from previous year
        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => now()->subYear()->startOfYear()->addDays(10),
            'activity_type' => 'sailing',
        ]);

        // Create flash from current year
        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => now()->startOfYear()->addDays(5),
            'activity_type' => 'sailing',
        ]);

        $response = $this->actingAs($user)->get(route('flashes.index'));

        $response->assertStatus(200);

        // existingDates should only include current year (not previous year in Feb)
        $existingDates = $response->viewData('existingDates');
        $this->assertCount(1, $existingDates);
        $this->assertEquals(now()->startOfYear()->addDays(5)->format('Y-m-d'), $existingDates[0]);
    }

    public function test_existing_dates_includes_previous_year_in_january(): void
    {
        // Mock now() to be January 15th
        $this->travelTo(now()->setMonth(1)->setDay(15));

        $user = User::factory()->create();

        // Create flashes from previous year
        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => now()->subYear()->startOfYear()->addDays(10),
            'activity_type' => 'sailing',
        ]);

        // Create flash from current year
        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => now()->startOfYear()->addDays(5),
            'activity_type' => 'sailing',
        ]);

        $response = $this->actingAs($user)->get(route('flashes.index'));

        $response->assertStatus(200);

        // existingDates should include both previous year AND current year in January
        $existingDates = $response->viewData('existingDates');
        $this->assertCount(2, $existingDates);
        $this->assertContains(now()->subYear()->startOfYear()->addDays(10)->format('Y-m-d'), $existingDates);
        $this->assertContains(now()->startOfYear()->addDays(5)->format('Y-m-d'), $existingDates);
    }
}
