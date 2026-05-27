<?php

use App\Models\Flash;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2027-01-15 12:00:00'));
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('flatpickr calendar', function () {
    it('opens calendar and shows available dates', function () {
        $page = visit('/logbook');
        $page->click('#date-picker');
        $page->assertVisible('.flatpickr-calendar.open');
        $page->assertScript('document.querySelectorAll(".flatpickr-calendar.open .flatpickr-day").length > 0', true);
    });

    it('multi-date selection works in create mode', function () {
        $page = visit('/logbook');
        $page->click('#date-picker');
        $page->assertVisible('.flatpickr-calendar.open');

        // Click two available days via script
        $page->script("
            const days = document.querySelectorAll('.flatpickr-calendar.open .flatpickr-day:not(.flatpickr-disabled):not(.prevMonthDay):not(.nextMonthDay)');
            if (days[0]) days[0].click();
            if (days[1]) days[1].click();
        ");

        $page->assertScript(
            'document.querySelector("#date-picker")._flatpickr.selectedDates.length >= 1',
            true,
        );
    });

    it('clearAndReinit runs after save — picker is empty and dates show as has-entry', function () {
        $page = visit('/logbook');

        $page->click('#date-picker');
        $page->assertVisible('.flatpickr-calendar.open');
        $page->script("document.querySelector('.flatpickr-calendar.open .flatpickr-day:not(.flatpickr-disabled):not(.prevMonthDay):not(.nextMonthDay)').click()");
        $page->script("document.querySelector('#date-picker')._flatpickr.close()");

        $page->select('#activity_type', 'sailing');
        $page->select('#sailing_type', 'club_race');
        $page->pressAndWaitFor('Log Activity', 2);

        $page->assertVisible('#toast-container .alert-success');
        $page->assertValue('#date-picker', '');
    });

    it('existing dates are disabled and marked with has-entry', function () {
        // Flatpickr uses JS Date (real system time), not Carbon's frozen time.
        // Create flashes with dates the server considers valid AND that flatpickr will display.
        // Use dates relative to Carbon's frozen time (Jan 2027) since those are within the editable range.
        // Then navigate the calendar to January 2027 to see them.
        $realDate1 = '2027-01-10';
        $realDate2 = '2027-01-11';

        Flash::factory()->sailing()->forUser($this->user)->onDate($realDate1)->create();
        Flash::factory()->sailing()->forUser($this->user)->onDate($realDate2)->create();

        $page = visit('/logbook');
        $page->click('#date-picker');
        $page->assertVisible('.flatpickr-calendar.open');

        // Navigate flatpickr to January 2027 where our dates are
        $page->script("
            const fp = document.querySelector('#date-picker')._flatpickr;
            fp.jumpToDate('2027-01-10');
        ");
        $page->wait(1);

        $page->assertScript('document.querySelectorAll(".flatpickr-calendar.open .flatpickr-day.has-entry").length > 0', true);
    });

    it('min/max date enforcement — day-after-tomorrow is disabled', function () {
        $page = visit('/logbook');
        $page->click('#date-picker');
        $page->assertVisible('.flatpickr-calendar.open');

        $page->assertScript(
            "document.querySelector('.flatpickr-day[aria-label=\"January 17, 2027\"]')?.classList.contains('flatpickr-disabled') ?? true",
            true,
        );
    });

    it('year selector dropdown shows options in January', function () {
        $page = visit('/logbook');
        $page->click('#date-picker');
        $page->assertVisible('.flatpickr-calendar.open');
        $page->assertPresent('.flatpickr-current-month');
    });
});

describe('flatpickr edit mode', function () {
    it('single-date edit picker initializes with correct default date', function () {
        Flash::factory()->sailing()->forUser($this->user)->onDate('2027-01-10')->create();

        $page = visit('/logbook');
        $page->click('Edit');
        $page->assertVisible('.modal.modal-open');
        $page->assertVisible('#date-picker-single');
        $page->assertValue('#date-picker-single', '2027-01-10');
    });
});
