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

    // Open the date picker
    $page->click('#date-picker');
    $page->assertVisible('.flatpickr-calendar.open');

    // Pick an available day
    $page->script("document.querySelector('.flatpickr-day:not(.flatpickr-disabled):not(.prevMonthDay):not(.nextMonthDay)').click()");

    // Close the calendar
    $page->script("document.querySelector('#date-picker')._flatpickr.close()");

    // Select activity type and event type
    $page->select('#activity_type', 'sailing');
    $page->select('#sailing_type', 'regatta');

    // Submit
    $page->pressAndWaitFor('Log Activity', 2);

    // Verify success toast
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
    $page->wait(3);

    // Verify success toast with plural message
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
    $page->wait(3);

    // Should see success toast
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
    $page->wait(3);

    // Should see success toast
    $page->assertVisible('#toast-container .alert-success');
    $page->assertSeeIn('#toast-container', 'Flash logged successfully');
});

it('clears the date picker after a successful save', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visit('/logbook');

    // Open the date picker and pick a day
    $page->click('#date-picker');
    $page->assertVisible('.flatpickr-calendar.open');
    $page->script("document.querySelector('.flatpickr-day:not(.flatpickr-disabled):not(.prevMonthDay):not(.nextMonthDay)').click()");
    $page->script("document.querySelector('#date-picker')._flatpickr.close()");

    // Select activity type
    $page->select('#activity_type', 'maintenance');

    // Submit
    $page->pressAndWaitFor('Log Activity', 2);

    // Wait for toast to confirm save completed
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
    $page->wait(1);

    // Submit the form
    $page->pressAndWaitFor('Log Activity', 2);
    $page->wait(1);

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
    $page->wait(1);

    // Submit the form
    $page->pressAndWaitFor('Log Activity', 2);
    $page->wait(1);

    // Should NOT see a success toast
    $page->assertNotPresent('#toast-container .alert-success');

    // Should see a duplicate date error
    $page->assertSee('already have activities logged');
});
