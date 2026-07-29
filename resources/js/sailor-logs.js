import { initCombobox } from './utils/combobox';

/**
 * Sailor Logs admin filters: district and fleet are both searchable
 * comboboxes (36 districts, 135 fleets). Simple logic: selecting a district
 * clears the fleet filter and vice versa; the "All Districts"/"All Fleets"
 * empty options are pickable rows (data-allow-empty on the selects).
 */
function initializeSailorLogsFilters() {
    const districtSelect = document.getElementById('sailor-logs-district-select');
    const fleetSelect = document.getElementById('sailor-logs-fleet-select');

    if (!districtSelect || !fleetSelect || fleetSelect._combobox) {
        return;
    }

    const districtCombobox = initCombobox(districtSelect, { placeholder: 'All Districts' });
    const fleetCombobox = initCombobox(fleetSelect, { placeholder: 'All Fleets' });

    const syncToLivewire = (el, property, value) => {
        const livewireComponent = Livewire.find(el.closest('[wire\\:id]')?.getAttribute('wire:id'));
        if (livewireComponent) {
            livewireComponent.set(property, value === '' ? null : parseInt(value, 10) || null);
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
            districtCombobox.clear({ silent: true });
            syncToLivewire(districtSelect, 'selectedDistrict', '');
        }
        syncToLivewire(fleetSelect, 'selectedFleet', value);
    });

    // Server-initiated reset (Clear Filters button): properties are already
    // null on the server, so update the UI silently
    Livewire.on('filters-cleared', () => {
        districtCombobox.clear({ silent: true });
        fleetCombobox.clear({ silent: true });
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    initializeSailorLogsFilters();
});
