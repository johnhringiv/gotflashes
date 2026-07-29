import { initCombobox } from './combobox';

/**
 * Wire up the district and fleet comboboxes (both searchable — 36 districts,
 * 135 fleets) with their cross-filtering rules. Options are server-rendered
 * in the Blade — there is no API fetch.
 *
 * "Unaffiliated/None" is a real district and fleet row (fleet_number 0),
 * marked with data-none on its <option>. The None fleet is selectable
 * alongside ANY district; per-fleet district ids ride on data-district-id.
 *
 * Rules:
 * - District change clears the fleet (user must re-select; the server
 *   detects this via the empty fleet value on submit) and narrows the fleet
 *   list to that district's fleets plus None. No district / the None
 *   district shows every fleet — clearing the district (empty the input and
 *   commit) is the way to search across ALL fleets.
 * - Picking a fleet auto-fills its district (silently — no 'change' round
 *   trip, but the Livewire property is synced explicitly).
 * - Picking the None fleet with a blank district fills the district as
 *   Unaffiliated/None too.
 * - Profile safety net: memberships always carry real ids, but if one is
 *   ever missing, fall back to Unaffiliated/None — non-silent so the
 *   Livewire property stays in sync with what the UI shows. On signup,
 *   fields stay empty to show placeholders.
 *
 * @param {Object} config
 * @param {string} config.districtSelectId
 * @param {string} config.fleetSelectId
 * @param {Function} [config.onDistrictChange] - Livewire sync callback
 * @param {Function} [config.onFleetChange] - Livewire sync callback
 */
export function initializeDistrictFleetSelects(config) {
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
    if (fleetSelect._combobox) {
        return {
            districtSelect,
            fleetSelect,
            districtCombobox: districtSelect._combobox,
            fleetCombobox: fleetSelect._combobox
        };
    }

    const noneDistrictId = districtSelect.querySelector('option[data-none]')?.value;
    const noneFleetId = fleetSelect.querySelector('option[data-none]')?.value;

    const districtCombobox = initCombobox(districtSelect, {
        placeholder: 'Select district...'
    });

    const fleetCombobox = initCombobox(fleetSelect, {
        placeholder: 'Select fleet...',
        // Evaluated on every open, so it always sees the current district.
        // Receives the raw <option> ELEMENT (for its data-* attributes),
        // not the combobox's mapped {value, label} object.
        extraFilter: (option) => {
            const district = districtSelect.value;
            if (!district || district === noneDistrictId) return true;
            return option.dataset.districtId === district || option.dataset.none !== undefined;
        }
    });

    // Auto-fill from a fleet pick: no 'change' dispatch (it would clear the
    // fleet that was just picked), so sync the Livewire property explicitly.
    const setDistrictSilently = (value) => {
        districtCombobox.setValue(value, { silent: true });
        if (onDistrictChange) {
            onDistrictChange(value);
        }
    };

    districtSelect.addEventListener('change', () => {
        const value = districtSelect.value;
        if (onDistrictChange) {
            onDistrictChange(value || null);
        }
        fleetCombobox.clear({ silent: true });
        if (onFleetChange) {
            onFleetChange(null);
        }
    });

    fleetSelect.addEventListener('change', () => {
        const value = fleetSelect.value;
        if (!value) {
            if (onFleetChange) {
                onFleetChange(null);
            }
            return;
        }
        if (onFleetChange) {
            onFleetChange(value);
        }
        // By value, not selectedOptions — see the note in combobox.js
        const option = Array.from(fleetSelect.options).find((o) => o.value === value);
        if (option?.dataset.none !== undefined) {
            if (!districtSelect.value) {
                setDistrictSilently(noneDistrictId);
            }
        } else if (option?.dataset.districtId) {
            setDistrictSilently(option.dataset.districtId);
        }
    });

    const isProfilePage = districtSelect.dataset.isProfile === 'true';
    // Order matters: setting the district non-silently CLEARS the fleet via
    // the district change handler, so the fleet fallback must run second.
    if (isProfilePage && !districtSelect.value) {
        districtCombobox.setValue(noneDistrictId);
    }
    if (isProfilePage && !fleetSelect.value) {
        fleetCombobox.setValue(noneFleetId);
    }

    return { districtSelect, fleetSelect, districtCombobox, fleetCombobox };
}
