<div>
    <!-- Tabs -->
    <div role="tablist" class="tabs tabs-boxed mb-6 w-fit">
        <button wire:click="switchTab('sailor')"
                role="tab"
                class="tab {{ $tab === 'sailor' ? 'tab-active' : '' }}">
            Sailor
        </button>
        <button wire:click="switchTab('fleet')"
                role="tab"
                class="tab {{ $tab === 'fleet' ? 'tab-active' : '' }}">
            Fleet
        </button>
        <button wire:click="switchTab('district')"
                role="tab"
                class="tab {{ $tab === 'district' ? 'tab-active' : '' }}">
            District
        </button>
    </div>

    @if($leaderboard->count() > 0)
        <div class="card bg-base-100 shadow overflow-x-auto">
            <table class="table">
                @if($tab === 'sailor')
                    <thead class="bg-base-300 border-b-2 border-base-content/20">
                        <tr>
                            <th class="text-center font-bold">Rank</th>
                            <th class="font-bold">Name</th>
                            <th class="text-center font-bold">District</th>
                            <th class="text-center font-bold">Fleet #</th>
                            <th class="font-bold">Yacht Club</th>
                            <th class="text-center font-bold">
                                <div class="tooltip tooltip-left" data-tip="All sailing days + up to 5 non-sailing days (maintenance & race committee)">
                                    <span class="cursor-help border-b border-dotted border-base-content/50">
                                        Days Sailed
                                    </span>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($leaderboard as $index => $user)
                            @php
                                $isCurrentUser = auth()->check() && auth()->id() === $user->id;
                            @endphp
                            <tr class="{{ $isCurrentUser ? 'current-user-row' : '' }}">
                                <td class="text-center font-bold">
                                    {{ ($leaderboard->currentPage() - 1) * $leaderboard->perPage() + $index + 1 }}
                                </td>
                                <td class="font-medium">
                                    {{ $user->name }}
                                    @if($isCurrentUser)
                                        <span class="badge badge-sm badge-primary ml-2">You</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @php
                                        $district = $user->district_id ? \App\Models\District::find($user->district_id) : null;
                                    @endphp
                                    {{ $district?->name ?? '—' }}
                                </td>
                                <td class="text-center">
                                    @php
                                        $fleet = $user->fleet_id ? \App\Models\Fleet::find($user->fleet_id) : null;
                                    @endphp
                                    {{ $fleet?->fleet_number ?? '—' }}
                                </td>
                                <td>{{ $user->yacht_club ?? '—' }}</td>
                                <td class="text-center">
                                    <span class="badge badge-accent badge-lg">
                                        {{ $user->flashes_count }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                @elseif($tab === 'fleet')
                    <thead class="bg-base-300 border-b-2 border-base-content/20">
                        <tr>
                            <th class="text-center font-bold">Rank</th>
                            <th class="font-bold">Fleet #</th>
                            <th class="text-center font-bold">Sailors</th>
                            <th class="text-center font-bold">
                                <div class="tooltip tooltip-left" data-tip="All sailing days + up to 5 non-sailing days (maintenance & race committee)">
                                    <span class="cursor-help border-b border-dotted border-base-content/50">
                                        Days Sailed
                                    </span>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($leaderboard as $index => $fleet)
                            @php
                                $userFleet = auth()->check() ? auth()->user()->currentMembership()?->fleet_id : null;
                                $isUserFleet = $userFleet && $userFleet === $fleet->id;
                            @endphp
                            <tr class="{{ $isUserFleet ? 'current-user-row' : '' }}">
                                <td class="text-center font-bold">
                                    {{ ($leaderboard->currentPage() - 1) * $leaderboard->perPage() + $index + 1 }}
                                </td>
                                <td class="font-medium">Fleet {{ $fleet->fleet_number }} - {{ $fleet->fleet_name }}</td>
                                <td class="text-center">{{ $fleet->member_count }}</td>
                                <td class="text-center">
                                    <span class="badge badge-accent badge-lg">
                                        {{ $fleet->total_flashes }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                @else
                    <thead class="bg-base-300 border-b-2 border-base-content/20">
                        <tr>
                            <th class="text-center font-bold">Rank</th>
                            <th class="font-bold">District</th>
                            <th class="text-center font-bold">Sailors</th>
                            <th class="text-center font-bold">
                                <div class="tooltip tooltip-left" data-tip="All sailing days + up to 5 non-sailing days (maintenance & race committee)">
                                    <span class="cursor-help border-b border-dotted border-base-content/50">
                                        Days Sailed
                                    </span>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($leaderboard as $index => $district)
                            @php
                                $userDistrict = auth()->check() ? auth()->user()->currentMembership()?->district_id : null;
                                $isUserDistrict = $userDistrict && $userDistrict === $district->id;
                            @endphp
                            <tr class="{{ $isUserDistrict ? 'current-user-row' : '' }}">
                                <td class="text-center font-bold">
                                    {{ ($leaderboard->currentPage() - 1) * $leaderboard->perPage() + $index + 1 }}
                                </td>
                                <td class="font-medium">{{ $district->name }}</td>
                                <td class="text-center">{{ $district->member_count }}</td>
                                <td class="text-center">
                                    <span class="badge badge-accent badge-lg">
                                        {{ $district->total_flashes }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                @endif
            </table>
        </div>

        <!-- Pagination -->
        @if($leaderboard->hasPages())
            <div class="mt-6">
                {{ $leaderboard->links('pagination::livewire-tailwind') }}
            </div>
        @endif
    @else
        <div class="hero py-12">
            <div class="hero-content text-center">
                <div>
                    <svg class="mx-auto h-12 w-12 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    <p class="mt-4 text-base-content/60">No flashes logged yet for {{ $currentYear }}. Be the first!</p>
                </div>
            </div>
        </div>
    @endif
</div>
