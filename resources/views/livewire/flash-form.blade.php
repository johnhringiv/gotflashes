<div>
    <form wire:submit="save" novalidate>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">
            <!-- Date(s) - order-1 on mobile, col 1 on desktop -->
            <div class="mb-6 floating-label-visible order-1">
                @if($mode === 'edit')
                    {{-- Single date picker for edit mode --}}
                    <input type="text" id="date-picker-single"
                           name="date"
                           data-mode="single"
                           data-default-date="{{ $date }}"
                           data-min-date="{{ $minDate->format('Y-m-d') }}"
                           data-max-date="{{ $maxDate->format('Y-m-d') }}"
                           data-existing-dates="{{ json_encode($existingDates) }}"
                           value="{{ $date }}"
                           placeholder="Select date"
                           class="input input-bordered w-full @error('date') input-error @enderror" required readonly>
                    <label>Date</label>
                @else
                    {{-- Multi-date picker for create mode --}}
                    <input type="text" id="date-picker"
                           data-mode="multiple"
                           data-existing-dates="{{ json_encode($existingDates) }}"
                           data-min-date="{{ $minDate->format('Y-m-d') }}"
                           data-max-date="{{ $maxDate->format('Y-m-d') }}"
                           placeholder="Select date(s)"
                           class="input input-bordered w-full @error('dates') input-error @enderror @error('dates.*') input-error @enderror" required readonly>
                    <label>Date(s)</label>
                @endif
                @error('date')
                    <div class="label">
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    </div>
                @enderror
                @error('dates')
                    <div class="label">
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    </div>
                @enderror
                @error('dates.*')
                    <div class="label">
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    </div>
                @enderror
            </div>

            <!-- Activity Type - order-2 on mobile, col 1 on desktop -->
            <div class="mb-6 floating-label-visible order-2 md:order-3 md:max-w-xs">
                {{-- .live (not .defer): syncs on change so updated() can clear this field's
                     validation error in real time. Worth the per-change render() since the
                     existingDates query it triggers is cached per request. --}}
                <select wire:model.live="activity_type" id="{{ $mode === 'edit' ? 'activity_type_edit' : 'activity_type' }}" class="select select-bordered w-full @error('activity_type') select-error @enderror" required>
                    {{-- hidden: placeholder text shows in the closed control but never
                         as a (checkmarked) row in the open picker --}}
                    <option value="" disabled hidden {{ $activity_type ? '' : 'selected' }}>Select activity type</option>
                    <option value="sailing" {{ $activity_type == 'sailing' ? 'selected' : '' }}>Sailing</option>
                    <option value="maintenance" {{ $activity_type == 'maintenance' ? 'selected' : '' }}>Boat/Trailer Maintenance</option>
                    <option value="race_committee" {{ $activity_type == 'race_committee' ? 'selected' : '' }}>Race Committee Work</option>
                </select>
                <label for="{{ $mode === 'edit' ? 'activity_type_edit' : 'activity_type' }}">Activity Type</label>
                @error('activity_type')
                    <div class="label">
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    </div>
                @enderror
            </div>

            <!-- Sailing Type - order-3 on mobile, col 1 on desktop -->
            <div class="mb-6 floating-label-visible order-3 md:order-5 md:max-w-xs">
                <select wire:model.live="event_type" id="{{ $mode === 'edit' ? 'sailing_type_edit' : 'sailing_type' }}"
                        class="select select-bordered w-full @error('event_type') select-error @enderror {{ $activity_type === 'sailing' ? '' : 'select-disabled' }}"
                        {{ $activity_type === 'sailing' ? 'required' : 'disabled' }}>
                    {{-- hidden: same placeholder pattern as activity type above --}}
                    <option value="" disabled hidden {{ $event_type ? '' : 'selected' }}>
                        {{ $activity_type === 'sailing' ? 'Select sailing type - All count equally' : 'Not applicable' }}
                    </option>
                    <option value="regatta" {{ $event_type == 'regatta' ? 'selected' : '' }}>Regatta</option>
                    <option value="club_race" {{ $event_type == 'club_race' ? 'selected' : '' }}>Club Race</option>
                    <option value="practice" {{ $event_type == 'practice' ? 'selected' : '' }}>Practice</option>
                    <option value="leisure" {{ $event_type == 'leisure' ? 'selected' : '' }}>Day Sailing</option>
                </select>
                {{-- The tooltip <span> is phrasing content, so it may live inside the <label>. --}}
                <label for="{{ $mode === 'edit' ? 'sailing_type_edit' : 'sailing_type' }}" class="flex items-center gap-1">
                    Sailing Type
                    <span class="tooltip tooltip-right tooltip-clamp" data-tip="Helps the Class understand our constituents">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-base-content/40 hover:text-base-content/70 cursor-help" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                </label>
                @error('event_type')
                    <div class="label">
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    </div>
                @enderror
            </div>

            <!-- Location - order-4 on mobile, col 2 on desktop -->
            <div class="mb-6 floating-label-visible order-4 md:order-2">
                <input type="text" wire:model.live.blur="location"
                       placeholder="Lake Norman, NC"
                       class="input input-bordered w-full" maxlength="255">
                <label>Location (optional)</label>
            </div>

            <!-- Sail Number - order-5 on mobile, col 2 on desktop -->
            <div class="mb-6 floating-label-visible order-5 md:order-4">
                <input type="text" inputmode="numeric" pattern="[0-9]*" wire:model.live.blur="sail_number"
                       id="{{ $mode === 'edit' ? 'sail_number_edit' : 'sail_number' }}"
                       placeholder="15234"
                       class="input input-bordered w-full">
                <label>Sail Number (optional)</label>
            </div>
        </div>

        <!-- Notes -->
        <div class="mb-6 floating-label-visible">
            <textarea wire:model.live.blur="notes" rows="3"
                      placeholder="Tell us about your day on the water..."
                      class="textarea textarea-bordered w-full"></textarea>
            <label>Notes (optional)</label>
        </div>

        <!-- Submit Button -->
        <div class="form-control mt-6">
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">
                    {{ $submitText }}
                </button>
                @if($mode === 'edit')
                    <button type="button" wire:click="$dispatch('close-edit-modal')" class="btn btn-error">
                        Cancel
                    </button>
                @endif
            </div>
        </div>
    </form>
</div>
