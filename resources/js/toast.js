// Toast notification system for both session flashes and Livewire events

let toastTimeout;

window.showToast = function(type, message) {
    const container = document.getElementById('toast-container');
    if (!container) {
        console.warn('Toast container not found');
        return;
    }

    if (!message) {
        console.warn('Toast message is empty');
        return;
    }

    // Validate type
    const validTypes = ['success', 'warning', 'error', 'info'];
    if (!validTypes.includes(type)) {
        console.warn(`Invalid toast type: ${type}, defaulting to 'info'`);
        type = 'info';
    }

    // Clear any existing timeout
    if (toastTimeout) {
        clearTimeout(toastTimeout);
    }

    // Icon based on type
    const icons = {
        success: `<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0 stroke-current" fill="none" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>`,
        warning: `<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0 stroke-current" fill="none" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>`,
        error: `<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0 stroke-current" fill="none" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>`,
        info: `<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0 stroke-current" fill="none" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>`,
    };

    // Create toast element
    const toast = document.createElement('div');
    toast.className = `alert alert-${type}`;
    toast.innerHTML = `
        ${icons[type] || icons.success}
        <span>${message}</span>
    `;

    // Clear container and add new toast
    container.innerHTML = '';
    container.appendChild(toast);

    toastTimeout = setTimeout(() => {
        toast.style.transition = 'opacity 0.3s ease-out';
        toast.style.opacity = '0';
        setTimeout(() => {
            container.innerHTML = '';
        }, 300);
    }, 5000);
};

// Listen for Livewire toast events
document.addEventListener('livewire:init', () => {
    Livewire.on('toast', (event) => {
        const data = event[0] || event;
        window.showToast(data.type || 'success', data.message);
    });
});
