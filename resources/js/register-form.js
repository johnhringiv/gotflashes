import TomSelect from 'tom-select';

// Date of Birth input formatting and validation
document.addEventListener('DOMContentLoaded', async function() {
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

    // Initialize TomSelect for district and fleet dropdowns
    const districtSelect = document.getElementById('district-select');
    const fleetSelect = document.getElementById('fleet-select');

    if (districtSelect && fleetSelect) {
        let districts = [];
        let fleets = [];

        // Fetch data from API
        try {
            const [districtsResponse, fleetsResponse] = await Promise.all([
                fetch('/api/districts'),
                fetch('/api/fleets')
            ]);

            districts = await districtsResponse.json();
            fleets = await fleetsResponse.json();
        } catch (error) {
            // eslint-disable-next-line no-console
            console.error('Error fetching data:', error);
            return;
        }

        // Initialize District Select with IDs as values but names as display text
        const districtTomSelect = new TomSelect('#district-select', {
            options: [
                { value: 'none', text: 'Unaffiliated/None', id: null },
                ...districts.map(d => ({ value: d.id, text: d.name, id: d.id, name: d.name }))
            ],
            placeholder: 'Search districts...',
            maxOptions: null,
            dropdownParent: 'body',
            sortField: {
                field: 'text',
                direction: 'asc'
            },
            onChange: function(value) {
                // Blur to hide cursor after selection
                if (value) {
                    this.blur();
                }

                // Clear fleet selection when district changes
                fleetTomSelect.clear();

                if (value === 'none') {
                    // Show all fleets for unaffiliated (in case of user mistake)
                    updateFleetOptions(fleets, false);
                    // Auto-set fleet to "None" for unaffiliated
                    fleetTomSelect.setValue('none', true); // silent=true
                } else if (value) {
                    // Filter fleets by selected district ID
                    const filteredFleets = fleets.filter(f => f.district_id == value);
                    updateFleetOptions(filteredFleets, false);
                } else {
                    // Show all fleets if no district selected
                    updateFleetOptions(fleets, false);
                }
            },
            onType: function(str) {
                // Clear selection when user starts typing
                if (this.items.length > 0 && str.length === 1) {
                    this.clear();
                }
            }
        });

        // Initialize Fleet Select with IDs as values
        const fleetTomSelect = new TomSelect('#fleet-select', {
            placeholder: 'Search fleets by name or number...',
            maxOptions: null,
            dropdownParent: 'body',
            sortField: {
                field: 'fleet_number',
                direction: 'asc'
            },
            render: {
                option: function(data, escape) {
                    // Handle "None" option without "Fleet" prefix
                    if (data.value === 'none') {
                        return '<div>None</div>';
                    }
                    return '<div>Fleet ' + escape(data.fleet_number) + ' - ' + escape(data.fleet_name) + '</div>';
                },
                item: function(data, escape) {
                    // Handle "None" option without "Fleet" prefix
                    if (data.value === 'none') {
                        return '<div>None</div>';
                    }
                    return '<div>Fleet ' + escape(data.fleet_number) + ' - ' + escape(data.fleet_name) + '</div>';
                }
            },
            onChange: function(value) {
                // Blur to hide cursor after selection
                if (value) {
                    this.blur();

                    // Find the fleet by ID and set its district
                    const fleet = fleets.find(f => f.id == value);
                    if (fleet) {
                        districtTomSelect.setValue(fleet.district_id, true); // silent=true to avoid triggering onChange
                    }
                }
            },
            onType: function(str) {
                // Clear selection when user starts typing
                if (this.items.length > 0 && str.length === 1) {
                    this.clear();
                }
            }
        });

        // Add all fleet options initially
        updateFleetOptions(fleets, false);

        function updateFleetOptions(fleetList, showNoneOnly = false) {
            // Clear existing options
            fleetTomSelect.clearOptions();

            // Add fleet options if not showing None only
            if (!showNoneOnly) {
                fleetList.forEach(fleet => {
                    fleetTomSelect.addOption({
                        value: fleet.id,
                        text: `Fleet ${fleet.fleet_number} - ${fleet.fleet_name}`,
                        fleet_number: fleet.fleet_number,
                        fleet_name: fleet.fleet_name,
                        fleet_id: fleet.id,
                        district_id: fleet.district_id,
                        district_name: fleet.district_name
                    });
                });
            }

            // Add "None" option at the bottom
            fleetTomSelect.addOption({
                value: 'none',
                text: 'None',
                fleet_number: 'None',
                fleet_name: 'None'
            });

            fleetTomSelect.refreshOptions(false); // false = don't trigger focus
        }

        // Handle old() values for validation errors (passed via data attributes)
        const oldDistrictId = districtSelect.dataset.oldValue;
        const oldFleetId = fleetSelect.dataset.oldValue;

        if (oldDistrictId) {
            districtTomSelect.setValue(oldDistrictId);
        }
        if (oldFleetId) {
            fleetTomSelect.setValue(oldFleetId);
        }
    }
});