import { initCombobox } from './utils/combobox';

/**
 * Sailor Logs admin filters: district is a plain native select, fleet is a
 * searchable combobox (135 options). Simple logic: selecting a district
 * clears the fleet filter and vice versa.
 */
function initializeSailorLogsFilters() {
    const districtSelect = document.getElementById('sailor-logs-district-select');
    const fleetSelect = document.getElementById('sailor-logs-fleet-select');

    if (!districtSelect || !fleetSelect || fleetSelect._combobox) {
        return;
    }

    const fleetCombobox = initCombobox(fleetSelect, { placeholder: 'All Fleets' });

    const syncToLivewire = (el, property, value) => {
        const livewireComponent = Livewire.find(el.closest('[wire\\:id]')?.getAttribute('wire:id'));
        if (livewireComponent) {
            livewireComponent.set(property, value === '' ? null : parseInt(value) || null);
        }
    };

    districtSelect.addEventListener('change', () => {
        const value = districtSelect.value;
        if (value) {
            fleetCombobox.clear({ silent: true });
            syncToLivewire(fleetSelect, 'selectedFleet', '');
        }
        syncToLivewire(districtSelect, 'selectedDistrict', value);
    });

    fleetSelect.addEventListener('change', () => {
        const value = fleetSelect.value;
        if (value) {
            districtSelect.value = '';
            syncToLivewire(districtSelect, 'selectedDistrict', '');
        }
        syncToLivewire(fleetSelect, 'selectedFleet', value);
    });

    // Server-initiated reset (Clear Filters button): properties are already
    // null on the server, so update the UI silently
    Livewire.on('filters-cleared', () => {
        districtSelect.value = '';
        fleetCombobox.clear({ silent: true });
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    initializeSailorLogsFilters();
});
