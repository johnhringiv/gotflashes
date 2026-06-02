<?php

use App\Models\Flash;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2027-01-15 12:00:00'));
});

it('opens edit modal when clicking Edit on a flash', function () {
    $user = User::factory()->create();
    Flash::factory()->forUser($user)->sailing()->onDate('2027-01-10')->create([
        'event_type' => 'regatta',
        'location' => 'Lake Norman',
    ]);

    $this->actingAs($user);

    $page = visit('/logbook');

    // Click the Edit button on the flash card
    $page->click('Edit');

    // The edit modal should open
    $page->assertVisible('.modal.modal-open');
    $page->assertSee('Edit Activity');
    $page->assertVisible('#activity_type_edit');
    $page->assertVisible('#date-picker-single');
});

it('updates a flash and reflects the change in the list', function () {
    $user = User::factory()->create();
    Flash::factory()->forUser($user)->sailing()->onDate('2027-01-10')->create([
        'event_type' => 'regatta',
        'location' => 'Lake Norman',
    ]);

    $this->actingAs($user);

    $page = visit('/logbook');

    // Open the edit modal
    $page->click('Edit');
    $page->assertVisible('.modal.modal-open');

    // Change the location using the input inside the modal
    $page->script(<<<'JS'
        const modal = document.querySelector('.modal.modal-open');
        const locationInput = modal.querySelector('input[wire\\:model\\.live\\.blur="location"]');
        locationInput.value = '';
        locationInput.dispatchEvent(new Event('input', { bubbles: true }));
    JS);
    $page->wait(1);

    // Fill the new location in the modal's location field
    $page->script(<<<'JS'
        const modal = document.querySelector('.modal.modal-open');
        const locationInput = modal.querySelector('input[wire\\:model\\.live\\.blur="location"]');
        const nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
        nativeInputValueSetter.call(locationInput, 'Chesapeake Bay');
        locationInput.dispatchEvent(new Event('input', { bubbles: true }));
        locationInput.dispatchEvent(new Event('change', { bubbles: true }));
        locationInput.dispatchEvent(new Event('blur', { bubbles: true }));
    JS);
    // Click Update Activity
    $page->pressAndWaitFor('Update Activity', 2);

    // Wait for the update to complete
    $page->assertVisible('#toast-container .alert-success');
    $page->assertSeeIn('#toast-container', 'Flash updated successfully');

    // The updated location should appear in the flash list
    $page->assertSee('Chesapeake Bay');
});

it('disables all dates except the current one in the edit calendar', function () {
    $user = User::factory()->create();
    $flash = Flash::factory()->forUser($user)->sailing()->onDate('2027-01-10')->create([
        'event_type' => 'regatta',
    ]);

    $this->actingAs($user);

    $page = visit('/logbook');

    // Open the edit modal
    $page->click('Edit');
    $page->assertVisible('.modal.modal-open');

    // The date picker in edit mode is single-select with data-mode="single"
    $page->assertAttribute('#date-picker-single', 'data-mode', 'single');

    // The default date should be the flash's date
    $page->assertAttribute('#date-picker-single', 'data-default-date', '2027-01-10');
});

it('reinitializes form JS in edit modal when switching activity types', function () {
    $user = User::factory()->create();
    Flash::factory()->forUser($user)->sailing()->onDate('2027-01-10')->create([
        'event_type' => 'regatta',
    ]);

    $this->actingAs($user);

    $page = visit('/logbook');

    // Open the edit modal
    $page->click('Edit');
    $page->assertVisible('.modal.modal-open');

    // Initially with sailing, the sailing_type_edit dropdown should be enabled
    $page->assertScript(
        '!document.getElementById("sailing_type_edit").disabled',
        true
    );

    // Switch to maintenance
    $page->select('#activity_type_edit', 'maintenance');
    // The sailing_type_edit dropdown should now be disabled
    $page->assertScript(
        'document.getElementById("sailing_type_edit").disabled',
        true
    );

    // Switch back to sailing
    $page->select('#activity_type_edit', 'sailing');
    // The sailing_type_edit dropdown should be enabled again
    $page->assertScript(
        '!document.getElementById("sailing_type_edit").disabled',
        true
    );
});
