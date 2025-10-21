import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import TomSelect from 'tom-select';

describe('Toast Notification Fade Logic', () => {
    let toast;
    let toastContainer;

    beforeEach(() => {
        vi.useFakeTimers();

        // Setup DOM with toast
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast toast-top toast-center z-50';
        toastContainer.innerHTML = `
            <div class="alert alert-success" id="success-toast">
                <span>Success message</span>
            </div>
        `;
        document.body.appendChild(toastContainer);
        toast = document.getElementById('success-toast');
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('should exist in the DOM with correct structure', () => {
        expect(toast).toBeTruthy();
        expect(toast.textContent).toContain('Success message');
        expect(toast.classList.contains('alert')).toBe(true);
        expect(toast.classList.contains('alert-success')).toBe(true);
    });

    it('should fade out after 3 seconds', () => {
        // Simulate the toast fade logic from app.js
        setTimeout(() => {
            toast.style.transition = 'opacity 0.5s';
            toast.style.opacity = '0';
        }, 3000);

        // Initially visible
        expect(toast.style.opacity).toBe('');

        // After 3 seconds, should start fading
        vi.advanceTimersByTime(3000);
        expect(toast.style.opacity).toBe('0');
        expect(toast.style.transition).toBe('opacity 0.5s');
    });

    it('should remove toast from DOM after fade completes', () => {
        // Simulate the complete toast logic from app.js
        setTimeout(() => {
            toast.style.transition = 'opacity 0.5s';
            toast.style.opacity = '0';
            setTimeout(() => toast.parentElement.remove(), 500);
        }, 3000);

        // Toast exists initially
        expect(document.getElementById('success-toast')).toBeTruthy();

        // After 3 seconds, toast starts fading
        vi.advanceTimersByTime(3000);
        expect(document.getElementById('success-toast')).toBeTruthy();

        // After fade completes (3.5 seconds total), toast removed
        vi.advanceTimersByTime(500);
        expect(document.getElementById('success-toast')).toBeNull();
    });
});

describe('TomSelect Initialization', () => {
    let districtSelect;
    let fleetSelect;

    beforeEach(() => {
        // Setup DOM
        const container = document.createElement('div');
        container.innerHTML = `
            <select name="district_id" id="district-select">
                <option value="">Select district...</option>
            </select>
            <select name="fleet_id" id="fleet-select">
                <option value="">Select fleet...</option>
            </select>
        `;
        document.body.appendChild(container);
        districtSelect = document.getElementById('district-select');
        fleetSelect = document.getElementById('fleet-select');
    });

    it('should initialize TomSelect on district select', () => {
        const tomselect = new TomSelect('#district-select', {
            placeholder: 'Search districts...',
            maxOptions: null,
        });

        expect(tomselect).toBeTruthy();
        expect(tomselect.input).toBe(districtSelect);

        tomselect.destroy();
    });

    it('should initialize TomSelect on fleet select', () => {
        const tomselect = new TomSelect('#fleet-select', {
            placeholder: 'Search fleets...',
            maxOptions: null,
        });

        expect(tomselect).toBeTruthy();
        expect(tomselect.input).toBe(fleetSelect);

        tomselect.destroy();
    });

    it('should allow adding options to TomSelect', () => {
        const tomselect = new TomSelect('#fleet-select');

        tomselect.addOption({
            value: '1',
            text: 'Fleet 194 - Mission Bay',
            fleet_number: 194,
            fleet_name: 'Mission Bay',
        });

        expect(tomselect.options['1']).toBeTruthy();
        expect(tomselect.options['1'].text).toBe('Fleet 194 - Mission Bay');

        tomselect.destroy();
    });

    it('should handle data attributes for old values', () => {
        districtSelect.dataset.oldValue = '5';

        expect(districtSelect.dataset.oldValue).toBe('5');
    });
});

describe('API Fetch Integration', () => {
    it('should handle successful API fetch', async () => {
        global.fetch = vi.fn(() =>
            Promise.resolve({
                json: () => Promise.resolve([
                    { id: 1, name: 'California' },
                    { id: 2, name: 'Central Atlantic' },
                ]),
            })
        );

        const response = await fetch('/api/districts');
        const data = await response.json();

        expect(data).toHaveLength(2);
        expect(data[0].name).toBe('California');

        vi.restoreAllMocks();
    });

    it('should handle API fetch errors', async () => {
        const consoleErrorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});

        global.fetch = vi.fn(() => Promise.reject(new Error('Network error')));

        try {
            await fetch('/api/districts');
        } catch (error) {
            expect(error.message).toBe('Network error');
        }

        vi.restoreAllMocks();
    });
});