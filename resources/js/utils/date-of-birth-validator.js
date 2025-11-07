/**
 * Initialize date of birth input with auto-formatting for Livewire forms.
 * Formats input as YYYY-MM-DD while user types. Validation is handled by Livewire server-side.
 * Syncing with Livewire is handled automatically by wire:model.blur.
 *
 * @param {HTMLInputElement} dobInput - The date of birth input element
 */
export function initializeDateOfBirthValidator(dobInput) {
    if (!dobInput) return;

    // Auto-format as user types (add hyphens to create YYYY-MM-DD format)
    dobInput.addEventListener('input', function(e) {
        const digits = e.target.value.replace(/\D/g, ''); // Remove non-digits
        let formatted = '';

        // Year (4 digits)
        if (digits.length > 0) {
            formatted = digits.slice(0, 4);
        }

        // Add hyphen and month (2 digits) only if we have month digits
        if (digits.length >= 5) {
            formatted += '-' + digits.slice(4, 6);
        }

        // Add hyphen and day (2 digits) only if we have day digits
        if (digits.length >= 7) {
            formatted += '-' + digits.slice(6, 8);
        }

        e.target.value = formatted;

        // Note: Livewire syncs automatically via wire:model.blur
    });
}