<x-layout>
    <x-slot:title>
        Reset Password
    </x-slot:title>

    <div class="hero min-h-[calc(100vh-16rem)]">
        <div class="hero-content flex-col">
            <div class="card w-96 bg-base-100">
                <div class="card-body">
                    <h1 class="text-3xl font-bold text-center mb-2">Set New Password</h1>
                    <p class="text-center text-sm text-base-content/70 mb-6">
                        Enter your new password below.
                    </p>

                    <form id="reset-password-form" method="POST" action="{{ route('password.update') }}">
                        @csrf

                        <!-- Hidden fields -->
                        <input type="hidden" name="token" value="{{ $token }}">
                        <input type="hidden" name="email" value="{{ request('email') }}">

                        <!-- Password -->
                        <div class="floating-label mb-6">
                            <x-password-input name="password" id="password" autofocus />
                            <span>New Password</span>
                        </div>
                        <div id="password-error" class="label -mt-4 mb-2 hidden">
                            <span class="label-text-alt text-error"></span>
                        </div>

                        <!-- Password Confirmation -->
                        <div class="floating-label mb-6">
                            <x-password-input name="password_confirmation" id="password_confirmation" />
                            <span>Confirm Password</span>
                        </div>
                        <div id="password-confirmation-error" class="label -mt-4 mb-2 hidden">
                            <span class="label-text-alt text-error"></span>
                        </div>

                        <div id="email-error" class="label mb-2 hidden">
                            <span class="label-text-alt text-error"></span>
                        </div>

                        <!-- Submit Button -->
                        <div class="form-control mt-8">
                            <button type="submit" id="submit-btn" class="btn btn-primary btn-sm w-full">
                                <span id="submit-text">Reset Password</span>
                                <span id="loading-spinner" class="loading loading-spinner loading-sm hidden"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast notification container -->
    <div id="toast-container" class="toast toast-top toast-end"></div>

    <script type="module">
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('reset-password-form');
            const submitBtn = document.getElementById('submit-btn');
            const submitText = document.getElementById('submit-text');
            const loadingSpinner = document.getElementById('loading-spinner');
            const passwordInput = document.getElementById('password');
            const passwordConfirmationInput = document.getElementById('password_confirmation');
            const passwordError = document.getElementById('password-error');
            const passwordConfirmationError = document.getElementById('password-confirmation-error');
            const emailError = document.getElementById('email-error');

            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                // Clear previous errors
                passwordError.classList.add('hidden');
                passwordConfirmationError.classList.add('hidden');
                emailError.classList.add('hidden');
                passwordInput.classList.remove('input-error');
                passwordConfirmationInput.classList.remove('input-error');

                // Client-side validation
                if (passwordInput.value.length < 8) {
                    passwordError.querySelector('span').textContent = 'Password must be at least 8 characters.';
                    passwordError.classList.remove('hidden');
                    passwordInput.classList.add('input-error');
                    return;
                }

                if (passwordInput.value !== passwordConfirmationInput.value) {
                    passwordConfirmationError.querySelector('span').textContent = 'Passwords do not match.';
                    passwordConfirmationError.classList.remove('hidden');
                    passwordConfirmationInput.classList.add('input-error');
                    return;
                }

                // Show loading state
                submitBtn.disabled = true;
                submitText.classList.add('hidden');
                loadingSpinner.classList.remove('hidden');

                try {
                    const formData = new FormData(form);
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const data = await response.json();

                    if (response.ok && data.success) {
                        // Show success toast
                        window.showToast('success', data.message);
                        // Redirect to login after a short delay
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 1500);
                    } else {
                        // Handle validation errors
                        if (data.errors) {
                            if (data.errors.password) {
                                passwordError.querySelector('span').textContent = data.errors.password[0];
                                passwordError.classList.remove('hidden');
                                passwordInput.classList.add('input-error');
                            }
                            if (data.errors.email) {
                                emailError.querySelector('span').textContent = data.errors.email[0];
                                emailError.classList.remove('hidden');
                            }
                        } else if (data.message) {
                            window.showToast('error', data.message);
                        }
                    }
                } catch (error) {
                    window.showToast('error', 'An error occurred. Please try again.');
                } finally {
                    // Reset button state
                    submitBtn.disabled = false;
                    submitText.classList.remove('hidden');
                    loadingSpinner.classList.add('hidden');
                }
            });
        });
    </script>
</x-layout>
