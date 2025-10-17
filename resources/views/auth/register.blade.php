<x-layout>
    <x-slot:title>
        Register
    </x-slot:title>

    <div class="hero min-h-[calc(100vh-16rem)]">
        <div class="hero-content flex-col">
            <div class="card w-full max-w-2xl bg-base-100">
                <div class="card-body">
                    <h1 class="text-3xl font-bold text-center mb-6">Create Account</h1>

                    <form method="POST" action="/register">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">
                            <!-- First Name -->
                            <div class="mb-6">
                                <label class="floating-label">
                                    <input type="text"
                                           name="first_name"
                                           placeholder="John"
                                           value="{{ old('first_name') }}"
                                           class="input input-bordered w-full @error('first_name') input-error @enderror"
                                           required>
                                    <span>First Name</span>
                                </label>
                                @error('first_name')
                                    <div class="label -mt-4">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </div>
                                @enderror
                            </div>

                            <!-- Last Name -->
                            <div class="mb-6">
                                <label class="floating-label">
                                    <input type="text"
                                           name="last_name"
                                           placeholder="Doe"
                                           value="{{ old('last_name') }}"
                                           class="input input-bordered w-full @error('last_name') input-error @enderror"
                                           required>
                                    <span>Last Name</span>
                                </label>
                                @error('last_name')
                                    <div class="label -mt-4">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </div>
                                @enderror
                            </div>
                        </div>

                        <!-- Email -->
                        <label class="floating-label mb-6">
                            <input type="email"
                                   name="email"
                                   placeholder="mail@example.com"
                                   value="{{ old('email') }}"
                                   class="input input-bordered @error('email') input-error @enderror"
                                   required>
                            <span>Email</span>
                        </label>
                        @error('email')
                            <div class="label -mt-4 mb-2">
                                <span class="label-text-alt text-error">{{ $message }}</span>
                            </div>
                        @enderror

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">
                            <!-- Password -->
                            <div class="mb-6">
                                <label class="floating-label">
                                    <input type="password"
                                           name="password"
                                           placeholder="••••••••"
                                           class="input input-bordered w-full @error('password') input-error @enderror"
                                           required>
                                    <span>Password</span>
                                </label>
                                @error('password')
                                    <div class="label -mt-4">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </div>
                                @enderror
                            </div>

                            <!-- Password Confirmation -->
                            <div class="mb-6">
                                <label class="floating-label">
                                    <input type="password"
                                           name="password_confirmation"
                                           placeholder="••••••••"
                                           class="input input-bordered w-full"
                                           required>
                                    <span>Confirm Password</span>
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">
                            <!-- Date of Birth -->
                            <div class="mb-6">
                                <label class="floating-label">
                                    <input type="text"
                                           name="date_of_birth"
                                           placeholder="YYYY-MM-DD"
                                           value="{{ old('date_of_birth') }}"
                                           class="input input-bordered w-full @error('date_of_birth') input-error @enderror"
                                           maxlength="10"
                                           required>
                                    <span>Date of Birth (YYYY-MM-DD)</span>
                                </label>
                                @error('date_of_birth')
                                    <div class="label -mt-4">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </div>
                                @enderror
                            </div>

                            <!-- Gender -->
                            <div class="mb-6">
                                <label class="form-control w-full">
                                    <div class="label">
                                        <span class="label-text">Gender</span>
                                    </div>
                                    <select name="gender" class="select select-bordered @error('gender') select-error @enderror" required>
                                        <option value="" disabled {{ old('gender') ? '' : 'selected' }}>Select gender</option>
                                        <option value="male" {{ old('gender') == 'male' ? 'selected' : '' }}>Male</option>
                                        <option value="female" {{ old('gender') == 'female' ? 'selected' : '' }}>Female</option>
                                        <option value="non_binary" {{ old('gender') == 'non_binary' ? 'selected' : '' }}>Non-binary</option>
                                        <option value="prefer_not_to_say" {{ old('gender') == 'prefer_not_to_say' ? 'selected' : '' }}>Prefer not to say</option>
                                    </select>
                                    @error('gender')
                                        <div class="label">
                                            <span class="label-text-alt text-error">{{ $message }}</span>
                                        </div>
                                    @enderror
                                </label>
                            </div>
                        </div>

                        <div class="divider my-6">Mailing Address</div>

                        <!-- Street Address -->
                        <label class="floating-label mb-6">
                            <input type="text"
                                   name="address_line1"
                                   placeholder="123 Main Street"
                                   value="{{ old('address_line1') }}"
                                   class="input input-bordered @error('address_line1') input-error @enderror"
                                   required>
                            <span>Street Address</span>
                        </label>
                        @error('address_line1')
                            <div class="label -mt-4 mb-2">
                                <span class="label-text-alt text-error">{{ $message }}</span>
                            </div>
                        @enderror

                        <!-- Address Line 2 -->
                        <label class="floating-label mb-6">
                            <input type="text"
                                   name="address_line2"
                                   placeholder="Apt 4B (optional)"
                                   value="{{ old('address_line2') }}"
                                   class="input input-bordered">
                            <span>Address Line 2 (optional)</span>
                        </label>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-x-4">
                            <!-- City -->
                            <div class="mb-6 md:col-span-1">
                                <label class="floating-label">
                                    <input type="text"
                                           name="city"
                                           placeholder="San Diego"
                                           value="{{ old('city') }}"
                                           class="input input-bordered w-full @error('city') input-error @enderror"
                                           required>
                                    <span>City</span>
                                </label>
                                @error('city')
                                    <div class="label -mt-4">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </div>
                                @enderror
                            </div>

                            <!-- State -->
                            <div class="mb-6">
                                <label class="floating-label">
                                    <input type="text"
                                           name="state"
                                           placeholder="CA"
                                           value="{{ old('state') }}"
                                           class="input input-bordered w-full @error('state') input-error @enderror"
                                           required>
                                    <span>State/Province</span>
                                </label>
                                @error('state')
                                    <div class="label -mt-4">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </div>
                                @enderror
                            </div>

                            <!-- Zip Code -->
                            <div class="mb-6">
                                <label class="floating-label">
                                    <input type="text"
                                           name="zip_code"
                                           placeholder="92101"
                                           value="{{ old('zip_code') }}"
                                           class="input input-bordered w-full @error('zip_code') input-error @enderror"
                                           required>
                                    <span>Zip/Postal Code</span>
                                </label>
                                @error('zip_code')
                                    <div class="label -mt-4">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </div>
                                @enderror
                            </div>
                        </div>

                        <!-- Country -->
                        <label class="floating-label mb-6">
                            <input type="text"
                                   name="country"
                                   placeholder="United States"
                                   value="{{ old('country', 'United States') }}"
                                   class="input input-bordered @error('country') input-error @enderror"
                                   required>
                            <span>Country</span>
                        </label>
                        @error('country')
                            <div class="label -mt-4 mb-2">
                                <span class="label-text-alt text-error">{{ $message }}</span>
                            </div>
                        @enderror

                        <div class="divider my-6">Lightning Class Info (Optional)</div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">
                            <!-- District -->
                            <div class="mb-6">
                                <label class="floating-label">
                                    <input type="text"
                                           name="district"
                                           placeholder="e.g., District 13"
                                           value="{{ old('district') }}"
                                           class="input input-bordered w-full @error('district') input-error @enderror">
                                    <span>District (optional)</span>
                                </label>
                                @error('district')
                                    <div class="label -mt-4">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </div>
                                @enderror
                            </div>

                            <!-- Fleet Number -->
                            <div class="mb-6">
                                <label class="floating-label">
                                    <input type="text"
                                           name="fleet_number"
                                           placeholder="e.g., 50"
                                           value="{{ old('fleet_number') }}"
                                           class="input input-bordered w-full @error('fleet_number') input-error @enderror">
                                    <span>Fleet Number (optional)</span>
                                </label>
                                @error('fleet_number')
                                    <div class="label -mt-4">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </div>
                                @enderror
                            </div>
                        </div>

                        <!-- Yacht Club -->
                        <label class="floating-label mb-6">
                            <input type="text"
                                   name="yacht_club"
                                   placeholder="e.g., San Diego Yacht Club"
                                   value="{{ old('yacht_club') }}"
                                   class="input input-bordered @error('yacht_club') input-error @enderror">
                            <span>Yacht Club (optional)</span>
                        </label>
                        @error('yacht_club')
                            <div class="label -mt-4 mb-2">
                                <span class="label-text-alt text-error">{{ $message }}</span>
                            </div>
                        @enderror

                        <!-- Submit Button -->
                        <div class="form-control mt-8">
                            <button type="submit" class="btn btn-primary btn-sm w-full">
                                Register
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
        </div>
    </div>
</x-layout>