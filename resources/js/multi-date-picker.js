import flatpickr from 'flatpickr';

document.addEventListener('DOMContentLoaded', function() {
    const datePickerElement = document.getElementById('date-picker');
    const formElement = datePickerElement?.closest('form');

    if (datePickerElement && formElement) {
        // Get min/max dates from data attributes (passed from controller)
        const minDateStr = datePickerElement.getAttribute('data-min-date');
        const maxDateStr = datePickerElement.getAttribute('data-max-date');

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

        const convertYearToDropdown = function(instance) {
            const yearInput = instance.currentYearElement;
            if (!yearInput || yearInput.tagName === 'SELECT') return; // Already converted

            // Calculate allowed years based on min/max dates
            // Parse as local date to avoid timezone issues (YYYY-MM-DD format)
            const minYear = parseInt(minDateStr.split('-')[0], 10);
            const maxYear = parseInt(maxDateStr.split('-')[0], 10);

            // Create select element
            const select = document.createElement('select');
            select.className = yearInput.className;
            select.style.cssText = yearInput.style.cssText;

            // Add year options
            for (let year = maxYear; year >= minYear; year--) {
                const option = document.createElement('option');
                option.value = year;
                option.textContent = year;
                if (year === instance.currentYear) {
                    option.selected = true;
                }
                select.appendChild(option);
            }

            // Handle year change
            select.addEventListener('change', function() {
                instance.changeYear(parseInt(this.value));
            });

            // Replace input with select
            yearInput.parentNode.replaceChild(select, yearInput);
            instance.currentYearElement = select;
        };

        const hideExtraWeeks = function(selectedDates, dateStr, instance) {
            const calendarContainer = instance.calendarContainer;
            if (!calendarContainer) return;

            const daysContainer = calendarContainer.querySelector('.dayContainer');
            if (!daysContainer) return;

            const days = daysContainer.querySelectorAll('.flatpickr-day');

            // Group days into weeks (7 days per week)
            const weeks = [];
            for (let i = 0; i < days.length; i += 7) {
                weeks.push(Array.from(days).slice(i, i + 7));
            }

            // Check each week and hide if it only contains prev/next month days
            weeks.forEach((weekDays) => {
                let hasCurrentMonthDay = false;

                weekDays.forEach(day => {
                    if (!day.classList.contains('prevMonthDay') && !day.classList.contains('nextMonthDay')) {
                        hasCurrentMonthDay = true;
                    }
                });

                // Hide the entire week by hiding each day in it
                if (!hasCurrentMonthDay) {
                    weekDays.forEach(day => {
                        day.style.display = 'none';
                    });
                } else {
                    // Make sure to show days in weeks that should be visible
                    weekDays.forEach(day => {
                        day.style.display = '';
                    });
                }
            });
        };

        flatpickr(datePickerElement, {
            mode: 'multiple',
            dateFormat: 'Y-m-d',
            minDate: minDateStr,
            maxDate: maxDateStr,
            conjunction: ', ',
            allowInput: false,
            clickOpens: true,
            showMonths: 1,
            static: false,
            disableMobile: false,
            disable: existingDates, // Disable dates that already have entries
            onDayCreate: function(dObj, dStr, fp, dayElem) {
                // Add custom class to existing dates to style them differently
                const dateStr = fp.formatDate(dayElem.dateObj, 'Y-m-d');
                if (existingDates.includes(dateStr)) {
                    dayElem.classList.add('has-entry');
                }
            },
            onReady: function(selectedDates, dateStr, instance) {
                convertYearToDropdown(instance);
                hideExtraWeeks(selectedDates, dateStr, instance);
            },
            onMonthChange: hideExtraWeeks,
            onYearChange: hideExtraWeeks,
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
