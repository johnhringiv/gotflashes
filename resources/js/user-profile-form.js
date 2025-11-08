import { initializeDateOfBirthValidator } from './utils/date-of-birth-validator';
import { initializeDistrictFleetSelects } from './utils/district-fleet-select';

document.addEventListener('DOMContentLoaded', function() {
    initializeUserProfileForm();
});

// Reinitialize on Livewire navigation (for Livewire forms)
if (typeof Livewire !== 'undefined') {
    Livewire.hook('morph.added', ({ el }) => {
        // Check if date of birth input was added
        if (el.querySelector && el.querySelector('input[wire\\:model\\.blur="date_of_birth"]')) {
            initializeUserProfileForm();
        }
    });
}

function initializeUserProfileForm() {
    // Check if we're on a profile/registration page
    const districtSelect = document.getElementById('district-select');
    if (!districtSelect) {
        return; // Not on a user profile form page
    }

    // Get Livewire component for syncing (if available)
    const livewireComponent = window.Livewire?.find(districtSelect.closest('[wire\\:id]')?.getAttribute('wire:id'));

    // Initialize date of birth validator (formatting only - wire:model.blur handles sync)
    const dobInput = document.querySelector('input[wire\\:model\\.blur="date_of_birth"]') ||
                     document.querySelector('input[name="date_of_birth"]');

    if (dobInput && !dobInput._dobValidatorInitialized) {
        initializeDateOfBirthValidator(dobInput);
        dobInput._dobValidatorInitialized = true;
    }

    // Initialize district and fleet selects with Livewire sync (if component available)
    initializeDistrictFleetSelects({
        districtSelectId: 'district-select',
        fleetSelectId: 'fleet-select',
        onDistrictChange: livewireComponent ? (value) => {
            livewireComponent.set('district_id', value);
        } : undefined,
        onFleetChange: livewireComponent ? (value) => {
            livewireComponent.set('fleet_id', value);
        } : undefined
    });
}
