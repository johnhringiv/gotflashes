import TomSelect from 'tom-select';

let districtTomSelect = null;
let fleetTomSelect = null;

/**
 * Initialize Tom Select dropdowns for Sailor Logs filters
 * Simple logic: selecting district clears fleet, selecting fleet clears district
 */
function initializeSailorLogsFilters() {
    const districtSelect = document.getElementById('sailor-logs-district-select');
    const fleetSelect = document.getElementById('sailor-logs-fleet-select');

    if (!districtSelect || !fleetSelect) {
        return;
    }

    // Prevent duplicate initialization
    if (districtSelect.tomselect || fleetSelect.tomselect) {
        districtTomSelect = districtSelect.tomselect;
        fleetTomSelect = fleetSelect.tomselect;
        return;
    }

    // Initialize District Select
    districtTomSelect = new TomSelect('#sailor-logs-district-select', {
        placeholder: 'All Districts',
        allowEmptyOption: true,
        maxOptions: null,
        dropdownParent: 'body',
        onChange: function(value) {
            if (value) this.blur();

            // Clear fleet selection when district is selected
            if (value && value !== '') {
                fleetTomSelect.clear();
            }

            // Sync with Livewire
            const livewireComponent = Livewire.find(districtSelect.closest('[wire\\:id]')?.getAttribute('wire:id'));
            if (livewireComponent) {
                livewireComponent.set('selectedDistrict', value === '' ? null : parseInt(value) || null);
            }
        }
    });

    // Initialize Fleet Select
    fleetTomSelect = new TomSelect('#sailor-logs-fleet-select', {
        placeholder: 'All Fleets',
        allowEmptyOption: true,
        maxOptions: null,
        dropdownParent: 'body',
        onChange: function(value) {
            if (value) this.blur();

            // Clear district selection when fleet is selected
            if (value && value !== '') {
                districtTomSelect.clear();
            }

            // Sync with Livewire
            const livewireComponent = Livewire.find(fleetSelect.closest('[wire\\:id]')?.getAttribute('wire:id'));
            if (livewireComponent) {
                livewireComponent.set('selectedFleet', value === '' ? null : parseInt(value) || null);
            }
        }
    });

    // Listen for clear filters event from Livewire
    Livewire.on('filters-cleared', () => {
        if (districtTomSelect) {
            districtTomSelect.clear();
        }
        if (fleetTomSelect) {
            fleetTomSelect.clear();
        }
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    initializeSailorLogsFilters();
});
