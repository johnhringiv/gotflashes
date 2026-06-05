<?php

use App\Models\Flash;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2027-01-15 12:00:00'));
});

it('logs a single sailing day via the date picker', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visit('/logbook');
    trackLivewireRequests($page);

    // Open the date picker
    $page->click('#date-picker');
    $page->assertVisible('.flatpickr-calendar.open');

    // Pick an available day
    $page->script("document.querySelector('.flatpickr-day:not(.flatpickr-disabled):not(.prevMonthDay):not(.nextMonthDay)').click()");

    // Close the calendar
    $page->script("document.querySelector('#date-picker')._flatpickr.close()");
    settleLivewire($page);

    // Select activity type, then event type — settle each so the live syncs
    // (incl. the morph that enables the sailing-type select) land before submit.
    $page->select('#activity_type', 'sailing');
    settleLivewire($page);
    $page->select('#sailing_type', 'regatta');
    settleLivewire($page);

    // Submit
    $page->pressAndWaitFor('Log Activity', 2);

    // Verify success toast
    waitForToast($page, 'success');
    $page->assertVisible('#toast-container .alert-success');
    $page->assertSeeIn('#toast-container', 'Flash logged successfully');
});

it('logs multiple dates in one submission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visit('/logbook');

    // Use Livewire direct call to submit multiple dates
    $page->script("
        const formEl = document.querySelector('#activity_type').closest('[wire\\\\:id]');
        const wireId = formEl.getAttribute('wire:id');
        const comp = Livewire.find(wireId);
        comp.set('dates', ['2027-01-08', '2027-01-09']);
        comp.set('activity_type', 'sailing');
        comp.set('event_type', 'practice');
        comp.call('save');
    ");
    // Verify success toast with plural message
    waitForToast($page, 'success');
    $page->assertVisible('#toast-container .alert-success');
    $page->assertSeeIn('#toast-container', 'flashes logged successfully');
});

it('requires event_type when activity_type is sailing', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visit('/logbook');

    // Open the date picker and pick a day
    $page->click('#date-picker');
    $page->assertVisible('.flatpickr-calendar.open');
    $page->script("document.querySelector('.flatpickr-day:not(.flatpickr-disabled):not(.prevMonthDay):not(.nextMonthDay)').click()");
    $page->script("document.querySelector('#date-picker')._flatpickr.close()");

    // Select sailing but do NOT select an event type
    $page->select('#activity_type', 'sailing');

    // Submit
    $page->pressAndWaitFor('Log Activity', 2);

    // Should NOT see a success toast since event_type is required for sailing
    $page->assertNotPresent('#toast-container .alert-success');
});

it('does not require event_type for maintenance', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visit('/logbook');

    // Open the date picker and pick a day
    // Use Livewire direct call to bypass native validation on sailing_type
    $page->script("
        const formEl = document.querySelector('#activity_type').closest('[wire\\\\:id]');
        const wireId = formEl.getAttribute('wire:id');
        const comp = Livewire.find(wireId);
        comp.set('dates', ['2027-01-09']);
        comp.set('activity_type', 'maintenance');
        comp.call('save');
    ");
    // Should see success toast
    waitForToast($page, 'success');
    $page->assertVisible('#toast-container .alert-success');
    $page->assertSeeIn('#toast-container', 'Flash logged successfully');
});

it('does not require event_type for race_committee', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visit('/logbook');

    // Use Livewire direct call to bypass native validation
    $page->script("
        const formEl = document.querySelector('#activity_type').closest('[wire\\\\:id]');
        const wireId = formEl.getAttribute('wire:id');
        const comp = Livewire.find(wireId);
        comp.set('dates', ['2027-01-08']);
        comp.set('activity_type', 'race_committee');
        comp.call('save');
    ");
    // Should see success toast
    waitForToast($page, 'success');
    $page->assertVisible('#toast-container .alert-success');
    $page->assertSeeIn('#toast-container', 'Flash logged successfully');
});

it('clears the date picker after a successful save', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visit('/logbook');
    trackLivewireRequests($page);

    // Open the date picker and pick a day
    $page->click('#date-picker');
    $page->assertVisible('.flatpickr-calendar.open');
    $page->script("document.querySelector('.flatpickr-day:not(.flatpickr-disabled):not(.prevMonthDay):not(.nextMonthDay)').click()");
    $page->script("document.querySelector('#date-picker')._flatpickr.close()");
    settleLivewire($page);

    // Select activity type
    $page->select('#activity_type', 'maintenance');
    settleLivewire($page);

    // Submit
    $page->pressAndWaitFor('Log Activity', 2);

    // Wait for toast to confirm save completed
    waitForToast($page, 'success');
    $page->assertVisible('#toast-container .alert-success');

    // Date picker value should be empty after successful save
    $page->assertScript(
        'document.getElementById("date-picker").value',
        ''
    );
});

it('rejects future dates beyond today plus one day', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visit('/logbook');

    // Use script to set a future date via Livewire (beyond max date of 2027-01-16)
    $page->script(<<<'JS'
        const wireEl = document.querySelector('#activity_type').closest('[wire\\:id]');
        const wireId = wireEl.getAttribute('wire:id');
        Livewire.find(wireId).set('dates', ['2027-02-15']);
        Livewire.find(wireId).set('activity_type', 'maintenance');
    JS);

    // Wait a moment for Livewire to process
    // Submit the form
    $page->pressAndWaitFor('Log Activity', 2);
    // Should NOT see a success toast
    $page->assertNotPresent('#toast-container .alert-success');

    // Should see a validation error
    $page->assertSee('date');
});

it('rejects duplicate dates for the same user', function () {
    $user = User::factory()->create();

    // Seed an existing flash on a specific date
    Flash::factory()->forUser($user)->sailing()->onDate('2027-01-10')->create();

    $this->actingAs($user);

    $page = visit('/logbook');

    // Use script to set the duplicate date via Livewire
    $page->script(<<<'JS'
        const wireEl = document.querySelector('#activity_type').closest('[wire\\:id]');
        const wireId = wireEl.getAttribute('wire:id');
        Livewire.find(wireId).set('dates', ['2027-01-10']);
        Livewire.find(wireId).set('activity_type', 'sailing');
        Livewire.find(wireId).set('event_type', 'regatta');
    JS);

    // Wait for Livewire to process
    // Submit the form
    $page->pressAndWaitFor('Log Activity', 2);
    // Should NOT see a success toast
    $page->assertNotPresent('#toast-container .alert-success');

    // Should see a duplicate date error
    $page->assertSee('already have activities logged');
});

it('clears validation errors as fields are corrected', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visit('/logbook');

    // Submit with nothing filled — validation errors on date(s) and activity type.
    $page->press('Log Activity');
    $page->assertAttributeContains('#date-picker', 'class', 'input-error');
    $page->assertAttributeContains('#activity_type', 'class', 'select-error');

    // Selecting an activity type (wire:model.live) clears its error; the
    // untouched date field keeps its error.
    $page->select('#activity_type', 'sailing');
    $page->assertAttributeDoesntContain('#activity_type', 'class', 'select-error');
    $page->assertAttributeContains('#date-picker', 'class', 'input-error');

    // Picking a date clears the date error too.
    $page->click('#date-picker');
    $page->script("document.querySelector('.flatpickr-day:not(.flatpickr-disabled):not(.prevMonthDay):not(.nextMonthDay)').click()");
    $page->script("document.querySelector('#date-picker')._flatpickr.close()");
    $page->assertAttributeDoesntContain('#date-picker', 'class', 'input-error');
});

it('clears the sailing-type error when activity type changes away from sailing', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visit('/logbook');

    // Sailing selected, date + sailing type left blank, then submit.
    $page->select('#activity_type', 'sailing');
    $page->press('Log Activity');
    $page->assertAttributeContains('#sailing_type', 'class', 'select-error');

    // Switching activity type away from sailing clears the now-irrelevant
    // sailing-type (event_type) error.
    $page->select('#activity_type', 'maintenance');
    $page->assertAttributeDoesntContain('#sailing_type', 'class', 'select-error');
});
