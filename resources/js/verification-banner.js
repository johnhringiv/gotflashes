// Email verification banner countdown timer

// Module-level state to prevent race conditions from multiple initializations
let cooldown = 0;
let intervalId = null;
let livewireListenerRegistered = false;

function formatTime(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return mins > 0 ? `${mins}:${secs.toString().padStart(2, '0')}` : `${secs}s`;
}

function updateUI() {
    const banner = document.getElementById('verification-banner');
    if (!banner) return;

    const resendBtn = banner.querySelector('[data-resend-btn]');
    const cooldownBtn = banner.querySelector('[data-cooldown-btn]');
    const cooldownMessage = banner.querySelector('[data-cooldown-message]');
    const defaultMessage = banner.querySelector('[data-default-message]');

    if (cooldown > 0) {
        // Show cooldown state
        if (cooldownBtn) {
            cooldownBtn.style.display = '';
            cooldownBtn.querySelector('[data-time]').textContent = formatTime(cooldown);
        }
        if (resendBtn) resendBtn.style.display = 'none';
        if (cooldownMessage) cooldownMessage.style.display = '';
        if (defaultMessage) defaultMessage.style.display = 'none';
    } else {
        // Show resend state
        if (cooldownBtn) cooldownBtn.style.display = 'none';
        if (resendBtn) resendBtn.style.display = '';
        if (cooldownMessage) cooldownMessage.style.display = 'none';
        if (defaultMessage) defaultMessage.style.display = '';
    }
}

function startTimer() {
    // Clear any existing interval to prevent duplicate timers
    if (intervalId) {
        clearInterval(intervalId);
        intervalId = null;
    }
    if (cooldown > 0) {
        intervalId = setInterval(() => {
            cooldown--;
            updateUI();
            if (cooldown <= 0) {
                clearInterval(intervalId);
                intervalId = null;
            }
        }, 1000);
    }
}

function initVerificationBanner() {
    const banner = document.getElementById('verification-banner');
    if (!banner) return;

    // Read initial cooldown from data attribute (only on first init or if higher)
    const dataCooldown = parseInt(banner.dataset.cooldown || '0', 10);
    if (dataCooldown > cooldown) {
        cooldown = dataCooldown;
    }

    // Initial UI update and timer start
    updateUI();
    startTimer();
}

// Register Livewire event listener ONCE (outside of initVerificationBanner)
// This prevents duplicate listeners from being added on each reinit
function registerLivewireListener() {
    if (livewireListenerRegistered) return;
    livewireListenerRegistered = true;

    function setupListeners() {
        Livewire.on('verification-sent', (event) => {
            const data = event[0] || event;
            cooldown = data.cooldown || 180;
            updateUI();
            startTimer();
        });

        Livewire.hook('morph.updated', ({ el }) => {
            if (el.id === 'verification-banner' || el.querySelector?.('#verification-banner')) {
                requestAnimationFrame(initVerificationBanner);
            }
        });
    }

    // Check if Livewire is already initialized, otherwise wait for init event
    if (typeof Livewire !== 'undefined' && Livewire.on) {
        setupListeners();
    } else {
        document.addEventListener('livewire:init', setupListeners);
    }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    initVerificationBanner();
    registerLivewireListener();
});

// Reinitialize when Livewire updates the DOM (e.g., after navigation)
document.addEventListener('livewire:navigated', initVerificationBanner);
