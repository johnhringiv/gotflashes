import flatpickr from 'flatpickr';

document.addEventListener('DOMContentLoaded', function() {
    initializePickers();
});

// Reinitialize after Livewire updates
document.addEventListener('livewire:navigated', function() {
    initializePickers();
});

function initializePickers() {
    // Initialize multi-date picker (create mode)
    const datePickerElement = document.getElementById('date-picker');
    if (datePickerElement && !datePickerElement._flatpickr) {
        initializeDatePicker(datePickerElement, 'multiple');
    }

    // Initialize single-date picker (edit mode)
    const datePickerSingleElement = document.getElementById('date-picker-single');
    if (datePickerSingleElement && !datePickerSingleElement._flatpickr) {
        initializeDatePicker(datePickerSingleElement, 'single');
    }
}

function reinitializeDatePicker(datePickerElement, mode) {
    if (!datePickerElement) return;

    // Destroy existing instance if it exists
    if (datePickerElement._flatpickr) {
        datePickerElement._flatpickr.destroy();
    }

    // Reinitialize
    initializeDatePicker(datePickerElement, mode);
}

// Listen for flash-saved event to reinitialize the date picker with updated dates
document.addEventListener('livewire:init', () => {
    // Listen for flash-saved event
    window.Livewire.on('flash-saved', () => {
        // Wait for Livewire to finish updating the DOM
        setTimeout(() => {
            const datePickerElement = document.getElementById('date-picker');
            if (datePickerElement) {
                // Clear and reinitialize to pick up updated data attributes
                if (datePickerElement._flatpickr) {
                    datePickerElement._flatpickr.clear();
                }
                reinitializeDatePicker(datePickerElement, 'multiple');
            }
        }, 300);
    });

    // Listen for flash-deleted event
    window.Livewire.on('flash-deleted', () => {
        // Wait for Livewire to finish updating the DOM
        setTimeout(() => {
            const datePickerElement = document.getElementById('date-picker');
            if (datePickerElement) {
                reinitializeDatePicker(datePickerElement, 'multiple');
            }
        }, 300);
    });
});

function initializeDatePicker(datePickerElement, mode) {
    const formElement = datePickerElement.closest('form');

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

            // If only one year is available, don't convert to dropdown
            if (minYear === maxYear) return;

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

        let hideExtraWeeksTimeout;
        const hideExtraWeeks = function(selectedDates, dateStr, instance) {
            // Clear any pending timeout
            if (hideExtraWeeksTimeout) {
                clearTimeout(hideExtraWeeksTimeout);
            }

            // Use requestAnimationFrame to ensure DOM is ready
            hideExtraWeeksTimeout = setTimeout(() => {
                requestAnimationFrame(() => {
                    const calendarContainer = instance.calendarContainer;
                    if (!calendarContainer) return;

                    const daysContainer = calendarContainer.querySelector('.dayContainer');
                    if (!daysContainer) return;

                    const days = daysContainer.querySelectorAll('.flatpickr-day');
                    if (days.length === 0) return;

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
                });
            }, 10);
        };

        // Get default date for single mode
        const defaultDate = datePickerElement.getAttribute('data-default-date');

        // Build flatpickr config based on mode
        const config = {
            mode: mode,
            dateFormat: 'Y-m-d',
            minDate: minDateStr,
            maxDate: maxDateStr,
            allowInput: false,
            clickOpens: true,
            showMonths: 1,
            static: false,
            disableMobile: false,
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
            onOpen: hideExtraWeeks,
            onMonthChange: hideExtraWeeks,
            onYearChange: hideExtraWeeks,
        };

        // Add mode-specific configuration
        if (mode === 'multiple') {
            config.conjunction = ', ';
            config.disable = existingDates; // Disable dates that already have entries
            config.onChange = function(selectedDates, dateStr, instance) {
                // Update display value
                datePickerElement.value = dateStr;

                // Convert dates to Y-m-d format array
                const formattedDates = selectedDates.map(date => instance.formatDate(date, 'Y-m-d'));

                // Sync with Livewire if present
                const livewireComponent = datePickerElement.closest('[wire\\:id]');
                if (livewireComponent) {
                    // Livewire v3 API - use Livewire.find()
                    const componentId = livewireComponent.getAttribute('wire:id');
                    if (window.Livewire && componentId) {
                        const component = window.Livewire.find(componentId);
                        if (component) {
                            component.set('dates', formattedDates);
                        }
                    }
                } else {
                    // Fallback to hidden inputs for non-Livewire forms
                    formElement.querySelectorAll('input[name="dates[]"]').forEach(input => input.remove());
                    selectedDates.forEach(date => {
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'dates[]';
                        hiddenInput.value = instance.formatDate(date, 'Y-m-d');
                        formElement.appendChild(hiddenInput);
                    });
                }

                // Hide extra weeks after date selection
                hideExtraWeeks(selectedDates, dateStr, instance);
            };
        } else if (mode === 'single') {
            // For single mode, disable all existing dates except the current one being edited
            const currentDate = defaultDate;
            config.disable = existingDates.filter(date => date !== currentDate);

            // Set default date for edit mode
            if (defaultDate) {
                config.defaultDate = defaultDate;
            }

            config.onChange = function(selectedDates, dateStr, instance) {
                // For single mode, just update the input value
                datePickerElement.value = dateStr;

                // Hide extra weeks after date selection
                hideExtraWeeks(selectedDates, dateStr, instance);
            };
        }

        flatpickr(datePickerElement, config);
    }
}
