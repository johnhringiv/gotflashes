import TomSelect from 'tom-select';

/**
 * Initialize district and fleet TomSelect dropdowns with smart filtering
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

    let districts = [];
    let fleets = [];

    // Fetch data from API (combined endpoint for better performance and to avoid SQLite locking)
    try {
        const response = await fetch('/api/districts-and-fleets');

        if (!response.ok) {
            throw new Error(`Failed to fetch data: ${response.status}`);
        }

        const data = await response.json();
        districts = data.districts;
        fleets = data.fleets;
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

    // Initialize District Select
    const districtTomSelect = new TomSelect(`#${districtSelectId}`, {
        options: [
            { value: 'none', text: 'Unaffiliated/None', id: null },
            ...districts.map(d => ({ value: d.id, text: d.name, id: d.id, name: d.name }))
        ],
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
                onDistrictChange(value === 'none' ? null : value);
            }

            // Clear fleet selection when district changes
            fleetTomSelect.clear();

            if (value === 'none') {
                updateFleetOptions(fleets, false);
                // Don't auto-select 'none' - leave empty to show placeholder
            } else if (value) {
                const filteredFleets = fleets.filter(f => f.district_id == value);
                updateFleetOptions(filteredFleets, false);
            } else {
                // District was cleared - sync null to Livewire and show all fleets
                if (onDistrictChange) {
                    onDistrictChange(null);
                }
                updateFleetOptions(fleets, false);
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
                if (data.value === 'none') return '<div>None</div>';
                if (!data.fleet_number || !data.fleet_name) return '<div></div>';
                return '<div>Fleet ' + escape(data.fleet_number) + ' - ' + escape(data.fleet_name) + '</div>';
            },
            item: function(data, escape) {
                if (data.value === 'none') return '<div>None</div>';
                if (!data.fleet_number || !data.fleet_name) return '<div></div>';
                return '<div>Fleet ' + escape(data.fleet_number) + ' - ' + escape(data.fleet_name) + '</div>';
            }
        },
        onChange: function(value) {
            if (value) {
                this.blur();

                // Callback for Livewire sync
                if (onFleetChange) {
                    onFleetChange(value === 'none' ? null : value);
                }

                const fleet = fleets.find(f => f.id == value);
                if (fleet) {
                    districtTomSelect.setValue(fleet.district_id, true);
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

    function updateFleetOptions(fleetList, showNoneOnly = false) {
        fleetTomSelect.clearOptions();

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

        fleetTomSelect.addOption({
            value: 'none',
            text: 'None',
            fleet_number: 'None',
            fleet_name: 'None'
        });

        fleetTomSelect.refreshOptions(false);
    }

    // Initialize fleet options
    updateFleetOptions(fleets, false);

    // Set initial values from data attributes
    const initialDistrictId = districtSelect.dataset.value || districtSelect.dataset.oldValue;
    const initialFleetId = fleetSelect.dataset.value || fleetSelect.dataset.oldValue;

    if (initialDistrictId && initialDistrictId !== '' && initialDistrictId !== 'null') {
        districtTomSelect.setValue(initialDistrictId);
    }

    if (initialFleetId && initialFleetId !== '' && initialFleetId !== 'null') {
        fleetTomSelect.setValue(initialFleetId);
    }

    return { districtTomSelect, fleetTomSelect };
}
