// Flash form JavaScript functionality
document.addEventListener('DOMContentLoaded', function() {
    // Enable/disable sailing_type based on activity_type
    const activityType = document.querySelector('select[name="activity_type"]');
    const sailingType = document.getElementById('sailing_type');
    const sailingTypeRequired = document.getElementById('sailing-type-required');
    const sailingTypeHelp = document.getElementById('sailing-type-help');

    // Only run if elements exist on the page
    if (activityType && sailingType) {
        function updateSailingTypeState() {
            const placeholderOption = sailingType.querySelector('option[value=""][disabled]');

            if (activityType.value === 'sailing') {
                sailingType.disabled = false;
                sailingType.required = true;
                sailingType.classList.remove('select-disabled');
                if (sailingTypeRequired) sailingTypeRequired.style.display = 'inline';
                if (sailingTypeHelp) sailingTypeHelp.textContent = 'All sailing types count equally toward awards.';
                if (placeholderOption) placeholderOption.textContent = 'Select sailing type - All count equally';
            } else {
                sailingType.disabled = true;
                sailingType.required = false;
                sailingType.value = '';
                sailingType.classList.add('select-disabled');
                if (sailingTypeRequired) sailingTypeRequired.style.display = 'none';
                if (sailingTypeHelp) sailingTypeHelp.textContent = 'Only applicable for sailing activities.';
                if (placeholderOption) placeholderOption.textContent = 'Not applicable';
            }
        }

        activityType.addEventListener('change', updateSailingTypeState);
        // Run on page load
        updateSailingTypeState();
    }

    // Restrict numeric inputs to integers only
    const numericInputs = document.querySelectorAll('input[name="sail_number"]');
    numericInputs.forEach(input => {
        input.addEventListener('input', function() {
            // Remove any non-digit characters
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    });
});