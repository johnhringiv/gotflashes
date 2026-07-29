import { describe, it, expect, vi } from 'vitest';
import { initializeDistrictFleetSelects } from '../../resources/js/utils/district-fleet-select.js';

/**
 * Exercises the REAL glue + REAL combobox against real <select>s in happy-dom —
 * no TomSelect mock, no fetch mock (options are server-rendered now).
 *
 * Fixture mirrors user-profile-fields.blade.php: Unaffiliated/None is a real
 * district (99) and fleet (90) row marked data-none; per-fleet district ids
 * ride on data-district-id.
 */

function buildDom({ isProfile = false, districtValue = '', fleetValue = '' } = {}) {
    document.body.innerHTML = `
        <select id="district-select" data-is-profile="${isProfile}">
            <option value="">Select district...</option>
            <option value="1">District 1</option>
            <option value="2">District 2</option>
            <option value="99" data-none>Unaffiliated/None</option>
        </select>
        <select id="fleet-select" data-is-profile="${isProfile}">
            <option value="">Select fleet...</option>
            <option value="90" data-district-id="99" data-none>None</option>
            <option value="10" data-district-id="1">Fleet 1 - Fleet One</option>
            <option value="20" data-district-id="1">Fleet 2 - Fleet Two</option>
            <option value="30" data-district-id="2">Fleet 3 - Fleet Three</option>
        </select>
    `;
    const districtSelect = document.getElementById('district-select');
    const fleetSelect = document.getElementById('fleet-select');
    if (districtValue) districtSelect.value = districtValue;
    if (fleetValue) fleetSelect.value = fleetValue;
    return { districtSelect, fleetSelect };
}

function init(callbacks = {}) {
    return initializeDistrictFleetSelects({
        districtSelectId: 'district-select',
        fleetSelectId: 'fleet-select',
        ...callbacks,
    });
}

const openFleet = () => {
    document.getElementById('fleet-select-input').dispatchEvent(new Event('click'));
    return Array.from(document.querySelectorAll('.combobox-option')).map((li) => li.textContent);
};

const pickDistrict = (districtSelect, value) => {
    districtSelect.value = value;
    districtSelect.dispatchEvent(new Event('change', { bubbles: true }));
};

describe('Initialization', () => {
    it('enhances both selects with comboboxes and returns handles', () => {
        buildDom();
        const result = init();
        expect(result.districtCombobox).toBeDefined();
        expect(result.fleetCombobox).toBeDefined();
        expect(document.getElementById('district-select-input')).toBeTruthy();
        expect(document.getElementById('fleet-select-input')).toBeTruthy();
        expect(result.districtSelect.hidden).toBe(true);
    });

    it('returns the existing wiring on a repeat call', () => {
        buildDom();
        const first = init();
        expect(init().fleetCombobox).toBe(first.fleetCombobox);
    });

    it('returns null if the selects are missing', () => {
        document.body.innerHTML = '';
        expect(init()).toBeNull();
    });
});

describe('Profile page behavior (data-is-profile="true")', () => {
    it('falls back to the None district and fleet when values are empty', () => {
        buildDom({ isProfile: true });
        const onDistrictChange = vi.fn();
        const onFleetChange = vi.fn();
        init({ onDistrictChange, onFleetChange });

        expect(document.getElementById('district-select').value).toBe('99');
        expect(document.getElementById('fleet-select').value).toBe('90');
        expect(document.getElementById('district-select-input').value).toBe('Unaffiliated/None');
        // Non-silent so the Livewire properties stay in sync with the UI
        expect(onDistrictChange).toHaveBeenCalledWith('99');
        expect(onFleetChange).toHaveBeenCalledWith('90');
    });

    it('preserves existing values', () => {
        buildDom({ isProfile: true, districtValue: '1', fleetValue: '20' });
        init();
        expect(document.getElementById('district-select').value).toBe('1');
        expect(document.getElementById('fleet-select').value).toBe('20');
        expect(document.getElementById('fleet-select-input').value).toBe('Fleet 2 - Fleet Two');
    });
});

describe('Signup page behavior (data-is-profile="false")', () => {
    it('leaves empty values alone so placeholders show', () => {
        buildDom();
        init();
        expect(document.getElementById('district-select').value).toBe('');
        expect(document.getElementById('fleet-select').value).toBe('');
        expect(document.getElementById('district-select-input').value).toBe('');
        expect(document.getElementById('fleet-select-input').value).toBe('');
    });
});

describe('District change behavior', () => {
    it('narrows the fleet list to that district plus the None fleet', () => {
        const { districtSelect } = buildDom();
        init();
        pickDistrict(districtSelect, '1');
        expect(openFleet()).toEqual(['None', 'Fleet 1 - Fleet One', 'Fleet 2 - Fleet Two']);
    });

    it('shows every fleet for the Unaffiliated/None district', () => {
        const { districtSelect } = buildDom();
        const onDistrictChange = vi.fn();
        init({ onDistrictChange });
        pickDistrict(districtSelect, '99');
        expect(onDistrictChange).toHaveBeenCalledWith('99');
        expect(openFleet()).toHaveLength(4);
    });

    it('clears the fleet selection and syncs null', () => {
        const { districtSelect, fleetSelect } = buildDom({ districtValue: '1', fleetValue: '10' });
        const onFleetChange = vi.fn();
        init({ onFleetChange });
        pickDistrict(districtSelect, '2');
        expect(fleetSelect.value).toBe('');
        expect(document.getElementById('fleet-select-input').value).toBe('');
        expect(onFleetChange).toHaveBeenCalledWith(null);
    });

    it('clearing the district widens the fleet list to every fleet', () => {
        const { districtSelect } = buildDom({ districtValue: '1' });
        init();
        districtSelect._combobox.clear(); // the combobox empty-commit gesture
        expect(openFleet()).toHaveLength(4);
    });

    it('syncs the picked district id', () => {
        const { districtSelect } = buildDom();
        const onDistrictChange = vi.fn();
        init({ onDistrictChange });
        pickDistrict(districtSelect, '1');
        expect(onDistrictChange).toHaveBeenCalledWith('1');
    });
});

describe('Fleet change behavior', () => {
    it('auto-fills the district from the picked fleet without clearing the pick', () => {
        buildDom();
        const onDistrictChange = vi.fn();
        const onFleetChange = vi.fn();
        const { fleetCombobox } = init({ onDistrictChange, onFleetChange });

        fleetCombobox.setValue('30'); // Fleet 3 belongs to District 2

        expect(document.getElementById('district-select').value).toBe('2');
        expect(document.getElementById('district-select-input').value).toBe('District 2');
        expect(document.getElementById('fleet-select').value).toBe('30');
        expect(onDistrictChange).toHaveBeenCalledWith('2');
        expect(onFleetChange).toHaveBeenCalledWith('30');
    });

    it('sets the district to None when the None fleet is picked with a blank district', () => {
        buildDom();
        const onDistrictChange = vi.fn();
        const onFleetChange = vi.fn();
        const { fleetCombobox } = init({ onDistrictChange, onFleetChange });

        fleetCombobox.setValue('90');

        expect(document.getElementById('district-select').value).toBe('99');
        expect(onDistrictChange).toHaveBeenCalledWith('99');
        expect(onFleetChange).toHaveBeenCalledWith('90');
    });

    it('leaves an already-chosen district alone when the None fleet is picked', () => {
        buildDom({ districtValue: '1' });
        const onDistrictChange = vi.fn();
        const { fleetCombobox } = init({ onDistrictChange });

        fleetCombobox.setValue('90');

        expect(document.getElementById('district-select').value).toBe('1');
        expect(onDistrictChange).not.toHaveBeenCalled();
    });

    it('syncs null when the fleet is cleared', () => {
        buildDom({ fleetValue: '10' });
        const onFleetChange = vi.fn();
        const { fleetCombobox } = init({ onFleetChange });
        fleetCombobox.clear();
        expect(onFleetChange).toHaveBeenCalledWith(null);
    });
});
