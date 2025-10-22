@props(['flash' => null, 'action', 'method' => 'POST', 'submitText' => 'Log Activity'])

<form action="{{ $action }}" method="POST">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">
        <!-- Date -->
        <div class="mb-6 floating-label-visible">
            <input type="date" name="date" value="{{ old('date', $flash?->date?->format('Y-m-d')) }}"
                   max="{{ now()->addDay()->format('Y-m-d') }}"
                   class="input input-bordered w-full @error('date') input-error @enderror" required>
            <label>Date</label>
            @error('date')
                <div class="label">
                    <span class="label-text-alt text-error">{{ $message }}</span>
                </div>
            @enderror
        </div>

        <!-- Location -->
        <div class="mb-6 floating-label-visible">
            <input type="text" name="location" value="{{ old('location', $flash?->location) }}"
                   placeholder="Lake Norman, NC"
                   class="input input-bordered w-full" maxlength="255">
            <label>Location (optional)</label>
        </div>

        <!-- Activity Type -->
        <div class="mb-6">
            <label class="form-control w-full">
                <div class="label">
                    <span class="label-text">Activity Type</span>
                </div>
                <select name="activity_type" class="select select-bordered @error('activity_type') select-error @enderror" required>
                    <option value="" disabled {{ old('activity_type', $flash?->activity_type) ? '' : 'selected' }}>Select activity type</option>
                    <option value="sailing" {{ old('activity_type', $flash?->activity_type) == 'sailing' ? 'selected' : '' }}>Sailing</option>
                    <option value="maintenance" {{ old('activity_type', $flash?->activity_type) == 'maintenance' ? 'selected' : '' }}>Boat/Trailer Maintenance</option>
                    <option value="race_committee" {{ old('activity_type', $flash?->activity_type) == 'race_committee' ? 'selected' : '' }}>Race Committee Work</option>
                </select>
                @error('activity_type')
                    <div class="label">
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    </div>
                @enderror
            </label>
        </div>

        <!-- Sail Number -->
        <div class="mb-6 floating-label-visible">
            <input type="text" inputmode="numeric" pattern="[0-9]*" name="sail_number" value="{{ old('sail_number', $flash?->sail_number) }}"
                   placeholder="15234"
                   class="input input-bordered w-full">
            <label>Sail Number (optional)</label>
        </div>

        <!-- Sailing Type -->
        <div class="mb-6 md:col-span-2">
            <label class="form-control w-full">
                <div class="label">
                    <span class="label-text flex items-center gap-1" id="sailing-type-label">
                        Sailing Type
                        <div class="tooltip tooltip-right" data-tip="Helps the Class understand our constituents">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-base-content/40 hover:text-base-content/70 cursor-help" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </span>
                </div>
                <select name="event_type" id="sailing_type" class="select select-bordered @error('event_type') select-error @enderror">
                    <option value="" disabled {{ old('event_type', $flash?->event_type) ? '' : 'selected' }}>Select sailing type - All count equally</option>
                    <option value="regatta" {{ old('event_type', $flash?->event_type) == 'regatta' ? 'selected' : '' }}>Regatta</option>
                    <option value="club_race" {{ old('event_type', $flash?->event_type) == 'club_race' ? 'selected' : '' }}>Club Race</option>
                    <option value="practice" {{ old('event_type', $flash?->event_type) == 'practice' ? 'selected' : '' }}>Practice</option>
                    <option value="leisure" {{ old('event_type', $flash?->event_type) == 'leisure' ? 'selected' : '' }}>Day Sailing</option>
                </select>
                @error('event_type')
                    <div class="label">
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    </div>
                @enderror
            </label>
        </div>
    </div>

    <!-- Notes -->
    <div class="mb-6 floating-label-visible">
        <textarea name="notes" rows="3"
                  placeholder="Tell us about your day on the water..."
                  class="textarea textarea-bordered w-full">{{ old('notes', $flash?->notes) }}</textarea>
        <label>Notes (optional)</label>
    </div>

    <!-- Submit Button -->
    <div class="form-control mt-6">
        {{ $slot }}
        @if($slot->isEmpty())
            <button type="submit" class="btn btn-primary">
                {{ $submitText }}
            </button>
        @endif
    </div>
</form>