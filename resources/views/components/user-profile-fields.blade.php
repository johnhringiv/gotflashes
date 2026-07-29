@props(['districtSelectId' => 'district-select', 'fleetSelectId' => 'fleet-select', 'emailSuggestion' => null])

<div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">
    <!-- First Name -->
    <div class="mb-6 floating-label-visible">
        <input type="text"
               id="first_name"
               wire:model.live.blur="first_name"
               placeholder="John"
               class="input w-full @error('first_name') input-error @enderror"
               required>
        <label for="first_name">First Name</label>
        @error('first_name')
            <div class="label">
                <span class="label-text-alt text-error">{{ $message }}</span>
            </div>
        @enderror
    </div>

    <!-- Last Name -->
    <div class="mb-6 floating-label-visible">
        <input type="text"
               id="last_name"
               wire:model.live.blur="last_name"
               placeholder="Doe"
               class="input w-full @error('last_name') input-error @enderror"
               required>
        <label for="last_name">Last Name</label>
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
           id="email"
           wire:model.live.blur="email"
           placeholder="mail@example.com"
           class="input w-full @error('email') input-error @enderror"
           required>
    <label for="email">Email</label>
    @error('email')
        <div class="label">
            <span class="label-text-alt text-error">{{ $message }}</span>
        </div>
    @enderror
    @if ($emailSuggestion)
        <div class="label" wire:key="email-suggestion">
            <span class="label-text-alt">
                Did you mean
                <button type="button"
                        wire:click="applyEmailSuggestion"
                        class="link link-primary font-semibold">{{ $emailSuggestion }}</button>?
            </span>
        </div>
    @endif
</div>

{{ $passwordFields ?? '' }}

<div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">
    <!-- Date of Birth -->
    <div class="mb-6 floating-label-visible">
        <input type="text"
               id="date_of_birth"
               wire:model.live.blur="date_of_birth"
               placeholder="YYYY-MM-DD"
               class="input w-full @error('date_of_birth') input-error @enderror"
               maxlength="10"
               required>
        <label for="date_of_birth">Date of Birth</label>
        @error('date_of_birth')
            <div class="label">
                <span class="label-text-alt text-error">{{ $message }}</span>
            </div>
        @enderror
    </div>

    <!-- Gender -->
    <div class="mb-6 floating-label-visible">
        <select id="gender" wire:model.live.blur="gender" class="select w-full @error('gender') select-error @enderror" required>
            {{-- hidden: placeholder text shows in the closed control but never
                 as a (checkmarked) row in the open picker --}}
            <option value="" disabled hidden>Select gender</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
            <option value="non_binary">Non-binary</option>
            <option value="prefer_not_to_say">Prefer not to say</option>
        </select>
        <label for="gender">Gender</label>
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
           id="address_line1"
           wire:model.live.blur="address_line1"
           placeholder="123 Main Street"
           class="input w-full @error('address_line1') input-error @enderror"
           required>
    <label for="address_line1">Street Address</label>
    @error('address_line1')
        <div class="label">
            <span class="label-text-alt text-error">{{ $message }}</span>
        </div>
    @enderror
</div>

<!-- Address Line 2 -->
<div class="mb-6 floating-label-visible">
    <input type="text"
           id="address_line2"
           wire:model.live.blur="address_line2"
           placeholder="Apt 4B"
           class="input w-full">
    <label for="address_line2">Address Line 2 (optional)</label>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-x-4">
    <!-- City -->
    <div class="mb-6 floating-label-visible md:col-span-1">
        <input type="text"
               id="city"
               wire:model.live.blur="city"
               placeholder="San Diego"
               class="input w-full @error('city') input-error @enderror"
               required>
        <label for="city">City</label>
        @error('city')
            <div class="label">
                <span class="label-text-alt text-error">{{ $message }}</span>
            </div>
        @enderror
    </div>

    <!-- State -->
    <div class="mb-6 floating-label-visible">
        <input type="text"
               id="state"
               wire:model.live.blur="state"
               placeholder="CA"
               class="input w-full @error('state') input-error @enderror"
               required>
        <label for="state">State/Province</label>
        @error('state')
            <div class="label">
                <span class="label-text-alt text-error">{{ $message }}</span>
            </div>
        @enderror
    </div>

    <!-- Zip Code -->
    <div class="mb-6 floating-label-visible">
        <input type="text"
               id="zip_code"
               wire:model.live.blur="zip_code"
               placeholder="92101"
               class="input w-full @error('zip_code') input-error @enderror"
               required>
        <label for="zip_code">Zip/Postal Code</label>
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
           id="country"
           wire:model.live.blur="country"
           placeholder="United States"
           class="input w-full @error('country') input-error @enderror"
           required>
    <label for="country">Country</label>
    @error('country')
        <div class="label">
            <span class="label-text-alt text-error">{{ $message }}</span>
        </div>
    @enderror
</div>

<div class="divider my-6">Lightning Class Info</div>
<p class="text-sm text-base-content/70 mb-4">Choose your fleet and district—let's see who gets out there!</p>

@php
    // Options are server-rendered (the old /api/districts-and-fleets fetch is
    // gone). Queried here rather than passed in so both callers (registration
    // + profile Livewire forms) stay untouched; the component must remain
    // anonymous because $this below binds to the calling Livewire component.
    $districtOptions = \App\Models\District::query()->orderBy('name')->get(['id', 'name']);
    $fleetOptions = \App\Models\Fleet::query()->orderBy('fleet_number')->get(['id', 'fleet_number', 'fleet_name', 'district_id']);
    $noneDistrictId = \App\Models\District::noneId();
    $noneFleetId = \App\Models\Fleet::noneId();
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">
    <!-- District -->
    <div class="mb-6 floating-label-visible @error('district_id') field-error @enderror">
        <div wire:ignore>
            <select name="district_id"
                    id="{{ $districtSelectId }}"
                    class="select w-full"
                    data-is-profile="{{ request()->routeIs('profile') ? 'true' : 'false' }}">
                {{-- hidden: placeholder text shows in the closed control but
                     never as a pickable row (same pattern as the gender select;
                     "no district" is the real Unaffiliated/None row) --}}
                <option value="" disabled hidden @selected(! $this->district_id)>Select district...</option>
                @foreach ($districtOptions as $district)
                    <option value="{{ $district->id }}"
                            @selected($this->district_id == $district->id)
                            @if ($district->id === $noneDistrictId) data-none @endif>
                        {{ $district->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <label for="{{ $districtSelectId }}" class="flex items-center gap-1">
            District
            <span class="tooltip tooltip-right" data-tip="Select 'Unaffiliated/None' if you're not in a district">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-base-content/40 hover:text-base-content/70 cursor-help" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
        </label>
        @error('district_id')
            <div class="label">
                <span class="label-text-alt text-error">{{ $message }}</span>
            </div>
        @enderror
    </div>

    <!-- Fleet -->
    <div class="mb-6 floating-label-visible @error('fleet_id') field-error @enderror">
        <div wire:ignore>
            <select name="fleet_id"
                    id="{{ $fleetSelectId }}"
                    class="select w-full"
                    data-is-profile="{{ request()->routeIs('profile') ? 'true' : 'false' }}">
                <option value="">Select fleet...</option>
                @foreach ($fleetOptions as $fleet)
                    <option value="{{ $fleet->id }}"
                            @selected($this->fleet_id == $fleet->id)
                            data-district-id="{{ $fleet->district_id }}"
                            @if ($fleet->id === $noneFleetId) data-none @endif>
                        {{ $fleet->id === $noneFleetId ? 'None' : 'Fleet '.$fleet->fleet_number.' - '.$fleet->fleet_name }}
                    </option>
                @endforeach
            </select>
        </div>
        <label for="{{ $fleetSelectId }}-input" class="flex items-center gap-1">
            Fleet
            <span class="tooltip tooltip-right" data-tip="Search by name or number, or select 'None' if unaffiliated">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-base-content/40 hover:text-base-content/70 cursor-help" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
        </label>
        @error('fleet_id')
            <div class="label">
                <span class="label-text-alt text-error">{{ $message }}</span>
            </div>
        @enderror
    </div>
</div>

<div class="divider my-6">Optional</div>

<!-- Yacht Club -->
<div class="mb-6 floating-label-visible">
    <input type="text"
           id="yacht_club"
           wire:model.live.blur="yacht_club"
           placeholder="e.g., San Diego Yacht Club"
           class="input w-full @error('yacht_club') input-error @enderror">
    <label for="yacht_club">Yacht Club</label>
    @error('yacht_club')
        <div class="label">
            <span class="label-text-alt text-error">{{ $message }}</span>
        </div>
    @enderror
</div>

