<?php

use App\Models\Flash;
use App\Models\User;
use Carbon\Carbon;

// Server time is frozen to Jan 2027 (grace period: min 2026-01-01, max
// 2027-01-16), but the browser runs on REAL time, so the calendar opens on
// the real current month — tests navigate explicitly via _datePicker.setView().

beforeEach(function () {
    $this->travelTo(Carbon::parse('2027-01-15 12:00:00'));
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('date picker calendar', function () {
    it('opens the calendar with day buttons on click', function () {
        $page = visit('/logbook');
        $page->click('#date-picker');
        $page->assertVisible('.date-picker');
        $page->assertScript('document.querySelectorAll(".date-picker .dp-day").length > 0', true);
    });

    it('selects and toggles multiple dates in create mode', function () {
        $page = visit('/logbook');
        trackLivewireRequests($page);

        $page->click('#date-picker');
        $page->assertVisible('.date-picker');
        $page->script("document.querySelector('#date-picker')._datePicker.setView(2027, 1)");

        $page->script("document.querySelector('.dp-day[data-date=\"2027-01-05\"]').click()");
        $page->script("document.querySelector('.dp-day[data-date=\"2027-01-06\"]').click()");
        settleLivewire($page);

        $page->assertValue('#date-picker', '2027-01-05, 2027-01-06');
        $page->assertScript('document.querySelector("#date-picker")._datePicker.selected.length', 2);

        // Clicking a selected day again deselects it.
        $page->script("document.querySelector('.dp-day[data-date=\"2027-01-05\"]').click()");
        settleLivewire($page);
        $page->assertValue('#date-picker', '2027-01-06');
    });

    it('clears the picker after save — input empty, saved date becomes has-entry', function () {
        $page = visit('/logbook');
        trackLivewireRequests($page);

        $page->click('#date-picker');
        $page->assertVisible('.date-picker');
        $page->script("document.querySelector('#date-picker')._datePicker.setView(2027, 1)");
        $page->script("document.querySelector('.dp-day[data-date=\"2027-01-05\"]').click()");
        $page->script("document.querySelector('#date-picker')._datePicker.close()");
        settleLivewire($page);

        $page->select('#activity_type', 'sailing');
        settleLivewire($page);
        $page->select('#sailing_type', 'club_race');
        settleLivewire($page);
        $page->pressAndWaitFor('Log Activity', 2);

        waitForToast($page, 'success');
        $page->assertVisible('#toast-container .alert-success');
        $page->assertValue('#date-picker', '');

        // Reopen: the config is re-read from the morphed data attributes, so
        // the just-saved date now renders as a disabled lightning day.
        $page->click('#date-picker');
        $page->script("document.querySelector('#date-picker')._datePicker.setView(2027, 1)");
        $page->assertScript(
            'document.querySelector(\'.dp-day[data-date="2027-01-05"]\').classList.contains("has-entry")',
            true,
        );
    });

    it('marks existing dates as disabled has-entry days', function () {
        Flash::factory()->sailing()->forUser($this->user)->onDate('2027-01-10')->create();
        Flash::factory()->sailing()->forUser($this->user)->onDate('2027-01-11')->create();

        $page = visit('/logbook');
        $page->click('#date-picker');
        $page->assertVisible('.date-picker');
        $page->script("document.querySelector('#date-picker')._datePicker.setView(2027, 1)");

        $page->assertScript('document.querySelectorAll(".date-picker .dp-day.has-entry").length', 2);
        $page->assertScript('document.querySelector(\'.dp-day[data-date="2027-01-10"]\').disabled', true);
    });

    it('enforces min/max — tomorrow selectable, day-after-tomorrow disabled', function () {
        $page = visit('/logbook');
        $page->click('#date-picker');
        $page->script("document.querySelector('#date-picker')._datePicker.setView(2027, 1)");

        // Server "today" is 2027-01-15; max is today +1.
        $page->assertScript('document.querySelector(\'.dp-day[data-date="2027-01-16"]\').disabled', false);
        $page->assertScript('document.querySelector(\'.dp-day[data-date="2027-01-17"]\').disabled', true);
    });

    it('shows a year dropdown during the January grace period', function () {
        $page = visit('/logbook');
        $page->click('#date-picker');
        $page->assertVisible('.date-picker');

        // Grace period range spans 2026–2027, so the year control is a select.
        $page->assertPresent('.dp-year-select');
        $page->assertScript(
            'Array.from(document.querySelectorAll(".dp-year-select option")).map(o => o.value).join(",")',
            '2027,2026',
        );
    });

    it('navigates months with the header arrows', function () {
        $page = visit('/logbook');
        $page->click('#date-picker');
        $page->script("document.querySelector('#date-picker')._datePicker.setView(2027, 1)");
        $page->assertSeeIn('.dp-title', 'January');

        // January 2027 is the max month, so only prev is enabled.
        $page->assertScript('document.querySelector(\'.dp-nav[data-nav="1"]\').disabled', true);
        $page->script("document.querySelector('.dp-nav[data-nav=\"-1\"]').click()");
        $page->assertSeeIn('.dp-title', 'December');
    });

    it('closes on outside click and on Escape', function () {
        $page = visit('/logbook');
        $page->click('#date-picker');
        $page->assertVisible('.date-picker');

        // Light dismiss: pointer down anywhere outside the calendar.
        $page->click('#location');
        $page->assertNotPresent('.date-picker');

        // Escape from within the calendar closes and refocuses the input.
        $page->click('#date-picker');
        $page->assertVisible('.date-picker');
        $page->script(<<<'JS'
            document.querySelector('.dp-day[tabindex="0"]')
                .dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        JS);
        $page->assertNotPresent('.date-picker');
        $page->assertScript('document.activeElement.id', 'date-picker');
    });

    it('supports keyboard opening and arrow-key day navigation', function () {
        $page = visit('/logbook');

        // Enter on the focused input opens the calendar with focus in the grid.
        $page->script(<<<'JS'
            const input = document.getElementById('date-picker');
            input.focus();
            input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
        JS);
        $page->assertVisible('.date-picker');
        $page->assertScript('document.activeElement.classList.contains("dp-day")', true);

        // ArrowRight moves focus one day forward.
        $page->script(<<<'JS'
            window.__dpBefore = document.activeElement.dataset.date;
            document.activeElement.dispatchEvent(
                new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }),
            );
        JS);
        $page->assertScript(
            'document.activeElement.dataset.date > window.__dpBefore',
            true,
        );
    });
});

describe('date picker edit mode', function () {
    it('initializes the single-date picker with the flash date', function () {
        Flash::factory()->sailing()->forUser($this->user)->onDate('2027-01-10')->create();

        $page = visit('/logbook');
        $page->click('Edit');
        $page->assertVisible('.modal.modal-open');
        $page->assertVisible('#date-picker-single');
        $page->assertValue('#date-picker-single', '2027-01-10');
    });

    it('keeps the edited date selectable while other entries stay locked', function () {
        Flash::factory()->sailing()->forUser($this->user)->onDate('2027-01-10')->create();
        Flash::factory()->sailing()->forUser($this->user)->onDate('2027-01-11')->create();

        $page = visit('/logbook');
        trackLivewireRequests($page);
        // The list is newest-first, so the first Edit button opens Jan 11.
        $page->click('Edit');
        $page->assertVisible('.modal.modal-open');

        $page->click('#date-picker-single');
        $page->assertVisible('.date-picker');

        // Opens on the month of the selected date — no navigation needed.
        $page->assertSeeIn('.dp-title', 'January');
        $page->assertScript('document.querySelector(\'.dp-day[data-date="2027-01-11"]\').disabled', false);
        $page->assertScript('document.querySelector(\'.dp-day[data-date="2027-01-10"]\').disabled', true);

        // Picking a new date closes the calendar (single mode) and updates the input.
        $page->script("document.querySelector('.dp-day[data-date=\"2027-01-12\"]').click()");
        settleLivewire($page);
        $page->assertNotPresent('.date-picker');
        $page->assertValue('#date-picker-single', '2027-01-12');
    });
});
