// Flash form JavaScript functionality
document.addEventListener('DOMContentLoaded', function() {
    // Enable/disable sailing_type based on activity_type
    const activityType = document.getElementById('activity_type');
    const sailingType = document.getElementById('sailing_type');

    // Only run if elements exist on the page
    if (activityType && sailingType) {
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
            }
        }

        activityType.addEventListener('change', updateSailingTypeState);
        // Run on page load to set initial state
        updateSailingTypeState();
    }

    // Restrict numeric inputs to integers only
    const sailNumberInput = document.getElementById('sail_number');
    if (sailNumberInput) {
        sailNumberInput.addEventListener('input', function() {
            // Remove any non-digit characters
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    }
});