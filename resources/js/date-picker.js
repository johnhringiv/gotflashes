// Home-grown date picker for the logbook form (replaces flatpickr), covering
// only what the app uses: multi-date toggle selection (create mode), single
// date (edit mode), min/max range, existing-entry days shown as disabled
// lightning days, and month/year navigation.
//
// Integration contract (unchanged from the flatpickr version): the input
// carries data-mode, data-min-date, data-max-date, data-existing-dates and
// (edit mode) data-default-date; selections sync to Livewire via
// component.set('dates'/'date', …) so FlashForm's updated() hook clears
// validation errors.
//
// Livewire-proofing, by construction rather than by hooks:
// - Initialization is lazy (a delegated click/keydown on the input), so there
//   is nothing to re-run after wire:navigate or a morph.
// - The data attributes are re-read on EVERY open, so Livewire can morph them
//   freely while the picker is closed — no destroy/reinit dance.
// - The calendar element is appended to document.body only while open, so
//   morph never sees it (and it escapes the edit modal's overflow-y: auto).

import {
    MONTH_NAMES,
    WEEKDAY_NAMES,
    buildMonthGrid,
    fromISO,
    todayISO,
    addDaysISO,
    addMonthsISO,
    clampISO,
    formatLongDate,
} from './utils/calendar';

const PICKER_INPUTS = '#date-picker, #date-picker-single';

const pickers = new WeakMap();
let openPicker = null;

class DatePicker {
    constructor(input) {
        this.input = input;
        this.root = null; // calendar element, exists only while open
        // Seed from the rendered value: edit mode's input carries the flash's
        // date server-side; create mode starts empty.
        this.selected = input.value ? input.value.split(', ') : [];
        this.view = null; // { year, month } while open

        this.onDocumentPointerDown = this.onDocumentPointerDown.bind(this);
        this.reposition = this.reposition.bind(this);
    }

    // --- lifecycle ---------------------------------------------------------

    open({ focusGrid = false } = {}) {
        if (this.root) {
            if (focusGrid) this.focusInitial();
            return;
        }
        if (openPicker && openPicker !== this) openPicker.close();

        this.readConfig();

        const anchor = clampISO(
            this.selected[this.selected.length - 1] || this.editableIso || todayISO(),
            this.minIso,
            this.maxIso,
        );
        const { year, month } = fromISO(anchor);
        this.view = { year, month };

        this.root = document.createElement('div');
        this.root.className = 'date-picker';
        this.root.setAttribute('role', 'dialog');
        this.root.setAttribute('aria-label', this.mode === 'single' ? 'Choose date' : 'Choose dates');
        this.root.addEventListener('click', (e) => this.onCalendarClick(e));
        this.root.addEventListener('keydown', (e) => this.onCalendarKeydown(e));
        this.root.addEventListener('change', (e) => {
            if (e.target.classList.contains('dp-month-select')) {
                const [y, m] = e.target.value.split('-').map(Number);
                this.setView(y, m, '.dp-month-select');
            }
        });
        document.body.appendChild(this.root);

        this.render();
        this.position();

        document.addEventListener('pointerdown', this.onDocumentPointerDown, true);
        // Capture-phase scroll catches scrolling containers too (the edit
        // modal's overflow-y: auto box), not just the window.
        window.addEventListener('scroll', this.reposition, true);
        window.addEventListener('resize', this.reposition);
        openPicker = this;

        if (focusGrid) this.focusInitial();
    }

    close({ refocusInput = false } = {}) {
        if (!this.root) return;
        this.root.remove();
        this.root = null;
        document.removeEventListener('pointerdown', this.onDocumentPointerDown, true);
        window.removeEventListener('scroll', this.reposition, true);
        window.removeEventListener('resize', this.reposition);
        if (openPicker === this) openPicker = null;
        if (refocusInput) this.input.focus();
    }

    /** Reset selection and input display (after a successful save). */
    clear() {
        this.selected = [];
        this.input.value = '';
        this.close();
    }

    /** Re-read the data-attribute contract; called on every open. */
    readConfig() {
        this.mode = this.input.dataset.mode === 'single' ? 'single' : 'multiple';
        this.minIso = this.input.dataset.minDate;
        this.maxIso = this.input.dataset.maxDate;
        // The date of the flash being edited stays selectable even though it
        // is an existing entry.
        this.editableIso = this.mode === 'single' ? this.input.dataset.defaultDate || null : null;
        try {
            this.existingDates = new Set(JSON.parse(this.input.dataset.existingDates || '[]'));
        } catch (e) {
            this.existingDates = new Set();
            // eslint-disable-next-line no-console
            console.error('Failed to parse existing dates:', e);
            if (window.showToast) {
                window.showToast('error', 'Failed to load calendar dates. Please refresh the page.');
            }
        }
    }

    // --- rendering ---------------------------------------------------------

    render() {
        const { year, month } = this.view;
        const weeks = buildMonthGrid(year, month, {
            minIso: this.minIso,
            maxIso: this.maxIso,
            existingDates: this.existingDates,
            editableIso: this.editableIso,
        });

        const min = fromISO(this.minIso);
        const max = fromISO(this.maxIso);

        // One dropdown listing ONLY the selectable months of the whole range,
        // labeled "July 2026" — the year lives in the label (during the
        // January grace period the list simply spans the year boundary), so
        // the closed control reads month-then-year with the native caret at
        // the end and there is no separate year control.
        const options = [];
        let y = min.year;
        let m = min.month;
        while (y < max.year || (y === max.year && m <= max.month)) {
            options.push(`<option value="${y}-${m}"${y === year && m === month ? ' selected' : ''}>${MONTH_NAMES[m - 1]} ${y}</option>`);
            m += 1;
            if (m > 12) {
                m = 1;
                y += 1;
            }
        }
        const monthControl = `<select class="dp-month-select" aria-label="Month">${options.join('')}</select>`;

        const prevDisabled = year === min.year && month === min.month;
        const nextDisabled = year === max.year && month === max.month;

        const days = weeks
            .flat()
            .map((day) => {
                const isSelected = this.selected.includes(day.iso);
                const classes = ['dp-day'];
                if (!day.inMonth) classes.push('other-month');
                if (day.isToday) classes.push('today');
                if (day.hasEntry) classes.push('has-entry');
                if (isSelected) classes.push('selected');
                const label = formatLongDate(day.iso) + (day.hasEntry ? ' (already logged)' : '');
                return (
                    `<button type="button" class="${classes.join(' ')}" data-date="${day.iso}"` +
                    ` aria-label="${label}" aria-pressed="${isSelected}" tabindex="-1"` +
                    `${day.disabled ? ' disabled' : ''}>${day.day}</button>`
                );
            })
            .join('');

        this.root.innerHTML =
            `<div class="dp-header">` +
            `<button type="button" class="dp-nav" data-nav="-1" aria-label="Previous month"${prevDisabled ? ' disabled' : ''}>&lsaquo;</button>` +
            `<div class="dp-title">${monthControl}</div>` +
            `<button type="button" class="dp-nav" data-nav="1" aria-label="Next month"${nextDisabled ? ' disabled' : ''}>&rsaquo;</button>` +
            `</div>` +
            `<div class="dp-weekdays" aria-hidden="true">${WEEKDAY_NAMES.map((w) => `<span>${w}</span>`).join('')}</div>` +
            `<div class="dp-grid">${days}</div>`;

        // Roving tabindex anchor: a selected day in view, else today, else the
        // first selectable day of the month.
        const focusable =
            this.root.querySelector('.dp-day.selected:not([disabled])') ||
            this.root.querySelector('.dp-day.today:not([disabled])') ||
            this.root.querySelector('.dp-day:not([disabled]):not(.other-month)') ||
            this.root.querySelector('.dp-day:not([disabled])');
        if (focusable) focusable.setAttribute('tabindex', '0');
    }

    /** Change the visible month (clamped to the allowed range) and re-render. */
    setView(year, month, focusSelector = null) {
        const min = fromISO(this.minIso);
        const max = fromISO(this.maxIso);
        if (year < min.year || (year === min.year && month < min.month)) {
            ({ year, month } = { year: min.year, month: min.month });
        }
        if (year > max.year || (year === max.year && month > max.month)) {
            ({ year, month } = { year: max.year, month: max.month });
        }
        this.view = { year, month };
        this.render();
        if (focusSelector) {
            // The intended control may have become disabled by the re-render
            // (e.g. nav reached the range edge) — fall back to any nav button.
            const el =
                this.root.querySelector(`${focusSelector}:not([disabled])`) ||
                this.root.querySelector('.dp-nav:not([disabled])');
            if (el) el.focus();
        }
    }

    position() {
        if (!this.input.isConnected) {
            this.close();
            return;
        }
        const rect = this.input.getBoundingClientRect();
        const height = this.root.offsetHeight;
        const width = this.root.offsetWidth;

        // Below the input, flipping above when there's no room below but there
        // is above; absolute page coordinates track normal page scrolling.
        let top = rect.bottom + window.scrollY + 4;
        if (rect.bottom + height + 4 > window.innerHeight && rect.top - height - 4 > 0) {
            top = rect.top + window.scrollY - height - 4;
        }
        const maxLeft = window.scrollX + document.documentElement.clientWidth - width - 8;
        const left = Math.max(window.scrollX + 8, Math.min(rect.left + window.scrollX, maxLeft));

        this.root.style.top = `${top}px`;
        this.root.style.left = `${left}px`;
    }

    reposition() {
        if (this.root) this.position();
    }

    // --- selection ---------------------------------------------------------

    select(iso) {
        if (this.mode === 'single') {
            this.selected = [iso];
            this.input.value = iso;
            this.sync('date', iso);
            this.close({ refocusInput: true });
            return;
        }
        const index = this.selected.indexOf(iso);
        if (index >= 0) {
            this.selected.splice(index, 1);
        } else {
            this.selected.push(iso);
            this.selected.sort();
        }
        this.input.value = this.selected.join(', ');
        this.sync('dates', [...this.selected]);
        this.render();
        this.focusDay(iso);
    }

    /**
     * Push the selection into the owning Livewire component so server-side
     * validation state stays in sync. (The old hidden-input fallback for
     * non-Livewire forms was dropped: the only consumer is FlashForm.)
     */
    sync(prop, value) {
        const componentRoot = this.input.closest('[wire\\:id]');
        if (!componentRoot || !window.Livewire) return;
        const component = window.Livewire.find(componentRoot.getAttribute('wire:id'));
        if (component) component.set(prop, value);
    }

    // --- events ------------------------------------------------------------

    onCalendarClick(e) {
        const nav = e.target.closest('.dp-nav');
        if (nav) {
            const delta = parseInt(nav.dataset.nav, 10);
            const target = addMonthsISO(`${this.view.year}-${String(this.view.month).padStart(2, '0')}-01`, delta);
            const { year, month } = fromISO(target);
            this.setView(year, month, `.dp-nav[data-nav="${delta}"]`);
            return;
        }
        const day = e.target.closest('.dp-day');
        if (day) this.select(day.dataset.date);
    }

    onCalendarKeydown(e) {
        if (e.key === 'Escape') {
            // Don't let the edit modal (or anything else) also act on it.
            e.stopPropagation();
            this.close({ refocusInput: true });
            return;
        }
        if (e.key === 'Tab') {
            // Refocus the input and let the browser's default Tab move on from
            // there — forward lands on the field after the input, Shift+Tab
            // before it.
            this.close();
            this.input.focus();
            return;
        }

        const day = e.target.closest('.dp-day');
        if (!day) return;
        const iso = day.dataset.date;
        const arrows = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };

        if (e.key in arrows) {
            e.preventDefault();
            this.moveFocus(iso, arrows[e.key]);
            return;
        }

        let target = null;
        const parts = fromISO(iso);
        const weekday = new Date(parts.year, parts.month - 1, parts.day).getDay();
        if (e.key === 'Home') target = addDaysISO(iso, -weekday);
        else if (e.key === 'End') target = addDaysISO(iso, 6 - weekday);
        else if (e.key === 'PageUp') target = addMonthsISO(iso, -1);
        else if (e.key === 'PageDown') target = addMonthsISO(iso, 1);
        if (!target) return;

        e.preventDefault();
        target = clampISO(target, this.minIso, this.maxIso);
        if (!this.isDisabled(target)) this.focusDay(target);
    }

    onDocumentPointerDown(e) {
        if (!this.root) return;
        if (this.root.contains(e.target) || e.target === this.input) return;
        this.close();
    }

    // --- focus helpers -----------------------------------------------------

    isDisabled(iso) {
        if (iso < this.minIso || iso > this.maxIso) return true;
        return this.existingDates.has(iso) && iso !== this.editableIso;
    }

    /** Arrow-key movement; steps over disabled (existing-entry) days. */
    moveFocus(fromIso, delta) {
        let iso = addDaysISO(fromIso, delta);
        while (iso >= this.minIso && iso <= this.maxIso && this.isDisabled(iso)) {
            iso = addDaysISO(iso, delta);
        }
        if (iso < this.minIso || iso > this.maxIso) return;
        this.focusDay(iso);
    }

    /** Focus a specific day, navigating the view to its month if needed. */
    focusDay(iso) {
        const { year, month } = fromISO(iso);
        if (year !== this.view.year || month !== this.view.month) {
            this.view = { year, month };
            this.render();
        }
        const btn = this.root.querySelector(`.dp-day[data-date="${iso}"]:not([disabled])`);
        if (!btn) return;
        this.root.querySelectorAll('.dp-day[tabindex="0"]').forEach((b) => b.setAttribute('tabindex', '-1'));
        btn.setAttribute('tabindex', '0');
        btn.focus();
    }

    focusInitial() {
        const btn = this.root.querySelector('.dp-day[tabindex="0"]');
        if (btn) btn.focus();
    }
}

function pickerFor(input) {
    let picker = pickers.get(input);
    if (!picker) {
        picker = new DatePicker(input);
        pickers.set(input, picker);
        // Test/debug handle, mirroring flatpickr's el._flatpickr convention.
        input._datePicker = picker;
    }
    return picker;
}

// Delegated activation — works for the always-present create input and the
// edit modal's input (which Livewire adds/removes) alike, with no init step.
document.addEventListener('click', (e) => {
    const input = e.target.closest(PICKER_INPUTS);
    if (input) pickerFor(input).open();
});

document.addEventListener('keydown', (e) => {
    const input = e.target.closest(PICKER_INPUTS);
    if (input && (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown')) {
        e.preventDefault();
        pickerFor(input).open({ focusGrid: true });
    }
});

document.addEventListener('livewire:init', () => {
    // After a successful save the server resets its `dates` property; clear
    // the JS-owned selection and display to match. (No handler is needed for
    // flash-deleted: the picker re-reads data-existing-dates on next open.)
    window.Livewire.on('flash-saved', () => {
        const input = document.getElementById('date-picker');
        if (!input) return;
        const picker = pickers.get(input);
        if (picker) picker.clear();
        else input.value = '';
    });
});

// If a wire:navigate page swap happens while a calendar is open, close it so
// it doesn't linger over the new page.
document.addEventListener('livewire:navigated', () => {
    if (openPicker) openPicker.close();
});
