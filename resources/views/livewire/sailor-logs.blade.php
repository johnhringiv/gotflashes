<div>
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold">Sailor Logs</h1>
        <p class="text-base-content/70 mt-1">View and export all flash activity</p>
    </div>

    <!-- Filters -->
    <div class="card bg-base-100 shadow mb-6">
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Year Filter -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Year</span>
                    </label>
                    <select wire:model.live="selectedYear" class="select select-bordered">
                        @foreach($availableYears as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- District Filter -->
                <div class="form-control" wire:ignore>
                    <label class="label">
                        <span class="label-text font-semibold">District</span>
                    </label>
                    <select id="sailor-logs-district-select"
                            class="select select-bordered"
                            data-value="{{ $selectedDistrict }}">
                        <option value="">All Districts</option>
                        @foreach($availableDistricts as $district)
                            <option value="{{ $district->id }}" {{ $selectedDistrict == $district->id ? 'selected' : '' }}>
                                {{ $district->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Fleet Filter -->
                <div class="form-control" wire:ignore>
                    <label class="label">
                        <span class="label-text font-semibold">Fleet</span>
                    </label>
                    <select id="sailor-logs-fleet-select"
                            class="select select-bordered"
                            data-value="{{ $selectedFleet }}">
                        <option value="">All Fleets</option>
                        @foreach($availableFleets as $fleet)
                            <option value="{{ $fleet->id }}"
                                    {{ $selectedFleet == $fleet->id ? 'selected' : '' }}
                                    data-fleet-number="{{ $fleet->fleet_number }}"
                                    data-district-id="{{ $fleet->district_id ?? '' }}"
                                    data-district-name="{{ $fleet->district_name ?? '' }}">
                                Fleet {{ $fleet->fleet_number }}
                                @if(!$selectedDistrict && isset($fleet->district_name))
                                    ({{ $fleet->district_name }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Search -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Search</span>
                    </label>
                    <input type="text"
                           wire:model.live.debounce.300ms="searchQuery"
                           placeholder="Name or email..."
                           class="input input-bordered">
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="mb-6 flex items-center justify-between">
        <div class="text-sm text-base-content/70">
            Showing <span class="font-bold">{{ $flashes->total() }}</span> {{ str('entry')->plural($flashes->total()) }}
        </div>

        <div class="flex gap-2">
            <button wire:click="clearFilters"
                    class="btn btn-error btn-sm gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                Clear Filters
            </button>

            <button wire:click="exportCsv"
                    class="btn btn-primary btn-sm gap-2"
                    wire:loading.attr="disabled"
                    wire:target="exportCsv">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                <span wire:loading.remove wire:target="exportCsv">Export CSV</span>
                <span wire:loading wire:target="exportCsv">Exporting...</span>
            </button>
        </div>
    </div>

    <!-- Flashes Table -->
    @if($flashes->count() > 0)
        <div class="card bg-base-100 shadow overflow-x-auto">
            <table class="table table-zebra">
                <thead class="bg-base-300 border-b-2 border-base-content/20">
                    <tr>
                        <th>Date</th>
                        <th>Sailor</th>
                        <th>Activity</th>
                        <th>Event Type</th>
                        <th>Location</th>
                        <th>Sail #</th>
                        <th>District</th>
                        <th>Fleet</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($flashes as $flash)
                        @php
                            $membership = $flash->user->membershipForYear($selectedYear);
                        @endphp
                        <tr>
                            <td class="font-mono text-sm">{{ $flash->date->format('M d, Y') }}</td>
                            <td>
                                <div class="font-semibold">{{ $flash->user->name }}</div>
                                <div class="text-xs text-base-content/60">
                                    {{ $flash->user->email }}
                                    @if($flash->user->email_verified_at)
                                        <div class="tooltip tooltip-right" data-tip="Email verified">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 inline-block ml-1 text-success" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                        </div>
                                    @else
                                        <div class="tooltip tooltip-right" data-tip="Email not verified (no notification sent)">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 inline-block ml-1 text-warning" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-sm
                                    @if($flash->activity_type === 'sailing') badge-success
                                    @elseif($flash->activity_type === 'maintenance') badge-warning
                                    @else badge-info
                                    @endif">
                                    {{ ucfirst($flash->activity_type) }}
                                </span>
                            </td>
                            <td class="text-sm">
                                @if($flash->event_type)
                                    {{ ucfirst(str_replace('_', ' ', $flash->event_type === 'leisure' ? 'Day Sailing' : $flash->event_type)) }}
                                @else
                                    <span class="text-base-content/40">—</span>
                                @endif
                            </td>
                            <td class="text-sm">{{ $flash->location ?? '—' }}</td>
                            <td class="text-sm">{{ $flash->sail_number ?? '—' }}</td>
                            <td class="text-sm">{{ $membership?->district->name ?? '—' }}</td>
                            <td class="text-sm">{{ $membership?->fleet->fleet_number ?? '—' }}</td>
                            <td class="text-sm max-w-xs truncate" title="{{ $flash->notes }}">
                                {{ $flash->notes ?? '' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($flashes->hasPages())
            <div class="mt-6">
                {{ $flashes->links('pagination::livewire-tailwind') }}
            </div>
        @endif
    @else
        <!-- Empty State -->
        <div class="card bg-base-100 shadow">
            <div class="card-body text-center py-12">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-base-content/20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="text-xl font-bold mt-4">No flash entries found</h3>
                <p class="text-base-content/60 mt-2">
                    @if($searchQuery || $selectedDistrict || $selectedFleet)
                        Try adjusting your filters to see more results.
                    @else
                        There are no flash entries for {{ $selectedYear }}.
                    @endif
                </p>
            </div>
        </div>
    @endif
</div>
