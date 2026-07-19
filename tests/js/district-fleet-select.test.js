import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { initializeDistrictFleetSelects } from '../../resources/js/utils/district-fleet-select.js';

// Mock TomSelect
vi.mock('tom-select', () => {
    return {
        default: vi.fn().mockImplementation(function(selector, options) {
            this.selector = selector;
            this.options = options;
            this.value = null;
            // Initialize optionsList with options passed during construction
            this.optionsList = options.options ? [...options.options] : [];

            this.addOption = vi.fn((option) => {
                this.optionsList.push(option);
            });

            this.clearOptions = vi.fn(() => {
                this.optionsList = [];
            });

            this.refreshOptions = vi.fn();

            this.setValue = vi.fn((value, silent) => {
                this.value = value;
                if (!silent && this.options.onChange) {
                    this.options.onChange.call(this, value);
                }
            });

            this.getValue = vi.fn(() => {
                return this.value;
            });

            this.clear = vi.fn(() => {
                this.value = null;
            });

            this.blur = vi.fn();

            return this;
        })
    };
});

// Mock fetch API
global.fetch = vi.fn();

describe('District and Fleet TomSelect Integration', () => {
    let districtSelect;
    let fleetSelect;
    let mockDistricts;
    let mockFleets;
    let onDistrictChangeSpy;
    let onFleetChangeSpy;

    beforeEach(() => {
        // Setup mock data
        // Unaffiliated/None is a real district (id 99) and fleet (id 90,
        // fleet_number 0); the API reports their ids alongside the lists
        mockDistricts = [
            { id: 1, name: 'District 1' },
            { id: 2, name: 'District 2' },
            { id: 99, name: 'Unaffiliated/None' }
        ];

        mockFleets = [
            { id: 1, fleet_number: '1', fleet_name: 'Fleet One', district_id: 1, district_name: 'District 1' },
            { id: 2, fleet_number: '2', fleet_name: 'Fleet Two', district_id: 1, district_name: 'District 1' },
            { id: 3, fleet_number: '3', fleet_name: 'Fleet Three', district_id: 2, district_name: 'District 2' },
            { id: 90, fleet_number: 0, fleet_name: 'None', district_id: 99, district_name: 'Unaffiliated/None' }
        ];

        // Mock fetch response
        global.fetch.mockResolvedValue({
            ok: true,
            json: async () => ({
                districts: mockDistricts,
                fleets: mockFleets,
                none_district_id: 99,
                none_fleet_id: 90
            })
        });

        // Setup DOM
        document.body.innerHTML = `
            <select id="district-select" data-value="" data-is-profile="false"></select>
            <select id="fleet-select" data-value="" data-is-profile="false"></select>
        `;

        districtSelect = document.getElementById('district-select');
        fleetSelect = document.getElementById('fleet-select');

        // Create spies for callbacks
        onDistrictChangeSpy = vi.fn();
        onFleetChangeSpy = vi.fn();
    });

    afterEach(() => {
        vi.clearAllMocks();
        document.body.innerHTML = '';
    });

    describe('Initialization', () => {
        it('should fetch districts and fleets from API', async () => {
            await initializeDistrictFleetSelects({
                districtSelectId: 'district-select',
                fleetSelectId: 'fleet-select'
            });

            expect(global.fetch).toHaveBeenCalledWith('/api/districts-and-fleets');
        });

        it('should initialize both TomSelect instances', async () => {
            const result = await initializeDistrictFleetSelects({
                districtSelectId: 'district-select',
                fleetSelectId: 'fleet-select'
            });

            expect(result).toBeTruthy();
            expect(result.districtTomSelect).toBeDefined();
            expect(result.fleetTomSelect).toBeDefined();
        });

        it('should include the Unaffiliated/None district from the API data', async () => {
            const result = await initializeDistrictFleetSelects({
                districtSelectId: 'district-select',
                fleetSelectId: 'fleet-select'
            });

            const districtOptions = result.districtTomSelect.optionsList;
            const noneOption = districtOptions.find(opt => opt.value === 99);

            expect(noneOption).toBeDefined();
            expect(noneOption.text).toBe('Unaffiliated/None');
        });

        it('should label the None fleet row "None"', async () => {
            const result = await initializeDistrictFleetSelects({
                districtSelectId: 'district-select',
                fleetSelectId: 'fleet-select'
            });

            const fleetOptions = result.fleetTomSelect.optionsList;
            const noneOption = fleetOptions.find(opt => opt.value === 90);

            expect(noneOption).toBeDefined();
            expect(noneOption.text).toBe('None');
        });
    });

    describe('Profile Page Behavior (data-is-profile="true")', () => {
        beforeEach(() => {
            districtSelect.dataset.isProfile = 'true';
            fleetSelect.dataset.isProfile = 'true';
        });

        it('should fall back to the None district when value is empty on profile page', async () => {
            districtSelect.dataset.value = '';

            const result = await initializeDistrictFleetSelects({
                districtSelectId: 'district-select',
                fleetSelectId: 'fleet-select'
            });

            // Non-silent so the Livewire property stays in sync with the UI
            expect(result.districtTomSelect.setValue).toHaveBeenCalledWith('99');
        });

        it('should fall back to the None fleet when value is empty on profile page', async () => {
            fleetSelect.dataset.value = '';

            const result = await initializeDistrictFleetSelects({
                districtSelectId: 'district-select',
                fleetSelectId: 'fleet-select'
            });

            expect(result.fleetTomSelect.setValue).toHaveBeenCalledWith('90');
        });

        it('should preserve existing district value on profile page', async () => {
            districtSelect.dataset.value = '1';

            const result = await initializeDistrictFleetSelects({
                districtSelectId: 'district-select',
                fleetSelectId: 'fleet-select'
            });

            expect(result.districtTomSelect.setValue).toHaveBeenCalledWith('1');
        });

        it('should preserve existing fleet value on profile page', async () => {
            fleetSelect.dataset.value = '2';

            const result = await initializeDistrictFleetSelects({
                districtSelectId: 'district-select',
                fleetSelectId: 'fleet-select'
            });

            expect(result.fleetTomSelect.setValue).toHaveBeenCalledWith('2');
        });
    });

    describe('Signup Page Behavior (data-is-profile="false")', () => {
        it('should NOT set district to "none" when value is empty on signup', async () => {
            districtSelect.dataset.value = '';
            districtSelect.dataset.isProfile = 'false';

            const result = await initializeDistrictFleetSelects({
                districtSelectId: 'district-select',
                fleetSelectId: 'fleet-select'
            });

            // Should not fall back to the None district for empty values on signup
            const setValueCalls = result.districtTomSelect.setValue.mock.calls;
            const noneCall = setValueCalls.find(call => call[0] === '99');
            expect(noneCall).toBeUndefined();
        });

        it('should NOT set fleet to "none" when value is empty on signup', async () => {
            fleetSelect.dataset.value = '';
            fleetSelect.dataset.isProfile = 'false';

            const result = await initializeDistrictFleetSelects({
                districtSelectId: 'district-select',
                fleetSelectId: 'fleet-select'
            });

            // Should not fall back to the None fleet for empty values on signup
            const setValueCalls = result.fleetTomSelect.setValue.mock.calls;
            const noneCall = setValueCalls.find(call => call[0] === '90');
            expect(noneCall).toBeUndefined();
        });
    });

    describe('Special Case: Auto-populate District when Fleet is None', () => {
        it('should set district to None when fleet is set to None and district is empty', async () => {
            const result = await initializeDistrictFleetSelects({
                districtSelectId: 'district-select',
                fleetSelectId: 'fleet-select',
                onDistrictChange: onDistrictChangeSpy,
                onFleetChange: onFleetChangeSpy
            });

            // Simulate user selecting the None fleet when district is empty
            result.districtTomSelect.getValue.mockReturnValue('');
            result.fleetTomSelect.options.onChange.call(result.fleetTomSelect, '90');

            expect(result.districtTomSelect.setValue).toHaveBeenCalledWith('99', true);
            expect(onDistrictChangeSpy).toHaveBeenCalledWith('99');
        });

        it('should NOT change district when fleet is set to None and district already has a value', async () => {
            const result = await initializeDistrictFleetSelects({
                districtSelectId: 'district-select',
                fleetSelectId: 'fleet-select'
            });

            // Simulate district already having a value
            result.districtTomSelect.getValue.mockReturnValue('1');

            // Clear previous setValue calls
            result.districtTomSelect.setValue.mockClear();

            // Simulate user selecting the None fleet
            result.fleetTomSelect.options.onChange.call(result.fleetTomSelect, '90');

            // Should NOT call setValue on district since it already has a value
            expect(result.districtTomSelect.setValue).not.toHaveBeenCalled();
        });

        it('should call onFleetChange with the None fleet id when fleet is set to None', async () => {
            const result = await initializeDistrictFleetSelects({
                districtSelectId: 'district-select',
                fleetSelectId: 'fleet-select',
                onFleetChange: onFleetChangeSpy
            });

            result.districtTomSelect.getValue.mockReturnValue('');
            result.fleetTomSelect.options.onChange.call(result.fleetTomSelect, '90');

            // The real None fleet id syncs like any other pick; the server
            // distinguishes an explicit choice from an auto-cleared field by
            // the value being non-empty.
            expect(onFleetChangeSpy).toHaveBeenCalledWith('90');
        });
    });

    describe('District Change Behavior', () => {
        it('should filter fleets when district changes', async () => {
            const result = await initializeDistrictFleetSelects({
                districtSelectId: 'district-select',
                fleetSelectId: 'fleet-select'
            });

            // Clear initial calls
            result.fleetTomSelect.clearOptions.mockClear();
            result.fleetTomSelect.addOption.mockClear();

            // Simulate district change to District 1
            result.districtTomSelect.options.onChange.call(result.districtTomSelect, '1');

            expect(result.fleetTomSelect.clearOptions).toHaveBeenCalled();

            // Should add District 1's fleets plus the always-available None fleet
            const addedOptions = result.fleetTomSelect.addOption.mock.calls.map(call => call[0]);
            const realFleets = addedOptions.filter(opt => opt.value !== 90);

            expect(realFleets.length).toBe(2); // Fleet 1 and Fleet 2
            expect(realFleets.every(opt => opt.district_id === 1)).toBe(true);
            expect(addedOptions.some(opt => opt.value === 90)).toBe(true);
        });

        it('should clear fleet selection when district changes', async () => {
            const result = await initializeDistrictFleetSelects({
                districtSelectId: 'district-select',
                fleetSelectId: 'fleet-select'
            });

            result.districtTomSelect.options.onChange.call(result.districtTomSelect, '2');

            expect(result.fleetTomSelect.clear).toHaveBeenCalled();
        });

        it('should call onDistrictChange callback with correct value', async () => {
            const result = await initializeDistrictFleetSelects({
                districtSelectId: 'district-select',
                fleetSelectId: 'fleet-select',
                onDistrictChange: onDistrictChangeSpy
            });

            result.districtTomSelect.options.onChange.call(result.districtTomSelect, '1');

            expect(onDistrictChangeSpy).toHaveBeenCalledWith('1');
        });

        it('should call onDistrictChange with "none" when "none" is selected', async () => {
            const result = await initializeDistrictFleetSelects({
                districtSelectId: 'district-select',
                fleetSelectId: 'fleet-select',
                onDistrictChange: onDistrictChangeSpy
            });

            result.districtTomSelect.options.onChange.call(result.districtTomSelect, 'none');

            // 'none' is sent literally for explicit "Unaffiliated" so the server
            // can distinguish it from a cleared/empty district.
            expect(onDistrictChangeSpy).toHaveBeenCalledWith('none');
        });
    });

    describe('Fleet Change Behavior', () => {
        it('should set district based on selected fleet', async () => {
            const result = await initializeDistrictFleetSelects({
                districtSelectId: 'district-select',
                fleetSelectId: 'fleet-select'
            });

            // Clear initial setValue calls
            result.districtTomSelect.setValue.mockClear();

            // Simulate selecting Fleet 3 (which belongs to District 2)
            result.fleetTomSelect.options.onChange.call(result.fleetTomSelect, '3');

            expect(result.districtTomSelect.setValue).toHaveBeenCalledWith(2, true);
        });

        it('should call onFleetChange callback with correct value', async () => {
            const result = await initializeDistrictFleetSelects({
                districtSelectId: 'district-select',
                fleetSelectId: 'fleet-select',
                onFleetChange: onFleetChangeSpy
            });

            result.fleetTomSelect.options.onChange.call(result.fleetTomSelect, '2');

            expect(onFleetChangeSpy).toHaveBeenCalledWith('2');
        });
    });

    describe('Error Handling', () => {
        it('should handle API fetch failure gracefully', async () => {
            global.fetch.mockRejectedValueOnce(new Error('Network error'));

            const result = await initializeDistrictFleetSelects({
                districtSelectId: 'district-select',
                fleetSelectId: 'fleet-select'
            });

            expect(result).toBeNull();
            expect(districtSelect.disabled).toBe(true);
            expect(fleetSelect.disabled).toBe(true);
        });

        it('should display error message when API fails', async () => {
            global.fetch.mockRejectedValueOnce(new Error('Network error'));

            await initializeDistrictFleetSelects({
                districtSelectId: 'district-select',
                fleetSelectId: 'fleet-select'
            });

            const errorAlert = document.querySelector('.alert-error');
            expect(errorAlert).toBeTruthy();
            expect(errorAlert.textContent).toContain('Unable to load districts and fleets');
        });

        it('should return null if select elements do not exist', async () => {
            document.body.innerHTML = '';

            const result = await initializeDistrictFleetSelects({
                districtSelectId: 'district-select',
                fleetSelectId: 'fleet-select'
            });

            expect(result).toBeNull();
        });
    });
});
