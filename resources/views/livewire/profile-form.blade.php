<div class="card w-full max-w-2xl bg-base-100 mx-auto">
    <div class="card-body">
        <h1 class="text-3xl font-bold text-center mb-6">Edit Profile</h1>

        <form wire:submit="save">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">
                <!-- First Name -->
                <div class="mb-6 floating-label-visible">
                    <input type="text"
                           wire:model.blur="first_name"
                           placeholder="John"
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
                           wire:model.blur="last_name"
                           placeholder="Doe"
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

            <!-- Email (Read-only) -->
            <div class="mb-6">
                <label class="form-control w-full">
                    <div class="label">
                        <span class="label-text">Email</span>
                    </div>
                    <input type="email"
                           value="{{ $email }}"
                           class="input input-bordered w-full bg-base-200 cursor-not-allowed"
                           disabled
                           readonly>
                    <div class="label">
                        <span class="label-text-alt text-base-content/60">Need to change your email? Contact us at admin@gotflashes.com</span>
                    </div>
                </label>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">
                <!-- Date of Birth -->
                <div class="mb-6 floating-label-visible">
                    <input type="text"
                           wire:model.blur="date_of_birth"
                           name="date_of_birth"
                           placeholder="YYYY-MM-DD"
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
                <div class="mb-6 floating-label-visible">
                    <select wire:model.blur="gender" class="select select-bordered w-full @error('gender') select-error @enderror" required>
                        <option value="" disabled>Select gender</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="non_binary">Non-binary</option>
                        <option value="prefer_not_to_say">Prefer not to say</option>
                    </select>
                    <label>Gender</label>
                    @error('gender')
                        <div class="label">
                            <span class="label-text-alt text-error">{{ $message }}</span>
                        </div>
                    @enderror
                </div>
            </div>

            <div class="divider my-6">Mailing Address</div>

            <!-- Street Address -->
            <div class="mb-6 floating-label-visible">
                <input type="text"
                       wire:model.blur="address_line1"
                       placeholder="123 Main Street"
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
                       wire:model.blur="address_line2"
                       placeholder="Apt 4B"
                       class="input input-bordered w-full">
                <label>Address Line 2 (optional)</label>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-x-4">
                <!-- City -->
                <div class="mb-6 floating-label-visible md:col-span-1">
                    <input type="text"
                           wire:model.blur="city"
                           placeholder="San Diego"
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
                           wire:model.blur="state"
                           placeholder="CA"
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
                           wire:model.blur="zip_code"
                           placeholder="92101"
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
                       wire:model.blur="country"
                       placeholder="United States"
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
            <p class="text-sm text-base-content/70 mb-4">Choose your fleet and district—let's see who gets out there!</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">
                <!-- District -->
                <div class="mb-6" wire:ignore>
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
                        <select name="district_id"
                                id="district-select-profile"
                                class="select select-bordered @error('district_id') select-error @enderror"
                                data-value="{{ $district_id }}"
                                required>
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
                <div class="mb-6" wire:ignore>
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
                        <select name="fleet_id"
                                id="fleet-select-profile"
                                class="select select-bordered @error('fleet_id') select-error @enderror"
                                data-value="{{ $fleet_id }}"
                                required>
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
                       wire:model.blur="yacht_club"
                       placeholder="e.g., San Diego Yacht Club"
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
                <button type="submit" class="btn btn-primary btn-sm w-full" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">Save Changes</span>
                    <span wire:loading wire:target="save">Saving...</span>
                </button>
            </div>
        </form>

        <!-- Export Data Button -->
        <div class="divider my-6"></div>
        <div class="text-center">
            <a href="{{ route('export.user-data') }}" class="btn btn-outline btn-sm gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                Export My Data
            </a>
        </div>
    </div>
</div>

