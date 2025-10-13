<x-layout>
    <x-slot:title>
        All Activities
    </x-slot:title>

    <div class="max-w-6xl mx-auto">
        <div class="mb-6">
            <h1 class="text-3xl font-bold">All Flash Activities</h1>
            <p class="text-base-content/70 mt-2">Recent sailing days logged by Lightning sailors</p>
        </div>

        <!-- Flash Entry Form -->
        <div class="card bg-base-100 shadow mb-6">
            <div class="card-body">
                <h2 class="card-title text-xl mb-4">Log a New Activity</h2>
                <form action="{{ route('flashes.store') }}" method="POST">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Date -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text font-semibold">Date <span class="text-error">*</span></span>
                            </label>
                            <input type="date" name="date" value="{{ old('date') }}"
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
                                <option value="" disabled selected hidden>Select activity type</option>
                                <option value="sailing" {{ old('activity_type') == 'sailing' ? 'selected' : '' }}>Sailing</option>
                                <option value="maintenance" {{ old('activity_type') == 'maintenance' ? 'selected' : '' }}>Boat/Trailer Maintenance</option>
                                <option value="race_committee" {{ old('activity_type') == 'race_committee' ? 'selected' : '' }}>Race Committee Work</option>
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
                                <option value="" disabled selected hidden>Select sailing type</option>
                                <option value="regatta" {{ old('event_type') == 'regatta' ? 'selected' : '' }}>Regatta</option>
                                <option value="club_race" {{ old('event_type') == 'club_race' ? 'selected' : '' }}>Club Race</option>
                                <option value="practice" {{ old('event_type') == 'practice' ? 'selected' : '' }}>Practice</option>
                                <option value="leisure" {{ old('event_type') == 'leisure' ? 'selected' : '' }}>Leisure</option>
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

                        <script>
                            // Enable/disable sailing_type based on activity_type
                            const activityType = document.querySelector('select[name="activity_type"]');
                            const sailingType = document.getElementById('sailing_type');
                            const sailingTypeRequired = document.getElementById('sailing-type-required');
                            const sailingTypeHelp = document.getElementById('sailing-type-help');

                            function updateSailingTypeState() {
                                if (activityType.value === 'sailing') {
                                    sailingType.disabled = false;
                                    sailingType.required = true;
                                    sailingType.classList.remove('select-disabled');
                                    sailingTypeRequired.style.display = 'inline';
                                    sailingTypeHelp.textContent = 'All sailing types count equally toward awards.';
                                } else {
                                    sailingType.disabled = true;
                                    sailingType.required = false;
                                    sailingType.value = '';
                                    sailingType.classList.add('select-disabled');
                                    sailingTypeRequired.style.display = 'none';
                                    sailingTypeHelp.textContent = 'Only applicable for sailing activities.';
                                }
                            }

                            activityType.addEventListener('change', updateSailingTypeState);
                            // Run on page load
                            updateSailingTypeState();
                        </script>

                        <!-- Yacht Club -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text font-semibold">Yacht Club</span>
                            </label>
                            <input type="text" name="yacht_club" value="{{ old('yacht_club') }}"
                                   placeholder="e.g., San Diego Yacht Club"
                                   class="input input-bordered" maxlength="100">
                        </div>

                        <!-- Location -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text font-semibold">Location</span>
                            </label>
                            <input type="text" name="location" value="{{ old('location') }}"
                                   placeholder="e.g., Lake Norman, NC"
                                   class="input input-bordered" maxlength="255">
                        </div>

                        <!-- Fleet Number -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text font-semibold">Fleet Number</span>
                            </label>
                            <input type="text" inputmode="numeric" pattern="[0-9]*" name="fleet_number" value="{{ old('fleet_number') }}"
                                   placeholder="e.g., 50"
                                   class="input input-bordered">
                        </div>

                        <!-- Sail Number -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text font-semibold">Sail Number</span>
                            </label>
                            <input type="text" inputmode="numeric" pattern="[0-9]*" name="sail_number" value="{{ old('sail_number') }}"
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
                                  class="textarea textarea-bordered">{{ old('notes') }}</textarea>
                    </div>

                    <!-- Submit Button -->
                    <div class="form-control mt-6">
                        <button type="submit" class="btn btn-primary">
                            Log Activity
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="space-y-4">
            @forelse($flashes as $flash)
                <x-flash-card :flash="$flash" />
            @empty
                <div class="hero py-12">
                    <div class="hero-content text-center">
                        <div>
                            <svg class="mx-auto h-12 w-12 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"></path>
                            </svg>
                            <p class="mt-4 text-base-content/60">No activities yet. Be the first to log your flash!</p>
                        </div>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</x-layout>
