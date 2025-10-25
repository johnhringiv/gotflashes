import { initializeDateOfBirthValidator } from './utils/date-of-birth-validator';
import { initializeDistrictFleetSelects } from './utils/district-fleet-select';

document.addEventListener('DOMContentLoaded', function() {
    // Initialize date of birth validator
    const dobInput = document.querySelector('input[name="date_of_birth"]');
    if (dobInput) {
        initializeDateOfBirthValidator(dobInput);
    }

    // Initialize district and fleet selects
    initializeDistrictFleetSelects({
        districtSelectId: 'district-select',
        fleetSelectId: 'fleet-select'
    });
});