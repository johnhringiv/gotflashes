import './bootstrap';
import './flash-form';
import './register-form';

// Toast notification auto-fade
document.addEventListener('DOMContentLoaded', function() {
    const successToast = document.getElementById('success-toast');
    const warningToast = document.getElementById('warning-toast');

    if (successToast) {
        setTimeout(() => {
            successToast.style.transition = 'opacity 0.5s';
            successToast.style.opacity = '0';
            setTimeout(() => successToast.parentElement.remove(), 500);
        }, 3000);
    }

    if (warningToast) {
        setTimeout(() => {
            warningToast.style.transition = 'opacity 0.5s';
            warningToast.style.opacity = '0';
            setTimeout(() => warningToast.parentElement.remove(), 500);
        }, 6000); // Warning stays for 6 seconds (longer than success)
    }
});
