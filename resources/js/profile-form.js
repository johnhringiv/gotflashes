import { initializeDateOfBirthValidator } from './utils/date-of-birth-validator';
import { initializeDistrictFleetSelects } from './utils/district-fleet-select';

document.addEventListener('DOMContentLoaded', function() {
    // Only run on profile page
    if (!document.getElementById('district-select-profile')) {
        return;
    }

    // Get Livewire component for syncing
    const districtSelect = document.getElementById('district-select-profile');
    const livewireComponent = districtSelect ?
        window.Livewire?.find(districtSelect.closest('[wire\\:id]')?.getAttribute('wire:id')) :
        null;

    // Initialize date of birth validator with Livewire sync
    const dobInput = document.querySelector('input[name="date_of_birth"]');
    if (dobInput && livewireComponent) {
        initializeDateOfBirthValidator(dobInput, (formatted) => {
            livewireComponent.set('date_of_birth', formatted);
        });
    }

    // Initialize district and fleet selects with Livewire sync
    initializeDistrictFleetSelects({
        districtSelectId: 'district-select-profile',
        fleetSelectId: 'fleet-select-profile',
        onDistrictChange: (value) => {
            if (livewireComponent) {
                livewireComponent.set('district_id', value);
            }
        },
        onFleetChange: (value) => {
            if (livewireComponent) {
                livewireComponent.set('fleet_id', value);
            }
        }
    });
});