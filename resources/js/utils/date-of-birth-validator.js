/**
 * Initialize date of birth input with auto-formatting for Livewire forms.
 * Formats input as YYYY-MM-DD while user types. Validation is handled by Livewire server-side.
 *
 * @param {HTMLInputElement} dobInput - The date of birth input element
 * @param {Function} onInputCallback - Optional callback for Livewire sync (receives formatted value)
 */
export function initializeDateOfBirthValidator(dobInput, onInputCallback = null) {
    if (!dobInput) return;

    // Auto-format as user types (add hyphens to create YYYY-MM-DD format)
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

        // Sync with Livewire if callback provided
        if (onInputCallback) {
            onInputCallback(formatted);
        }
    });
}