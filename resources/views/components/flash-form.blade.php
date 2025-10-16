@props(['flash' => null, 'action', 'method' => 'POST', 'submitText' => 'Log Activity'])

<form action="{{ $action }}" method="POST">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Date -->
        <div class="form-control">
            <label class="label">
                <span class="label-text font-semibold">Date <span class="text-error">*</span></span>
            </label>
            <input type="date" name="date" value="{{ old('date', $flash?->date?->format('Y-m-d')) }}"
                   max="{{ now()->addDay()->format('Y-m-d') }}"
                   class="input input-bordered @error('date') input-error @enderror" required>
            @error('date')
                <label class="label">
                    <span class="label-text-alt text-error">{{ $message }}</span>
                </label>
            @enderror
        </div>

        <!-- Activity Type -->
        <div class="form-control">
            <label class="label">
                <span class="label-text font-semibold">Activity Type <span class="text-error">*</span></span>
            </label>
            <select name="activity_type" class="select select-bordered @error('activity_type') select-error @enderror" required>
                <option value="" disabled {{ old('activity_type', $flash?->activity_type) ? '' : 'selected' }} hidden>Select activity type</option>
                <option value="sailing" {{ old('activity_type', $flash?->activity_type) == 'sailing' ? 'selected' : '' }}>Sailing</option>
                <option value="maintenance" {{ old('activity_type', $flash?->activity_type) == 'maintenance' ? 'selected' : '' }}>Boat/Trailer Maintenance</option>
                <option value="race_committee" {{ old('activity_type', $flash?->activity_type) == 'race_committee' ? 'selected' : '' }}>Race Committee Work</option>
            </select>
            @error('activity_type')
                <label class="label">
                    <span class="label-text-alt text-error">{{ $message }}</span>
                </label>
            @enderror
        </div>

        <!-- Sailing Type -->
        <div class="form-control">
            <label class="label">
                <span class="label-text font-semibold">Sailing Type <span class="text-error" id="sailing-type-required">*</span></span>
            </label>
            <select name="event_type" id="sailing_type" class="select select-bordered @error('event_type') select-error @enderror">
                <option value="" disabled {{ old('event_type', $flash?->event_type) ? '' : 'selected' }} hidden>Select sailing type</option>
                <option value="regatta" {{ old('event_type', $flash?->event_type) == 'regatta' ? 'selected' : '' }}>Regatta</option>
                <option value="club_race" {{ old('event_type', $flash?->event_type) == 'club_race' ? 'selected' : '' }}>Club Race</option>
                <option value="practice" {{ old('event_type', $flash?->event_type) == 'practice' ? 'selected' : '' }}>Practice</option>
                <option value="leisure" {{ old('event_type', $flash?->event_type) == 'leisure' ? 'selected' : '' }}>Leisure</option>
            </select>
            <label class="label">
                <span class="label-text-alt text-base-content/60" id="sailing-type-help">All sailing types count equally toward awards.</span>
            </label>
            @error('event_type')
                <label class="label">
                    <span class="label-text-alt text-error">{{ $message }}</span>
                </label>
            @enderror
        </div>

        <!-- Location -->
        <div class="form-control">
            <label class="label">
                <span class="label-text font-semibold">Location</span>
            </label>
            <input type="text" name="location" value="{{ old('location', $flash?->location) }}"
                   placeholder="e.g., Lake Norman, NC"
                   class="input input-bordered" maxlength="255">
        </div>

        <!-- Sail Number -->
        <div class="form-control">
            <label class="label">
                <span class="label-text font-semibold">Sail Number</span>
            </label>
            <input type="text" inputmode="numeric" pattern="[0-9]*" name="sail_number" value="{{ old('sail_number', $flash?->sail_number) }}"
                   placeholder="e.g., 15234"
                   class="input input-bordered">
        </div>
    </div>

    <!-- Notes -->
    <div class="form-control mt-4">
        <label class="label">
            <span class="label-text font-semibold">Notes</span>
        </label>
        <textarea name="notes" rows="3"
                  placeholder="Optional notes about your day on the water..."
                  class="textarea textarea-bordered">{{ old('notes', $flash?->notes) }}</textarea>
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