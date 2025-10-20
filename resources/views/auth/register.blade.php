<x-layout>
    <x-slot:title>
        Register
    </x-slot:title>

    <style>
        /* Fix Tom Select dropdown visibility */
        .ts-dropdown {
            position: absolute;
            z-index: 9999 !important;
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            max-height: 300px;
            overflow-y: auto;
        }

        .ts-dropdown .option {
            padding: 0.5rem 0.75rem;
            cursor: pointer;
            color: #1f2937;
        }

        .ts-dropdown .option:hover,
        .ts-dropdown .option.active {
            background-color: #f3f4f6;
        }

        .ts-dropdown .optgroup-header {
            padding: 0.5rem 0.75rem;
            font-weight: 600;
            color: #6b7280;
        }

        .ts-control {
            min-height: 3rem;
            display: flex;
            align-items: center;
            padding: 0.5rem 0.75rem;
        }

        .ts-wrapper.single .ts-control {
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
        }

        .ts-wrapper.single .ts-control input {
            color: #1f2937;
        }

        .ts-wrapper .item {
            line-height: normal;
        }
    </style>

    <div class="hero min-h-[calc(100vh-16rem)]">
        <div class="hero-content flex-col">
            <div class="card w-full max-w-2xl bg-base-100">
                <div class="card-body">
                    <h1 class="text-3xl font-bold text-center mb-6">Create Account</h1>

                    <form method="POST" action="/register">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">
                            <!-- First Name -->
                            <div class="mb-6 floating-label-visible">
                                <input type="text"
                                       name="first_name"
                                       placeholder="John"
                                       value="{{ old('first_name') }}"
                                       class="input input-bordered w-full @error('first_name') input-error @enderror"
                                       required>
                                <label>First Name</label>
                                @error('first_name')
                                    <div class="label">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </div>
                                @enderror
                            </div>

                            <!-- Last Name -->
                            <div class="mb-6 floating-label-visible">
                                <input type="text"
                                       name="last_name"
                                       placeholder="Doe"
                                       value="{{ old('last_name') }}"
                                       class="input input-bordered w-full @error('last_name') input-error @enderror"
                                       required>
                                <label>Last Name</label>
                                @error('last_name')
                                    <div class="label">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </div>
                                @enderror
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="mb-6 floating-label-visible">
                            <input type="email"
                                   name="email"
                                   placeholder="mail@example.com"
                                   value="{{ old('email') }}"
                                   class="input input-bordered w-full @error('email') input-error @enderror"
                                   required>
                            <label>Email</label>
                            @error('email')
                                <div class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </div>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">
                            <!-- Password -->
                            <div class="mb-6 floating-label-visible">
                                <input type="password"
                                       name="password"
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
                                       name="password_confirmation"
                                       placeholder="••••••••"
                                       class="input input-bordered w-full"
                                       required>
                                <label>Confirm Password</label>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">
                            <!-- Date of Birth -->
                            <div class="mb-6 floating-label-visible">
                                <input type="text"
                                       name="date_of_birth"
                                       placeholder="YYYY-MM-DD"
                                       value="{{ old('date_of_birth') }}"
                                       class="input input-bordered w-full @error('date_of_birth') input-error @enderror"
                                       maxlength="10"
                                       required>
                                <label>Date of Birth</label>
                                @error('date_of_birth')
                                    <div class="label">
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
                        <div class="mb-6 floating-label-visible">
                            <input type="text"
                                   name="address_line1"
                                   placeholder="123 Main Street"
                                   value="{{ old('address_line1') }}"
                                   class="input input-bordered w-full @error('address_line1') input-error @enderror"
                                   required>
                            <label>Street Address</label>
                            @error('address_line1')
                                <div class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </div>
                            @enderror
                        </div>

                        <!-- Address Line 2 -->
                        <div class="mb-6 floating-label-visible">
                            <input type="text"
                                   name="address_line2"
                                   placeholder="Apt 4B"
                                   value="{{ old('address_line2') }}"
                                   class="input input-bordered w-full">
                            <label>Address Line 2 (optional)</label>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-x-4">
                            <!-- City -->
                            <div class="mb-6 floating-label-visible md:col-span-1">
                                <input type="text"
                                       name="city"
                                       placeholder="San Diego"
                                       value="{{ old('city') }}"
                                       class="input input-bordered w-full @error('city') input-error @enderror"
                                       required>
                                <label>City</label>
                                @error('city')
                                    <div class="label">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </div>
                                @enderror
                            </div>

                            <!-- State -->
                            <div class="mb-6 floating-label-visible">
                                <input type="text"
                                       name="state"
                                       placeholder="CA"
                                       value="{{ old('state') }}"
                                       class="input input-bordered w-full @error('state') input-error @enderror"
                                       required>
                                <label>State/Province</label>
                                @error('state')
                                    <div class="label">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </div>
                                @enderror
                            </div>

                            <!-- Zip Code -->
                            <div class="mb-6 floating-label-visible">
                                <input type="text"
                                       name="zip_code"
                                       placeholder="92101"
                                       value="{{ old('zip_code') }}"
                                       class="input input-bordered w-full @error('zip_code') input-error @enderror"
                                       required>
                                <label>Zip/Postal Code</label>
                                @error('zip_code')
                                    <div class="label">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </div>
                                @enderror
                            </div>
                        </div>

                        <!-- Country -->
                        <div class="mb-6 floating-label-visible">
                            <input type="text"
                                   name="country"
                                   placeholder="United States"
                                   value="{{ old('country', 'United States') }}"
                                   class="input input-bordered w-full @error('country') input-error @enderror"
                                   required>
                            <label>Country</label>
                            @error('country')
                                <div class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </div>
                            @enderror
                        </div>

                        <div class="divider my-6">Lightning Class Info</div>
                        <p class="text-sm text-base-content/70 mb-4">Choose your district and fleet to compete on the leaderboard—let's see who can log the most days on the water!</p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">
                            <!-- District -->
                            <div class="mb-6">
                                <label class="form-control w-full">
                                    <div class="label">
                                        <span class="label-text flex items-center gap-1">
                                            District
                                            <div class="tooltip tooltip-right" data-tip="Select 'Unaffiliated/None' if you're not in a district">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-base-content/40 hover:text-base-content/70 cursor-help" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </div>
                                        </span>
                                    </div>
                                    <select name="district_id" id="district-select" class="select select-bordered @error('district_id') select-error @enderror" data-old-value="{{ old('district_id') }}" required>
                                        <option value="">Select district...</option>
                                    </select>
                                    @error('district_id')
                                        <div class="label">
                                            <span class="label-text-alt text-error">{{ $message }}</span>
                                        </div>
                                    @enderror
                                </label>
                            </div>

                            <!-- Fleet -->
                            <div class="mb-6">
                                <label class="form-control w-full">
                                    <div class="label">
                                        <span class="label-text flex items-center gap-1">
                                            Fleet
                                            <div class="tooltip tooltip-right" data-tip="Search by name or number, or select 'None' if unaffiliated">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-base-content/40 hover:text-base-content/70 cursor-help" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </div>
                                        </span>
                                    </div>
                                    <select name="fleet_id" id="fleet-select" class="select select-bordered @error('fleet_id') select-error @enderror" data-old-value="{{ old('fleet_id') }}" required>
                                        <option value="">Select fleet...</option>
                                    </select>
                                    @error('fleet_id')
                                        <div class="label">
                                            <span class="label-text-alt text-error">{{ $message }}</span>
                                        </div>
                                    @enderror
                                </label>
                            </div>
                        </div>

                        <div class="divider my-6">Optional</div>

                        <!-- Yacht Club -->
                        <div class="mb-6 floating-label-visible">
                            <input type="text"
                                   name="yacht_club"
                                   placeholder="e.g., San Diego Yacht Club"
                                   value="{{ old('yacht_club') }}"
                                   class="input input-bordered w-full @error('yacht_club') input-error @enderror">
                            <label>Yacht Club</label>
                            @error('yacht_club')
                                <div class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </div>
                            @enderror
                        </div>

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