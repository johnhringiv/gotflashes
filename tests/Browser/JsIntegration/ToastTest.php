<?php

use App\Models\Flash;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2027-01-15 12:00:00'));
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('shows success toast after flash save', function () {
    $page = visit('/logbook');

    // Use Livewire direct call to avoid flatpickr/native validation issues
    $page->script("
        const formEl = document.querySelector('#activity_type').closest('[wire\\\\:id]');
        const wireId = formEl.getAttribute('wire:id');
        const comp = Livewire.find(wireId);
        comp.set('dates', ['2027-01-10']);
        comp.set('activity_type', 'sailing');
        comp.set('event_type', 'practice');
        comp.call('save');
    ");

    waitForToast($page, 'success');
    $page->assertVisible('#toast-container .alert-success');
    $page->assertSeeIn('#toast-container', 'logged');
});

it('shows success toast after flash delete', function () {
    Flash::factory()->sailing()->forUser($this->user)->onDate('2027-01-10')->create();

    $page = visit('/logbook');
    $page->click('Delete');
    $page->assertVisible('.modal.modal-open');
    $page->click('.modal.modal-open .btn-error');
    waitForToast($page, 'success');
    $page->assertVisible('#toast-container .alert-success');
    $page->assertSeeIn('#toast-container', 'deleted');
});

it('shows warning toast when logging 6th non-sailing day', function () {
    for ($i = 1; $i <= 5; $i++) {
        Flash::factory()->maintenance()->forUser($this->user)
            ->onDate("2027-01-0{$i}")->create();
    }

    $page = visit('/logbook');

    $page->script("
        const formEl = document.querySelector('#activity_type').closest('[wire\\\\:id]');
        const wireId = formEl.getAttribute('wire:id');
        const comp = Livewire.find(wireId);
        comp.set('dates', ['2027-01-06']);
        comp.set('activity_type', 'maintenance');
        comp.call('save');
    ");

    waitForToast($page, 'warning');
    $page->assertVisible('#toast-container .alert-warning');
    $page->assertSeeIn('#toast-container', 'non-sailing');
});

it('toast carries correct alert class (dynamic class safelist)', function () {
    for ($i = 1; $i <= 5; $i++) {
        Flash::factory()->maintenance()->forUser($this->user)
            ->onDate("2027-01-0{$i}")->create();
    }

    $page = visit('/logbook');

    $page->script("
        const formEl = document.querySelector('#activity_type').closest('[wire\\\\:id]');
        const wireId = formEl.getAttribute('wire:id');
        const comp = Livewire.find(wireId);
        comp.set('dates', ['2027-01-06']);
        comp.set('activity_type', 'maintenance');
        comp.call('save');
    ");

    waitForToast($page, 'warning');
    $page->assertVisible('#toast-container .alert-warning');
    $page->assertAttributeContains('#toast-container .alert', 'class', 'alert-warning');
});
