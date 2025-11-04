<div class="card w-full max-w-2xl bg-base-100 mx-auto">
    <div class="card-body">
        <h1 class="text-3xl font-bold text-center mb-6">Create Account</h1>

        <form wire:submit="register">
            <x-user-profile-fields>
                <x-slot:passwordFields>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">
                        <!-- Password -->
                        <div class="mb-6 floating-label-visible">
                            <input type="password"
                                   wire:model.blur="password"
                                   placeholder="••••••••"
                                   class="input input-bordered w-full @error('password') input-error @enderror"
                                   required>
                            <label>Password</label>
                            @error('password')
                                <div class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </div>
                            @enderror
                        </div>

                        <!-- Password Confirmation -->
                        <div class="mb-6 floating-label-visible">
                            <input type="password"
                                   wire:model.blur="password_confirmation"
                                   placeholder="••••••••"
                                   class="input input-bordered w-full"
                                   required>
                            <label>Confirm Password</label>
                        </div>
                    </div>
                </x-slot:passwordFields>
            </x-user-profile-fields>

            <!-- Submit Button -->
            <div class="form-control mt-8">
                <button type="submit" class="btn btn-primary btn-sm w-full" wire:loading.attr="disabled" wire:target="register">
                    <span wire:loading.remove wire:target="register">Register</span>
                    <span wire:loading wire:target="register">Creating Account...</span>
                </button>
            </div>
        </form>

        <div class="divider">OR</div>
        <p class="text-center text-sm">
            Already have an account?
            <a href="/login" class="link link-primary">Sign in</a>
        </p>
    </div>
</div>
