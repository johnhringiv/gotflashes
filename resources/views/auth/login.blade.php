<x-layout>
    <x-slot:title>
        Sign In
    </x-slot:title>

    <div class="hero min-h-[calc(100vh-16rem)]">
        <div class="hero-content flex-col">
            <div class="card w-96 bg-base-100">
                <div class="card-body">
                    <h1 class="text-3xl font-bold text-center mb-6">Welcome Back</h1>

                    <form method="POST" action="/login">
                        @csrf

                        <!-- Email -->
                        <div class="mb-6 floating-label-visible">
                            <input type="email"
                                   id="email"
                                   name="email"
                                   placeholder="mail@example.com"
                                   value="{{ old('email') }}"
                                   class="input w-full @error('email') input-error @enderror"
                                   required
                                   autofocus>
                            <label for="email">Email</label>
                            @error('email')
                                <div class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </div>
                            @enderror
                        </div>

                        <!-- Password -->
                        <div class="mb-6 floating-label-visible">
                            <x-password-input name="password" id="password" :class="$errors->has('password') ? 'input-error' : ''" />
                            <label for="password">Password</label>
                            @error('password')
                                <div class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </div>
                            @enderror
                        </div>

                        <!-- Remember Me & Forgot Password -->
                        <div class="form-control mt-4">
                            <div class="flex justify-between items-center">
                                <label class="label cursor-pointer justify-start p-0">
                                    <input type="checkbox"
                                           name="remember"
                                           class="checkbox checkbox-primary">
                                    <span class="label-text ml-2">Remember me</span>
                                </label>
                                <a href="{{ route('password.request') }}" class="link link-primary text-sm">
                                    Forgot your password?
                                </a>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="form-control mt-8">
                            <button type="submit" class="btn btn-primary btn-sm w-full">
                                Sign In
                            </button>
                        </div>
                    </form>

                    <div class="divider">OR</div>
                    <p class="text-center text-sm">
                        Don't have an account?
                        <a href="/register" class="link link-primary">Register</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-layout>
