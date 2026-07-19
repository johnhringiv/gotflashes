import TomSelect from 'tom-select';

/**
 * Initialize district and fleet TomSelect dropdowns with smart filtering.
 *
 * "Unaffiliated/None" is a real district and fleet row (fleet_number 0); the
 * API reports their ids as none_district_id / none_fleet_id. The None fleet
 * is selectable alongside ANY district.
 *
 * @param {Object} config - Configuration object
 * @param {string} config.districtSelectId - ID of the district select element
 * @param {string} config.fleetSelectId - ID of the fleet select element
 * @param {Function} config.onDistrictChange - Callback when district changes (for Livewire sync)
 * @param {Function} config.onFleetChange - Callback when fleet changes (for Livewire sync)
 */
export async function initializeDistrictFleetSelects(config) {
    const {
        districtSelectId,
        fleetSelectId,
        onDistrictChange = null,
        onFleetChange = null
    } = config;

    const districtSelect = document.getElementById(districtSelectId);
    const fleetSelect = document.getElementById(fleetSelectId);

    if (!districtSelect || !fleetSelect) {
        return null;
    }

    // No `= []` default on purpose: ESLint's no-useless-assignment flags it
    // (immediately overwritten in the try). The catch below returns early, so
    // these are never read while still undefined.
    let districts;
    let fleets;
    let noneDistrictId;
    let noneFleetId;

    // Fetch data from API (combined endpoint for better performance and to avoid SQLite locking)
    try {
        const response = await fetch('/api/districts-and-fleets');

        if (!response.ok) {
            throw new Error(`Failed to fetch data: ${response.status}`);
        }

        const data = await response.json();
        districts = data.districts;
        fleets = data.fleets;
        noneDistrictId = String(data.none_district_id);
        noneFleetId = String(data.none_fleet_id);
    } catch (error) {
        // eslint-disable-next-line no-console
        console.error('Error fetching districts and fleets:', error);

        // Display user-friendly error message
        const errorAlert = document.createElement('div');
        errorAlert.className = 'alert alert-error mb-4';
        errorAlert.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0 stroke-current" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Unable to load districts and fleets. Please refresh the page or try again later.</span>
        `;

        // Insert error before the district select
        districtSelect.parentElement.insertAdjacentElement('beforebegin', errorAlert);

        // Disable the select elements
        districtSelect.disabled = true;
        fleetSelect.disabled = true;

        return null;
    }

    const isNoneFleet = (value) => String(value) === noneFleetId;

    // Initialize District Select (the None district is a real row in the list)
    const districtTomSelect = new TomSelect(`#${districtSelectId}`, {
        options: districts.map(d => ({ value: d.id, text: d.name, id: d.id, name: d.name })),
        placeholder: 'Select district...',
        allowEmptyOption: true,
        maxOptions: null,
        dropdownParent: 'body',
        sortField: {
            field: 'text',
            direction: 'asc'
        },
        onChange: function(value) {
            if (value) this.blur();

            // Callback for Livewire sync
            if (onDistrictChange) {
                onDistrictChange(value);
            }

            // Clear fleet selection when district changes — user must re-select.
            // Server detects this via the empty fleet value on submit.
            fleetTomSelect.clear();
            if (onFleetChange) {
                onFleetChange(null);
            }

            if (String(value) === noneDistrictId) {
                // Unaffiliated district: any fleet is still selectable
                // (picking a real fleet auto-sets its district below)
                updateFleetOptions(fleets);
            } else if (value) {
                updateFleetOptions(fleets.filter(f => f.district_id == value || isNoneFleet(f.id)));
            } else {
                // District was cleared - sync null to Livewire and show all fleets
                if (onDistrictChange) {
                    onDistrictChange(null);
                }
                updateFleetOptions(fleets);
            }
        },
        onType: function(str) {
            if (this.items.length > 0 && str.length === 1) {
                this.clear();
            }
        }
    });

    // Initialize Fleet Select
    const fleetTomSelect = new TomSelect(`#${fleetSelectId}`, {
        placeholder: 'Select fleet...',
        maxOptions: null,
        dropdownParent: 'body',
        sortField: {
            field: 'fleet_number',
            direction: 'asc'
        },
        render: {
            option: function(data, escape) {
                if (isNoneFleet(data.value)) return '<div>None</div>';
                if (!data.fleet_number || !data.fleet_name) return '<div></div>';
                return '<div>Fleet ' + escape(data.fleet_number) + ' - ' + escape(data.fleet_name) + '</div>';
            },
            item: function(data, escape) {
                if (isNoneFleet(data.value)) return '<div>None</div>';
                if (!data.fleet_number || !data.fleet_name) return '<div></div>';
                return '<div>Fleet ' + escape(data.fleet_number) + ' - ' + escape(data.fleet_name) + '</div>';
            }
        },
        onChange: function(value) {
            if (value) {
                this.blur();

                // Callback for Livewire sync
                if (onFleetChange) {
                    onFleetChange(value);
                }

                if (isNoneFleet(value)) {
                    // Special case: fleet set to None with a blank district —
                    // fill the district as Unaffiliated/None too
                    const currentDistrict = districtTomSelect.getValue();
                    if (!currentDistrict || currentDistrict === '') {
                        districtTomSelect.setValue(noneDistrictId, true);
                        // Explicitly sync to Livewire since silent=true skips onChange
                        if (onDistrictChange) {
                            onDistrictChange(noneDistrictId);
                        }
                    }
                } else {
                    const fleet = fleets.find(f => f.id == value);
                    if (fleet) {
                        districtTomSelect.setValue(fleet.district_id, true);
                        // Explicitly sync to Livewire since silent=true skips onChange
                        if (onDistrictChange) {
                            onDistrictChange(fleet.district_id);
                        }
                    }
                }
            } else {
                // Fleet was cleared - sync null to Livewire
                if (onFleetChange) {
                    onFleetChange(null);
                }
            }
        },
        onType: function(str) {
            if (this.items.length > 0 && str.length === 1) {
                this.clear();
            }
        }
    });

    function updateFleetOptions(fleetList) {
        fleetTomSelect.clearOptions();

        fleetList.forEach(fleet => {
            fleetTomSelect.addOption({
                value: fleet.id,
                text: isNoneFleet(fleet.id) ? 'None' : `Fleet ${fleet.fleet_number} - ${fleet.fleet_name}`,
                fleet_number: fleet.fleet_number,
                fleet_name: fleet.fleet_name,
                fleet_id: fleet.id,
                district_id: fleet.district_id,
                district_name: fleet.district_name
            });
        });

        fleetTomSelect.refreshOptions(false);
    }

    // Initialize fleet options (the None fleet sorts first: fleet_number 0)
    updateFleetOptions(fleets);

    // Set initial values from data attributes
    const initialDistrictId = districtSelect.dataset.value || districtSelect.dataset.oldValue;
    const initialFleetId = fleetSelect.dataset.value || fleetSelect.dataset.oldValue;
    const isProfilePage = districtSelect.dataset.isProfile === 'true';

    // Handle district initialization
    if (initialDistrictId && initialDistrictId !== '' && initialDistrictId !== 'null') {
        districtTomSelect.setValue(initialDistrictId);
    } else if (isProfilePage) {
        // Safety net: profile memberships always carry real ids, but if one is
        // ever missing, fall back to Unaffiliated/None — non-silent so the
        // Livewire property stays in sync with what the UI shows
        districtTomSelect.setValue(noneDistrictId);
    }
    // On signup, leave empty to show placeholder

    // Handle fleet initialization
    if (initialFleetId && initialFleetId !== '' && initialFleetId !== 'null') {
        fleetTomSelect.setValue(initialFleetId);
    } else if (isProfilePage) {
        // Same safety net as the district above
        fleetTomSelect.setValue(noneFleetId);
    }
    // On signup, leave empty to show placeholder

    return { districtTomSelect, fleetTomSelect };
}
