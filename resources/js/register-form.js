// Date of Birth input formatting and validation
document.addEventListener('DOMContentLoaded', function() {
    const dobInput = document.querySelector('input[name="date_of_birth"]');

    if (dobInput) {
        // Auto-format as user types (add hyphens only)
        dobInput.addEventListener('input', function(e) {
            const value = e.target.value.replace(/\D/g, ''); // Remove non-digits
            let formatted = '';

            // Year (4 digits)
            if (value.length > 0) {
                formatted = value.slice(0, 4);
            }

            // Month (2 digits) - add hyphen before
            if (value.length >= 5) {
                formatted += '-' + value.slice(4, 6);
            }

            // Day (2 digits) - add hyphen before
            if (value.length >= 7) {
                formatted += '-' + value.slice(6, 8);
            }

            e.target.value = formatted;
        });

        // Validate on blur
        dobInput.addEventListener('blur', function(e) {
            const value = e.target.value;
            let errorMessage = '';

            // Remove any existing error message
            const existingError = dobInput.parentElement.querySelector('.dob-error-message');
            if (existingError) {
                existingError.remove();
            }
            dobInput.classList.remove('input-error');

            if (!value) return; // Skip if empty (will be caught by required attribute)

            // Check format
            const datePattern = /^\d{4}-\d{2}-\d{2}$/;
            if (!datePattern.test(value)) {
                errorMessage = 'Please enter date in YYYY-MM-DD format';
            } else {
                // Parse the date
                const [year, month, day] = value.split('-').map(Number);

                // Validate year (reasonable range: 1900 to current year)
                const currentYear = new Date().getFullYear();
                if (year < 1900 || year > currentYear) {
                    errorMessage = `Year must be between 1900 and ${currentYear}`;
                }
                // Validate month
                else if (month < 1 || month > 12) {
                    errorMessage = 'Month must be between 01 and 12';
                }
                else {
                    // Validate day
                    const daysInMonth = new Date(year, month, 0).getDate();
                    if (day < 1 || day > daysInMonth) {
                        errorMessage = `Day must be between 01 and ${daysInMonth} for month ${month.toString().padStart(2, '0')}`;
                    } else {
                        // Check if date is not in the future
                        const inputDate = new Date(year, month - 1, day);
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);

                        if (inputDate > today) {
                            errorMessage = 'Date of birth cannot be in the future';
                        }
                    }
                }
            }

            // Show error if there is one
            if (errorMessage) {
                e.target.setCustomValidity(errorMessage);
                dobInput.classList.add('input-error');

                // Add visual error message below the input
                const errorDiv = document.createElement('div');
                errorDiv.className = 'label -mt-4 dob-error-message';
                errorDiv.innerHTML = `<span class="label-text-alt text-error">${errorMessage}</span>`;
                dobInput.parentElement.appendChild(errorDiv);
            } else {
                e.target.setCustomValidity('');
            }
        });

        // Clear custom validity on input to allow revalidation
        dobInput.addEventListener('input', function(e) {
            e.target.setCustomValidity('');
        });
    }
});