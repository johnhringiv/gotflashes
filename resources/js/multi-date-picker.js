import flatpickr from 'flatpickr';

document.addEventListener('DOMContentLoaded', function() {
    const datePickerElement = document.getElementById('date-picker');
    const formElement = datePickerElement?.closest('form');

    if (datePickerElement && formElement) {
        const maxDate = new Date();
        maxDate.setDate(maxDate.getDate() + 1); // Allow today + 1 day for timezone handling

        // Get existing dates from data attribute
        let existingDates = [];
        try {
            const existingDatesAttr = datePickerElement.getAttribute('data-existing-dates');
            if (existingDatesAttr) {
                existingDates = JSON.parse(existingDatesAttr);
            }
        } catch (e) {
            // Failed to parse existing dates - use empty array
            // eslint-disable-next-line no-console
            console.error('Failed to parse existing dates:', e);
        }

        flatpickr(datePickerElement, {
            mode: 'multiple',
            dateFormat: 'Y-m-d',
            maxDate: maxDate,
            conjunction: ', ',
            allowInput: false,
            clickOpens: true,
            disable: existingDates, // Disable dates that already have entries
            onDayCreate: function(dObj, dStr, fp, dayElem) {
                // Add custom class to existing dates to style them differently
                const dateStr = fp.formatDate(dayElem.dateObj, 'Y-m-d');
                if (existingDates.includes(dateStr)) {
                    dayElem.classList.add('has-entry');
                }
            },
            onChange: function(selectedDates, dateStr, instance) {
                // Remove any existing date[] hidden inputs
                formElement.querySelectorAll('input[name="dates[]"]').forEach(input => input.remove());

                // Create hidden input for each selected date
                selectedDates.forEach(date => {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'dates[]';
                    hiddenInput.value = instance.formatDate(date, 'Y-m-d');
                    formElement.appendChild(hiddenInput);
                });

                // Update display value
                datePickerElement.value = dateStr;
            }
        });
    }
});
