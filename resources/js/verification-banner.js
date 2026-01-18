// Email verification banner countdown timer

function initVerificationBanner() {
    const banner = document.getElementById('verification-banner');
    if (!banner) return;

    const countdownEl = banner.querySelector('[data-countdown]');
    const resendBtn = banner.querySelector('[data-resend-btn]');
    const cooldownBtn = banner.querySelector('[data-cooldown-btn]');
    const cooldownMessage = banner.querySelector('[data-cooldown-message]');
    const defaultMessage = banner.querySelector('[data-default-message]');

    let cooldown = parseInt(banner.dataset.cooldown || '0', 10);
    let intervalId = null;

    function formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return mins > 0 ? `${mins}:${secs.toString().padStart(2, '0')}` : `${secs}s`;
    }

    function updateUI() {
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
        if (intervalId) {
            clearInterval(intervalId);
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

    // Initial UI update and timer start
    updateUI();
    startTimer();

    // Listen for Livewire verification-sent event to restart countdown
    document.addEventListener('livewire:init', () => {
        Livewire.on('verification-sent', (event) => {
            const data = event[0] || event;
            cooldown = data.cooldown || 180;
            updateUI();
            startTimer();
        });
    });
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', initVerificationBanner);

// Reinitialize when Livewire updates the DOM (e.g., after navigation)
document.addEventListener('livewire:navigated', initVerificationBanner);

// Also handle Livewire morphing
if (typeof Livewire !== 'undefined') {
    document.addEventListener('livewire:init', () => {
        Livewire.hook('morph.updated', ({ el }) => {
            if (el.id === 'verification-banner' || el.querySelector?.('#verification-banner')) {
                requestAnimationFrame(initVerificationBanner);
            }
        });
    });
}
