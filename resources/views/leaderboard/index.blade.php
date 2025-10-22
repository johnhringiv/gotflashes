<x-layout>
    <x-slot:title>
        Leaderboard
    </x-slot:title>
    <x-slot:description>
        View the Lightning Class GOT-FLASHES leaderboard rankings. See top sailors, fleets, and districts by sailing days tracked in 2025.
    </x-slot:description>

    <div class="max-w-6xl mx-auto">
        <div class="mb-6">
            <h1 class="text-3xl font-bold">
                <span class="text-primary">2025</span>
                <span class="text-accent">Leaderboard</span>
            </h1>
            <p class="text-base-content/70 mt-2">Top Lightning sailors by total flashes this year</p>
        </div>

        <!-- Tabs -->
        <div role="tablist" class="tabs tabs-boxed mb-6 w-fit">
            <a href="{{ route('leaderboard', ['tab' => 'sailor']) }}"
               role="tab"
               class="tab {{ $currentTab === 'sailor' ? 'tab-active' : '' }}">
                Sailor
            </a>
            <a href="{{ route('leaderboard', ['tab' => 'fleet']) }}"
               role="tab"
               class="tab {{ $currentTab === 'fleet' ? 'tab-active' : '' }}">
                Fleet
            </a>
            <a href="{{ route('leaderboard', ['tab' => 'district']) }}"
               role="tab"
               class="tab {{ $currentTab === 'district' ? 'tab-active' : '' }}">
                District
            </a>
        </div>

        @if($leaderboard->count() > 0)
            <div class="card bg-base-100 shadow overflow-x-auto">
                <table class="table">
                    <style>
                        .table tbody tr:nth-child(even):not(.current-user-row) {
                            background-color: oklch(38% 0.09 245 / 0.1);
                        }
                        .current-user-row {
                            background-color: oklch(44% 0.21 29 / 0.15);
                        }
                        .badge-accent {
                            background-color: oklch(44% 0.21 29);
                            color: oklch(100% 0 0);
                            border-color: oklch(44% 0.21 29);
                        }
                    </style>
                    @if($currentTab === 'sailor')
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
                                            Total Flashes
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
                                <tr class="{{ $isCurrentUser ? 'current-user-row border-l-4 border-accent' : '' }}">
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
                    @elseif($currentTab === 'fleet')
                        <thead class="bg-base-300 border-b-2 border-base-content/20">
                            <tr>
                                <th class="text-center font-bold">Rank</th>
                                <th class="font-bold">Fleet #</th>
                                <th class="text-center font-bold">Members</th>
                                <th class="text-center font-bold">
                                    <div class="tooltip tooltip-left" data-tip="All sailing days + up to 5 non-sailing days (maintenance & race committee)">
                                        <span class="cursor-help border-b border-dotted border-base-content/50">
                                            Total Flashes
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
                                <tr class="{{ $isUserFleet ? 'current-user-row border-l-4 border-accent' : '' }}">
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
                                <th class="text-center font-bold">Members</th>
                                <th class="text-center font-bold">
                                    <div class="tooltip tooltip-left" data-tip="All sailing days + up to 5 non-sailing days (maintenance & race committee)">
                                        <span class="cursor-help border-b border-dotted border-base-content/50">
                                            Total Flashes
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
                                <tr class="{{ $isUserDistrict ? 'current-user-row border-l-4 border-accent' : '' }}">
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
                    {{ $leaderboard->appends(['tab' => $currentTab])->links() }}
                </div>
            @endif
        @else
            <div class="hero py-12">
                <div class="hero-content text-center">
                    <div>
                        <svg class="mx-auto h-12 w-12 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        <p class="mt-4 text-base-content/60">No flashes logged yet for 2025. Be the first!</p>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-layout>
