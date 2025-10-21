import './bootstrap';
import './flash-form';
import './register-form';

// Toast notification auto-fade
document.addEventListener('DOMContentLoaded', function() {
    const toast = document.getElementById('success-toast');
    if (toast) {
        setTimeout(() => {
            toast.style.transition = 'opacity 0.5s';
            toast.style.opacity = '0';
            setTimeout(() => toast.parentElement.remove(), 500);
        }, 3000);
    }
});
