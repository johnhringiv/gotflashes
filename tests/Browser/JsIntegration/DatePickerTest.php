<?php

use App\Models\Flash;
use App\Models\User;

// Server time is frozen mid-January so the grace period is active (min =
// Jan 1 of the previous year, max = frozen today +1). Every date below is
// DERIVED from that one frozen instant — no other hardcoded years. The
// browser still runs on REAL time, so the calendar opens on the real current
// month and tests navigate explicitly via _datePicker.setView().

beforeEach(function () {
    $this->travelTo(frozenJanuary());
    $this->year = now()->year;
    $this->prevYear = $this->year - 1;
    $this->ym = now()->format('Y-m'); // frozen year-month ("Y-m")
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
        $page->script("document.querySelector('#date-picker')._datePicker.setView({$this->year}, 1)");

        $page->script("document.querySelector('.dp-day[data-date=\"{$this->ym}-05\"]').click()");
        $page->script("document.querySelector('.dp-day[data-date=\"{$this->ym}-06\"]').click()");
        settleLivewire($page);

        $page->assertValue('#date-picker', "{$this->ym}-05, {$this->ym}-06");
        $page->assertScript('document.querySelector("#date-picker")._datePicker.selected.length', 2);

        // Clicking a selected day again deselects it.
        $page->script("document.querySelector('.dp-day[data-date=\"{$this->ym}-05\"]').click()");
        settleLivewire($page);
        $page->assertValue('#date-picker', "{$this->ym}-06");
    });

    it('clears the picker after save — input empty, saved date becomes has-entry', function () {
        $page = visit('/logbook');
        trackLivewireRequests($page);

        $page->click('#date-picker');
        $page->assertVisible('.date-picker');
        $page->script("document.querySelector('#date-picker')._datePicker.setView({$this->year}, 1)");
        $page->script("document.querySelector('.dp-day[data-date=\"{$this->ym}-05\"]').click()");
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
        $page->script("document.querySelector('#date-picker')._datePicker.setView({$this->year}, 1)");
        $page->assertScript(
            "document.querySelector('.dp-day[data-date=\"{$this->ym}-05\"]').classList.contains('has-entry')",
            true,
        );
    });

    it('marks existing dates as locked has-entry days that stay focusable', function () {
        Flash::factory()->sailing()->forUser($this->user)->onDate("{$this->ym}-10")->create();
        Flash::factory()->sailing()->forUser($this->user)->onDate("{$this->ym}-11")->create();

        $page = visit('/logbook');
        $page->click('#date-picker');
        $page->assertVisible('.date-picker');
        $page->script("document.querySelector('#date-picker')._datePicker.setView({$this->year}, 1)");

        // aria-disabled (not the disabled attribute) so keyboard focus can
        // land on them and announce "(already logged)".
        $page->assertScript('document.querySelectorAll(".date-picker .dp-day.has-entry").length', 2);
        $page->assertScript(
            "document.querySelector('.dp-day[data-date=\"{$this->ym}-10\"]').getAttribute('aria-disabled')",
            'true',
        );
        $page->assertScript("document.querySelector('.dp-day[data-date=\"{$this->ym}-10\"]').disabled", false);
    });

    it('arrows onto logged days without selecting them', function () {
        Flash::factory()->sailing()->forUser($this->user)->onDate("{$this->ym}-10")->create();

        $page = visit('/logbook');
        $page->click('#date-picker');
        $page->script("document.querySelector('#date-picker')._datePicker.setView({$this->year}, 1)");

        // ArrowRight from the 9th lands ON the logged 10th (no skip)...
        $page->script(<<<JS
            const from = document.querySelector('.dp-day[data-date="{$this->ym}-09"]');
            from.focus();
            from.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));
        JS);
        $page->assertScript('document.activeElement.dataset.date', "{$this->ym}-10");

        // ...clicking/pressing it selects nothing...
        $page->script("document.querySelector('.dp-day[data-date=\"{$this->ym}-10\"]').click()");
        $page->assertValue('#date-picker', '');

        // ...and another ArrowRight continues past it.
        $page->script(<<<'JS'
            document.activeElement.dispatchEvent(
                new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }),
            );
        JS);
        $page->assertScript('document.activeElement.dataset.date', "{$this->ym}-11");
    });

    it('enforces min/max — tomorrow selectable, day-after-tomorrow disabled', function () {
        $tomorrow = now()->addDay()->toDateString();
        $dayAfter = now()->addDays(2)->toDateString();

        $page = visit('/logbook');
        $page->click('#date-picker');
        $page->script("document.querySelector('#date-picker')._datePicker.setView({$this->year}, 1)");

        $page->assertScript("document.querySelector('.dp-day[data-date=\"{$tomorrow}\"]').disabled", false);
        $page->assertScript("document.querySelector('.dp-day[data-date=\"{$dayAfter}\"]').disabled", true);
    });

    it('lists only selectable months, spanning years during the January grace period', function () {
        $page = visit('/logbook');
        $page->click('#date-picker');
        $page->assertVisible('.date-picker');

        // Grace range runs Jan 1 of the previous year through frozen January:
        // exactly 13 months, year in the labels — no separate year control,
        // no dead options.
        $page->assertScript('document.querySelectorAll(".dp-month-select option").length', 13);
        $page->assertScript('document.querySelectorAll(".dp-month-select option[disabled]").length', 0);
        $page->assertNotPresent('.dp-year-select');
        $page->assertScript(
            '(o => [o[0].textContent, o[o.length - 1].textContent].join("|"))(document.querySelectorAll(".dp-month-select option"))',
            "January {$this->prevYear}|January {$this->year}",
        );
    });

    it('navigates months with the header arrows', function () {
        $page = visit('/logbook');
        $page->click('#date-picker');
        $page->script("document.querySelector('#date-picker')._datePicker.setView({$this->year}, 1)");
        $page->assertScript('document.querySelector(".dp-month-select").value', "{$this->year}-1");

        // The frozen January is the max month, so only prev is enabled.
        $page->assertScript('document.querySelector(\'.dp-nav[data-nav="1"]\').disabled', true);
        $page->script("document.querySelector('.dp-nav[data-nav=\"-1\"]').click()");
        $page->assertScript('document.querySelector(".dp-month-select").value', "{$this->prevYear}-12");
    });

    it('jumps straight to a month in another year via the dropdown', function () {
        $page = visit('/logbook');
        $page->click('#date-picker');
        $page->script("document.querySelector('#date-picker')._datePicker.setView({$this->year}, 1)");

        $page->script(<<<JS
            const select = document.querySelector('.dp-month-select');
            select.value = '{$this->prevYear}-3';
            select.dispatchEvent(new Event('change', { bubbles: true }));
        JS);
        $page->assertPresent(".dp-day[data-date=\"{$this->prevYear}-03-15\"]");
        $page->assertScript('document.querySelector(".dp-month-select").value', "{$this->prevYear}-3");
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
        Flash::factory()->sailing()->forUser($this->user)->onDate("{$this->ym}-10")->create();

        $page = visit('/logbook');
        $page->click('Edit');
        $page->assertVisible('.modal.modal-open');
        $page->assertVisible('#date-picker-single');
        $page->assertValue('#date-picker-single', "{$this->ym}-10");
    });

    it('keeps the edited date selectable while other entries stay locked', function () {
        Flash::factory()->sailing()->forUser($this->user)->onDate("{$this->ym}-10")->create();
        Flash::factory()->sailing()->forUser($this->user)->onDate("{$this->ym}-11")->create();

        $page = visit('/logbook');
        trackLivewireRequests($page);
        // The list is newest-first, so the first Edit button opens the -11 flash.
        $page->click('Edit');
        $page->assertVisible('.modal.modal-open');

        $page->click('#date-picker-single');
        $page->assertVisible('.date-picker');

        // Opens on the month of the selected date — no navigation needed.
        $page->assertScript('document.querySelector(".dp-month-select").value', "{$this->year}-1");
        $page->assertScript("document.querySelector('.dp-day[data-date=\"{$this->ym}-11\"]').disabled", false);
        $page->assertScript(
            "document.querySelector('.dp-day[data-date=\"{$this->ym}-10\"]').getAttribute('aria-disabled')",
            'true',
        );

        // Picking a new date closes the calendar (single mode) and updates the input.
        $page->script("document.querySelector('.dp-day[data-date=\"{$this->ym}-12\"]').click()");
        settleLivewire($page);
        $page->assertNotPresent('.date-picker');
        $page->assertValue('#date-picker-single', "{$this->ym}-12");
    });
});
