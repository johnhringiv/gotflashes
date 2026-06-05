<?php

use App\Models\Flash;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2027-01-15 12:00:00'));
});

it('opens confirmation modal and cancels without deleting', function () {
    $user = User::factory()->create();
    Flash::factory()->forUser($user)->sailing()->onDate('2027-01-10')->create([
        'event_type' => 'regatta',
        'location' => 'Lake Norman',
    ]);

    $this->actingAs($user);

    $page = visit('/logbook');

    // The flash should be visible
    $page->assertSee('Lake Norman');

    // Click the Delete button
    $page->click('Delete');

    // The confirmation modal should open
    $page->assertVisible('.modal.modal-open');
    $page->assertSee('Delete Activity');
    $page->assertSee('Are you sure you want to delete this activity?');

    // Click Cancel
    $page->click('Cancel');

    // The modal should close
    $page->assertNotPresent('.modal.modal-open');

    // The flash should still be visible (not deleted)
    $page->assertSee('Lake Norman');
});

it('deletes a flash after confirmation and updates the list', function () {
    $user = User::factory()->create();
    Flash::factory()->forUser($user)->sailing()->onDate('2027-01-10')->create([
        'event_type' => 'regatta',
        'location' => 'Lake Norman',
    ]);

    $this->actingAs($user);

    $page = visit('/logbook');

    // The flash should be visible
    $page->assertSee('Lake Norman');

    // Click the Delete button on the flash card
    $page->click('Delete');

    // The confirmation modal should open
    $page->assertVisible('.modal.modal-open');
    $page->assertSee('Are you sure you want to delete this activity?');

    // Confirm the deletion by clicking the Delete button inside the modal
    // The modal has two buttons: "Cancel" and "Delete" - click the red Delete button
    $page->click('.modal.modal-open .btn-error');

    // Should see success toast
    waitForToast($page, 'success');
    $page->assertVisible('#toast-container .alert-success');
    $page->assertSeeIn('#toast-container', 'Flash deleted');

    // The flash should no longer be visible
    $page->assertSee('No activities yet. Log your first flash to get started!');
});
