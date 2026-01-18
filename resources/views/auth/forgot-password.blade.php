<x-layout>
    <x-slot:title>
        Forgot Password
    </x-slot:title>

    <div class="hero min-h-[calc(100vh-16rem)]">
        <div class="hero-content flex-col">
            <div class="card w-96 bg-base-100">
                <div class="card-body">
                    <h1 class="text-3xl font-bold text-center mb-2">Reset Password</h1>
                    <p class="text-center text-sm text-base-content/70 mb-6">
                        Enter your email and we'll send you a password reset link.
                    </p>

                    <form id="forgot-password-form" method="POST" action="{{ route('password.email') }}">
                        @csrf

                        <!-- Email -->
                        <label class="floating-label mb-6">
                            <input type="email"
                                   id="email"
                                   name="email"
                                   placeholder="mail@example.com"
                                   value="{{ old('email') }}"
                                   class="input input-bordered"
                                   required
                                   autofocus>
                            <span>Email</span>
                        </label>
                        <div id="email-error" class="label -mt-4 mb-2 hidden">
                            <span class="label-text-alt text-error"></span>
                        </div>

                        <!-- Submit Button -->
                        <div class="form-control mt-8">
                            <button type="submit" id="submit-btn" class="btn btn-primary btn-sm w-full">
                                <span id="submit-text">Send Reset Link</span>
                                <span id="loading-spinner" class="loading loading-spinner loading-sm hidden"></span>
                            </button>
                        </div>
                    </form>

                    <div class="divider">OR</div>
                    <p class="text-center text-sm">
                        Remember your password?
                        <a href="{{ route('login') }}" class="link link-primary">Back to Login</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast notification container -->
    <div id="toast-container" class="toast toast-top toast-end"></div>

    <script @cspNonce type="module">
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('forgot-password-form');
            const submitBtn = document.getElementById('submit-btn');
            const submitText = document.getElementById('submit-text');
            const loadingSpinner = document.getElementById('loading-spinner');
            const emailInput = document.getElementById('email');
            const emailError = document.getElementById('email-error');

            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                // Clear previous errors
                emailError.classList.add('hidden');
                emailInput.classList.remove('input-error');

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
                        // Clear form
                        form.reset();
                    } else {
                        // Handle validation errors
                        if (data.errors && data.errors.email) {
                            emailError.querySelector('span').textContent = data.errors.email[0];
                            emailError.classList.remove('hidden');
                            emailInput.classList.add('input-error');
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
