// Flash form JavaScript functionality
document.addEventListener('DOMContentLoaded', function() {
    initializeFlashForm();
});

// Reinitialize after Livewire updates (for edit modal)
document.addEventListener('livewire:init', () => {
    Livewire.hook('morph.added', ({ el }) => {
        // Check if this element is or contains the edit form
        const hasEditForm = el.id === 'activity_type_edit' ||
                           el.id === 'sail_number_edit' ||
                           (el.querySelector && (el.querySelector('#activity_type_edit') || el.querySelector('#sail_number_edit')));

        // Only initialize if the modal or form was added
        if (hasEditForm) {
            requestAnimationFrame(() => {
                initializeFlashForm();
            });
        }
    });
});

function initializeFlashForm() {
    // Initialize both main form and edit form
    initializeFormFields('activity_type', 'sailing_type', 'sail_number');
    initializeFormFields('activity_type_edit', 'sailing_type_edit', 'sail_number_edit');
}

function initializeFormFields(activityTypeId, sailingTypeId, sailNumberId) {
    // Enable/disable sailing_type based on activity_type
    const activityType = document.getElementById(activityTypeId);
    const sailingType = document.getElementById(sailingTypeId);

    // Only run if elements exist and not already initialized
    if (activityType && sailingType && !activityType._flashFormInitialized) {
        function updateSailingTypeState() {
            const placeholderOption = sailingType.querySelector('option[value=""][disabled]');

            if (activityType.value === 'sailing') {
                sailingType.disabled = false;
                sailingType.required = true;
                sailingType.classList.remove('select-disabled');
                if (placeholderOption) placeholderOption.textContent = 'Select sailing type - All count equally';
            } else {
                sailingType.disabled = true;
                sailingType.required = false;
                sailingType.value = '';
                sailingType.classList.add('select-disabled');
                if (placeholderOption) placeholderOption.textContent = 'Not applicable';

                // Dispatch input event to notify Livewire of the value change
                sailingType.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }

        activityType.addEventListener('change', updateSailingTypeState);
        activityType._flashFormInitialized = true;
        // Run immediately to set initial state
        updateSailingTypeState();
    }

    // Restrict numeric inputs to integers only
    const sailNumberInput = document.getElementById(sailNumberId);
    if (sailNumberInput && !sailNumberInput._flashFormInitialized) {
        sailNumberInput.addEventListener('input', function() {
            // Remove any non-digit characters
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        sailNumberInput._flashFormInitialized = true;
    }
}