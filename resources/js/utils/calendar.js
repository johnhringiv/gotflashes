// Pure date math for the logbook date picker.
//
// Everything works on 'Y-m-d' strings (whose lexicographic order equals date
// order) or {year, month} pairs. Date objects are only ever constructed with
// new Date(year, monthIndex, day) — never by parsing a string — so nothing
// here is sensitive to the runtime timezone.

export const MONTH_NAMES = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];

export const WEEKDAY_NAMES = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

/** Build a 'Y-m-d' string from parts (month is 1-12). */
export function toISO(year, month, day) {
    return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

/** Parse a 'Y-m-d' string into integer parts (month is 1-12). */
export function fromISO(iso) {
    const [year, month, day] = iso.split('-').map((part) => parseInt(part, 10));
    return { year, month, day };
}

/** Today's local date as 'Y-m-d'. */
export function todayISO() {
    const now = new Date();
    return toISO(now.getFullYear(), now.getMonth() + 1, now.getDate());
}

/** 'Y-m-d' plus n days (n may be negative). */
export function addDaysISO(iso, n) {
    const { year, month, day } = fromISO(iso);
    const date = new Date(year, month - 1, day + n);
    return toISO(date.getFullYear(), date.getMonth() + 1, date.getDate());
}

/** 'Y-m-d' plus n months, clamping the day to the target month's length. */
export function addMonthsISO(iso, n) {
    const { year, month, day } = fromISO(iso);
    const target = new Date(year, month - 1 + n, 1);
    const lastDay = new Date(target.getFullYear(), target.getMonth() + 1, 0).getDate();
    return toISO(target.getFullYear(), target.getMonth() + 1, Math.min(day, lastDay));
}

/** Clamp a 'Y-m-d' string into [minIso, maxIso]. */
export function clampISO(iso, minIso, maxIso) {
    if (iso < minIso) return minIso;
    if (iso > maxIso) return maxIso;
    return iso;
}

/** 'January 17, 2027' — used for day button aria-labels. */
export function formatLongDate(iso) {
    const { year, month, day } = fromISO(iso);
    return `${MONTH_NAMES[month - 1]} ${day}, ${year}`;
}

/**
 * Build the day grid for one month as an array of weeks (Sunday-first), each
 * week an array of 7 day cells:
 *
 *   { iso, day, inMonth, disabled, hasEntry, isToday }
 *
 * Only weeks containing at least one day of the target month are generated
 * (the grid runs from the week of the 1st through the week of the last day),
 * so a "hide extra weeks" pass is never needed. Adjacent-month days appear
 * only in the partial first/last weeks.
 *
 * - disabled: outside [minIso, maxIso], or an existing-entry day
 * - hasEntry: the date already has a logged flash (editableIso — the date of
 *   the flash being edited — is exempt so edit mode can keep it selectable)
 */
export function buildMonthGrid(year, month, { minIso, maxIso, existingDates = [], editableIso = null, todayIso = todayISO() }) {
    const existing = existingDates instanceof Set ? existingDates : new Set(existingDates);
    const startOffset = new Date(year, month - 1, 1).getDay(); // 0 = Sunday
    const daysInMonth = new Date(year, month, 0).getDate();
    const cellCount = Math.ceil((startOffset + daysInMonth) / 7) * 7;

    const weeks = [];
    let week = [];
    for (let i = 0; i < cellCount; i++) {
        const date = new Date(year, month - 1, 1 - startOffset + i);
        const iso = toISO(date.getFullYear(), date.getMonth() + 1, date.getDate());
        const hasEntry = existing.has(iso) && iso !== editableIso;
        week.push({
            iso,
            day: date.getDate(),
            inMonth: date.getMonth() === month - 1,
            disabled: iso < minIso || iso > maxIso || hasEntry,
            hasEntry,
            isToday: iso === todayIso,
        });
        if (week.length === 7) {
            weeks.push(week);
            week = [];
        }
    }
    return weeks;
}
