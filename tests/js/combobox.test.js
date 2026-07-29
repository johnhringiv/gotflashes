import { describe, it, expect, beforeEach, vi } from 'vitest';
import { filterOptions, initCombobox } from '../../resources/js/utils/combobox.js';

/**
 * Exercises the REAL Combobox against a real <select> in happy-dom — no mocks.
 */

function buildSelect({ allowEmpty = false } = {}) {
    document.body.innerHTML = `
        <select id="fleet-select"${allowEmpty ? ' data-allow-empty="true"' : ''}>
            <option value="">Select fleet...</option>
            <option value="10" data-district-id="1">Fleet 1 - Alpha</option>
            <option value="20" data-district-id="1">Fleet 2 - Beta</option>
            <option value="30" data-district-id="2">Fleet 3 - Gamma</option>
            <option value="90" data-none>None</option>
        </select>
    `;
    return document.getElementById('fleet-select');
}

const listbox = () => document.querySelector('.combobox-listbox');
const optionLabels = () =>
    Array.from(document.querySelectorAll('.combobox-option')).map((li) => li.textContent);

describe('filterOptions (pure)', () => {
    const options = [
        { value: '1', label: 'Fleet 1 - Alpha' },
        { value: '2', label: 'Fleet 12 - Beta Bay' },
        { value: '9', label: 'None' },
    ];

    it('returns everything for an empty or whitespace query', () => {
        expect(filterOptions(options, '')).toHaveLength(3);
        expect(filterOptions(options, '   ')).toHaveLength(3);
    });

    it('matches case-insensitive substrings anywhere in the label', () => {
        expect(filterOptions(options, 'beta').map((o) => o.value)).toEqual(['2']);
        expect(filterOptions(options, 'FLEET 1')).toHaveLength(2); // "Fleet 1" and "Fleet 12"
        expect(filterOptions(options, 'none').map((o) => o.value)).toEqual(['9']);
    });

    it('matches by number substring', () => {
        expect(filterOptions(options, '12').map((o) => o.value)).toEqual(['2']);
    });
});

describe('Combobox', () => {
    let select;
    let combobox;
    let input;

    beforeEach(() => {
        select = buildSelect();
        combobox = initCombobox(select, { placeholder: 'Select fleet...' });
        input = document.getElementById('fleet-select-input');
    });

    it('hides the select and inserts a combobox input after it', () => {
        expect(select.hidden).toBe(true);
        expect(input).toBeTruthy();
        expect(input.getAttribute('role')).toBe('combobox');
        expect(input.placeholder).toBe('Select fleet...');
        expect(select.nextElementSibling).toBe(input);
    });

    it('is idempotent — a second init returns the same instance', () => {
        expect(initCombobox(select, {})).toBe(combobox);
        expect(document.querySelectorAll('.combobox-input')).toHaveLength(1);
    });

    it('opens on click, listing every option except the empty placeholder', () => {
        input.dispatchEvent(new Event('click'));
        expect(listbox()).toBeTruthy();
        expect(input.getAttribute('aria-expanded')).toBe('true');
        expect(optionLabels()).toEqual([
            'Fleet 1 - Alpha', 'Fleet 2 - Beta', 'Fleet 3 - Gamma', 'None',
        ]);
    });

    it('includes the empty option as a row when data-allow-empty is set', () => {
        select = buildSelect({ allowEmpty: true });
        combobox = initCombobox(select, {});
        input = document.getElementById('fleet-select-input');
        input.dispatchEvent(new Event('click'));
        expect(optionLabels()[0]).toBe('Select fleet...');
    });

    it('filters as the user types', () => {
        input.dispatchEvent(new Event('click'));
        input.value = 'gam';
        input.dispatchEvent(new Event('input'));
        expect(optionLabels()).toEqual(['Fleet 3 - Gamma']);
    });

    it('shows "No matches" for a query with no hits', () => {
        input.dispatchEvent(new Event('click'));
        input.value = 'zzz';
        input.dispatchEvent(new Event('input'));
        expect(optionLabels()).toEqual([]);
        expect(document.querySelector('.combobox-empty').textContent).toBe('No matches');
    });

    it('applies the extraFilter hook on every open', () => {
        select = buildSelect();
        let district = '1';
        combobox = initCombobox(select, {
            extraFilter: (o) => o.dataset.districtId === district || o.dataset.none !== undefined,
        });
        input = document.getElementById('fleet-select-input');

        input.dispatchEvent(new Event('click'));
        expect(optionLabels()).toEqual(['Fleet 1 - Alpha', 'Fleet 2 - Beta', 'None']);

        combobox.close();
        district = '2';
        input.dispatchEvent(new Event('click'));
        expect(optionLabels()).toEqual(['Fleet 3 - Gamma', 'None']);
    });

    it('picking a row (tap/click) sets the select value, dispatches change, and shows the label', () => {
        const onChange = vi.fn();
        select.addEventListener('change', onChange);
        input.dispatchEvent(new Event('click'));
        const row = document.querySelector('[data-value="20"]');
        row.dispatchEvent(new Event('click', { bubbles: true }));
        expect(select.value).toBe('20');
        expect(onChange).toHaveBeenCalledTimes(1);
        expect(input.value).toBe('Fleet 2 - Beta');
        expect(listbox()).toBeNull();
    });

    it('mouse pointerdown picks immediately; touch pointerdown does not (scroll gesture)', () => {
        input.dispatchEvent(new Event('click'));
        const touchDown = new Event('pointerdown', { bubbles: true });
        Object.defineProperty(touchDown, 'pointerType', { value: 'touch' });
        document.querySelector('[data-value="20"]').dispatchEvent(touchDown);
        // A finger landing on a row to scroll must NOT select it
        expect(select.value).toBe('');
        expect(listbox()).toBeTruthy();

        const mouseDown = new Event('pointerdown', { bubbles: true });
        Object.defineProperty(mouseDown, 'pointerType', { value: 'mouse' });
        document.querySelector('[data-value="30"]').dispatchEvent(mouseDown);
        expect(select.value).toBe('30');
        expect(listbox()).toBeNull();
    });

    it('suppresses the virtual keyboard until a second tap opts into typing', () => {
        expect(input.inputMode).toBe('none'); // opening tap: browse, no keyboard
        input.dispatchEvent(new Event('click'));
        expect(input.inputMode).toBe('none');
        input.dispatchEvent(new Event('click')); // second tap while open
        expect(input.inputMode).toBe('text');
        expect(listbox()).toBeTruthy(); // opting in never closes the list
        combobox.close();
        expect(input.inputMode).toBe('none'); // reset for the next open
    });

    it('navigates with arrows and commits with Enter', () => {
        input.dispatchEvent(new Event('click'));
        input.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown' }));
        input.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown' }));
        input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter' }));
        expect(select.value).toBe('30'); // opened on first row, moved down twice
        expect(input.value).toBe('Fleet 3 - Gamma');
    });

    it('Escape closes and reverts the text to the selected label', () => {
        combobox.setValue('10', { silent: true });
        input.dispatchEvent(new Event('click'));
        input.value = 'gam';
        input.dispatchEvent(new Event('input'));
        input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        expect(listbox()).toBeNull();
        expect(select.value).toBe('10'); // typing never destroyed the selection
        expect(input.value).toBe('Fleet 1 - Alpha');
    });

    it('light dismiss reverts abandoned typing', () => {
        combobox.setValue('10', { silent: true });
        input.dispatchEvent(new Event('click'));
        input.value = 'bet';
        input.dispatchEvent(new Event('input'));
        document.body.dispatchEvent(new Event('pointerdown', { bubbles: true }));
        expect(listbox()).toBeNull();
        expect(select.value).toBe('10');
        expect(input.value).toBe('Fleet 1 - Alpha');
    });

    it('committing an emptied field clears the selection', () => {
        const onChange = vi.fn();
        combobox.setValue('10', { silent: true });
        select.addEventListener('change', onChange);
        input.dispatchEvent(new Event('click'));
        input.value = '';
        input.dispatchEvent(new Event('input'));
        input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter' }));
        expect(select.value).toBe('');
        expect(onChange).toHaveBeenCalledTimes(1); // clear syncs like a pick
        expect(input.value).toBe('');
    });

    it('re-reads options from the select DOM on every open', () => {
        input.dispatchEvent(new Event('click'));
        expect(optionLabels()).toHaveLength(4);
        combobox.close();

        const extra = document.createElement('option');
        extra.value = '40';
        extra.textContent = 'Fleet 4 - Delta';
        select.appendChild(extra);

        input.dispatchEvent(new Event('click'));
        expect(optionLabels()).toHaveLength(5);
    });

    it('setValue / clear update select and input; silent skips the change event', () => {
        const onChange = vi.fn();
        select.addEventListener('change', onChange);
        combobox.setValue('30');
        expect(onChange).toHaveBeenCalledTimes(1);
        expect(input.value).toBe('Fleet 3 - Gamma');
        combobox.clear({ silent: true });
        expect(onChange).toHaveBeenCalledTimes(1);
        expect(select.value).toBe('');
        expect(input.value).toBe('');
    });

    it('moves the active highlight with the pointer (no double-grey)', () => {
        combobox.setValue('10', { silent: true });
        input.dispatchEvent(new Event('click'));
        const selectedRow = document.querySelector('[data-value="10"]');
        expect(selectedRow.classList.contains('active')).toBe(true); // keyboard anchor on open

        const hoveredRow = document.querySelector('[data-value="30"]');
        hoveredRow.dispatchEvent(new Event('pointerover', { bubbles: true }));
        expect(hoveredRow.classList.contains('active')).toBe(true);
        expect(selectedRow.classList.contains('active')).toBe(false); // checkmark only
        expect(selectedRow.classList.contains('selected')).toBe(true);
    });

    it('refreshes the visible label when an external driver fires change on the select', () => {
        select.value = '20';
        select.dispatchEvent(new Event('change', { bubbles: true }));
        expect(input.value).toBe('Fleet 2 - Beta');
    });

    it('marks the selected row with aria-selected and highlights it on open', () => {
        combobox.setValue('20', { silent: true });
        input.dispatchEvent(new Event('click'));
        const row = document.querySelector('[data-value="20"]');
        expect(row.getAttribute('aria-selected')).toBe('true');
        expect(row.classList.contains('selected')).toBe(true);
        expect(row.classList.contains('active')).toBe(true);
        expect(input.getAttribute('aria-activedescendant')).toBe(row.id);
    });
});
