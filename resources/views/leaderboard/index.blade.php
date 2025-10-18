<x-layout>
    <x-slot:title>
        Leaderboard
    </x-slot:title>

    <div class="max-w-6xl mx-auto">
        <div class="mb-6">
            <h1 class="text-3xl font-bold">2025 Leaderboard</h1>
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
                <table class="table table-zebra">
                    @if($currentTab === 'sailor')
                        <thead>
                            <tr>
                                <th class="text-center">Rank</th>
                                <th>Name</th>
                                <th class="text-center">District</th>
                                <th class="text-center">Fleet #</th>
                                <th>Yacht Club</th>
                                <th class="text-center">
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
                                <tr class="{{ $isCurrentUser ? 'bg-primary/10 border-l-4 border-primary' : '' }}">
                                    <td class="text-center font-bold">
                                        {{ ($leaderboard->currentPage() - 1) * $leaderboard->perPage() + $index + 1 }}
                                    </td>
                                    <td class="font-medium">
                                        {{ $user->name }}
                                        @if($isCurrentUser)
                                            <span class="badge badge-sm badge-primary ml-2">You</span>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $user->district ?? '—' }}</td>
                                    <td class="text-center">{{ $user->fleet_number ?? '—' }}</td>
                                    <td>{{ $user->yacht_club ?? '—' }}</td>
                                    <td class="text-center">
                                        <span class="badge badge-primary badge-lg">
                                            {{ $user->flashes_count }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    @elseif($currentTab === 'fleet')
                        <thead>
                            <tr>
                                <th class="text-center">Rank</th>
                                <th>Fleet #</th>
                                <th class="text-center">Members</th>
                                <th class="text-center">
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
                                <tr>
                                    <td class="text-center font-bold">
                                        {{ ($leaderboard->currentPage() - 1) * $leaderboard->perPage() + $index + 1 }}
                                    </td>
                                    <td class="font-medium">Fleet {{ $fleet->fleet_number }}</td>
                                    <td class="text-center">{{ $fleet->member_count }}</td>
                                    <td class="text-center">
                                        <span class="badge badge-primary badge-lg">
                                            {{ $fleet->total_flashes }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    @else
                        <thead>
                            <tr>
                                <th class="text-center">Rank</th>
                                <th>District</th>
                                <th class="text-center">Members</th>
                                <th class="text-center">
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
                                <tr>
                                    <td class="text-center font-bold">
                                        {{ ($leaderboard->currentPage() - 1) * $leaderboard->perPage() + $index + 1 }}
                                    </td>
                                    <td class="font-medium">District {{ $district->district }}</td>
                                    <td class="text-center">{{ $district->member_count }}</td>
                                    <td class="text-center">
                                        <span class="badge badge-primary badge-lg">
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
                    {{ $leaderboard->links() }}
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
