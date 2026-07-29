// Unit tests for the date picker's pure date math (resources/js/utils/calendar.js).
// Unlike the flatpickr-era test file, these exercise the REAL exported functions
// the picker runs on, not re-implemented copies of the logic.

import { describe, it, expect } from 'vitest';
import {
    toISO,
    fromISO,
    addDaysISO,
    addMonthsISO,
    clampISO,
    formatLongDate,
    buildMonthGrid,
    MONTH_NAMES,
    WEEKDAY_NAMES,
} from '../../resources/js/utils/calendar.js';

describe('ISO helpers', () => {
    it('round-trips between parts and Y-m-d strings with zero padding', () => {
        expect(toISO(2027, 1, 5)).toBe('2027-01-05');
        expect(toISO(2027, 12, 31)).toBe('2027-12-31');
        expect(fromISO('2027-01-05')).toEqual({ year: 2027, month: 1, day: 5 });
    });

    it('adds days across month and year boundaries', () => {
        expect(addDaysISO('2027-01-31', 1)).toBe('2027-02-01');
        expect(addDaysISO('2027-01-01', -1)).toBe('2026-12-31');
        expect(addDaysISO('2028-02-28', 1)).toBe('2028-02-29'); // leap year
        expect(addDaysISO('2027-01-15', 7)).toBe('2027-01-22');
    });

    it('adds months, clamping the day to the target month length', () => {
        expect(addMonthsISO('2027-01-31', 1)).toBe('2027-02-28');
        expect(addMonthsISO('2027-03-15', -1)).toBe('2027-02-15');
        expect(addMonthsISO('2026-12-15', 1)).toBe('2027-01-15');
    });

    it('clamps into a min/max range', () => {
        expect(clampISO('2026-12-31', '2027-01-01', '2027-01-16')).toBe('2027-01-01');
        expect(clampISO('2027-02-01', '2027-01-01', '2027-01-16')).toBe('2027-01-16');
        expect(clampISO('2027-01-10', '2027-01-01', '2027-01-16')).toBe('2027-01-10');
    });

    it('formats aria-label dates', () => {
        expect(formatLongDate('2027-01-17')).toBe('January 17, 2027');
        expect(formatLongDate('2026-12-05')).toBe('December 5, 2026');
    });

    it('parses year from the string, immune to timezone conversion', () => {
        // new Date('2027-01-01') would shift to Dec 31 in negative-UTC zones;
        // fromISO never goes through Date parsing.
        expect(fromISO('2027-01-01').year).toBe(2027);
    });

    it('exposes English month and weekday names', () => {
        expect(MONTH_NAMES).toHaveLength(12);
        expect(WEEKDAY_NAMES).toEqual(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']);
    });
});

describe('buildMonthGrid', () => {
    const range = { minIso: '2026-01-01', maxIso: '2027-01-16', todayIso: '2027-01-15' };

    it('generates only weeks that touch the month (no all-adjacent extra weeks)', () => {
        // January 2027 starts on Friday and has 31 days: 5+31 = 36 cells → 6 weeks
        // exactly, none of them fully outside the month.
        const weeks = buildMonthGrid(2027, 1, range);
        expect(weeks).toHaveLength(6);
        weeks.forEach((week) => {
            expect(week).toHaveLength(7);
            expect(week.some((day) => day.inMonth)).toBe(true);
        });
        // February 2027 starts on Monday, 28 days: 1+28 = 29 cells → 5 weeks.
        expect(buildMonthGrid(2027, 2, range)).toHaveLength(5);
        // February 2026 starts on Sunday, 28 days → a perfect 4-week grid.
        expect(buildMonthGrid(2026, 2, range)).toHaveLength(4);
    });

    it('starts weeks on Sunday and pads with adjacent-month days', () => {
        const weeks = buildMonthGrid(2027, 1, range);
        const firstWeek = weeks[0];
        // Jan 1 2027 is a Friday: Sun Dec 27 … Thu Dec 31 pad the front.
        expect(firstWeek[0].iso).toBe('2026-12-27');
        expect(firstWeek[0].inMonth).toBe(false);
        expect(firstWeek[5].iso).toBe('2027-01-01');
        expect(firstWeek[5].inMonth).toBe(true);
    });

    it('disables days outside [min, max]', () => {
        const weeks = buildMonthGrid(2027, 1, range).flat();
        const jan16 = weeks.find((d) => d.iso === '2027-01-16');
        const jan17 = weeks.find((d) => d.iso === '2027-01-17');
        expect(jan16.disabled).toBe(false);
        expect(jan17.disabled).toBe(true); // beyond maxIso
        const dec31 = weeks.find((d) => d.iso === '2026-12-31');
        expect(dec31.disabled).toBe(false); // within min (grace period range)
    });

    it('marks existing-entry days as disabled hasEntry', () => {
        const weeks = buildMonthGrid(2027, 1, {
            ...range,
            existingDates: ['2027-01-10', '2027-01-11'],
        }).flat();
        const jan10 = weeks.find((d) => d.iso === '2027-01-10');
        expect(jan10.hasEntry).toBe(true);
        expect(jan10.disabled).toBe(true);
        expect(weeks.find((d) => d.iso === '2027-01-09').hasEntry).toBe(false);
    });

    it('exempts the date being edited from the existing-entry lock', () => {
        const weeks = buildMonthGrid(2027, 1, {
            ...range,
            existingDates: ['2027-01-10', '2027-01-11'],
            editableIso: '2027-01-10',
        }).flat();
        const jan10 = weeks.find((d) => d.iso === '2027-01-10');
        expect(jan10.hasEntry).toBe(false);
        expect(jan10.disabled).toBe(false);
        expect(weeks.find((d) => d.iso === '2027-01-11').disabled).toBe(true);
    });

    it('accepts existing dates as an array or a Set', () => {
        const fromArray = buildMonthGrid(2027, 1, { ...range, existingDates: ['2027-01-10'] }).flat();
        const fromSet = buildMonthGrid(2027, 1, { ...range, existingDates: new Set(['2027-01-10']) }).flat();
        expect(fromArray.find((d) => d.iso === '2027-01-10').hasEntry).toBe(true);
        expect(fromSet.find((d) => d.iso === '2027-01-10').hasEntry).toBe(true);
    });

    it('flags today', () => {
        const weeks = buildMonthGrid(2027, 1, range).flat();
        expect(weeks.find((d) => d.iso === '2027-01-15').isToday).toBe(true);
        expect(weeks.filter((d) => d.isToday)).toHaveLength(1);
    });
});
