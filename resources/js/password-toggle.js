/**
 * Password visibility toggle functionality
 * Adds eye icon to toggle password visibility on/off
 */

document.addEventListener('DOMContentLoaded', initPasswordToggles);

// Reinitialize on Livewire navigation
if (typeof Livewire !== 'undefined') {
    Livewire.hook('morph.added', ({ el }) => {
        if (el.querySelector && el.querySelector('.password-toggle-btn')) {
            initPasswordToggles();
        }
    });
}

function initPasswordToggles() {
    document.querySelectorAll('.password-toggle-btn').forEach(btn => {
        if (btn._passwordToggleInitialized) return;
        btn._passwordToggleInitialized = true;

        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const container = this.closest('.password-input-wrapper');
            const input = container.querySelector('input');
            const eyeOpen = this.querySelector('.eye-open');
            const eyeClosed = this.querySelector('.eye-closed');

            if (input.type === 'password') {
                input.type = 'text';
                eyeOpen.classList.add('hidden');
                eyeClosed.classList.remove('hidden');
            } else {
                input.type = 'password';
                eyeOpen.classList.remove('hidden');
                eyeClosed.classList.add('hidden');
            }
        });
    });
}
