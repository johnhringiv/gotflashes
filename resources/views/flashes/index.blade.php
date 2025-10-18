<x-layout>
    <x-slot:title>
        My Activities
    </x-slot:title>

    <div class="max-w-6xl mx-auto">
        <div class="mb-6">
            <h1 class="text-3xl font-bold">My Activities</h1>
            <p class="text-base-content/70 mt-2">Your sailing days and progress toward awards</p>
        </div>

        <!-- Progress Card -->
        <div class="card bg-base-100 shadow mb-6">
            <div class="card-body">
                <div class="flex items-center justify-between mb-2">
                    <h2 class="card-title text-xl">{{ $currentYear }} Progress</h2>

                    <!-- Award Badges -->
                    @if(count($earnedAwards) > 0)
                        <div class="flex gap-2">
                            @foreach($earnedAwards as $award)
                                @php
                                    $color = match($award) {
                                        10 => '#CD7F32', // Bronze
                                        25 => '#C0C0C0', // Silver
                                        50 => '#FFD700', // Gold
                                        default => '#FFD700'
                                    };
                                    $title = match($award) {
                                        10 => '10 Day Award',
                                        25 => '25 Day Award',
                                        50 => '50 Day Award (Burgee)',
                                        default => 'Award'
                                    };
                                @endphp
                                <div class="tooltip" data-tip="{{ $title }}">
                                    <div class="badge badge-lg badge-primary gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="{{ $color }}" class="bi bi-trophy-fill" viewBox="0 0 16 16">
                                            <path d="M2.5.5A.5.5 0 0 1 3 0h10a.5.5 0 0 1 .5.5q0 .807-.034 1.536a3 3 0 1 1-1.133 5.89c-.79 1.865-1.878 2.777-2.833 3.011v2.173l1.425.356c.194.048.377.135.537.255L13.3 15.1a.5.5 0 0 1-.3.9H3a.5.5 0 0 1-.3-.9l1.838-1.379c.16-.12.343-.207.537-.255L6.5 13.11v-2.173c-.955-.234-2.043-1.146-2.833-3.012a3 3 0 1 1-1.132-5.89A33 33 0 0 1 2.5.5m.099 2.54a2 2 0 0 0 .72 3.935c-.333-1.05-.588-2.346-.72-3.935m10.083 3.935a2 2 0 0 0 .72-3.935c-.133 1.59-.388 2.885-.72 3.935"/>
                                        </svg>
                                        <span class="font-bold">{{ $award }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-4 mb-4">
                    <div class="stat p-0 flex-1">
                        <div class="stat-title text-xs">Total Days</div>
                        <div class="stat-value text-3xl text-primary">{{ $totalFlashes }}</div>
                        <div class="stat-desc text-xs">{{ $sailingCount }} sailing + {{ $nonSailingCount }} non-sailing</div>
                    </div>

                    @if($nextMilestone)
                        <div class="stat p-0 flex-1">
                            <div class="stat-title text-xs">Next Award</div>
                            <div class="stat-value text-3xl">{{ $nextMilestone }}</div>
                            <div class="stat-desc text-xs">{{ $nextMilestone - $totalFlashes }} days to go</div>
                        </div>
                    @else
                        <div class="stat p-0 flex-1">
                            <div class="stat-title text-xs">Achievement</div>
                            <div class="stat-value text-3xl">🏆</div>
                            <div class="stat-desc text-xs">All tiers completed!</div>
                        </div>
                    @endif
                </div>

                <!-- Progress Bar -->
                @if($nextMilestone)
                    @php
                        $previousMilestone = 0;
                        if ($nextMilestone == 25) $previousMilestone = 10;
                        if ($nextMilestone == 50) $previousMilestone = 25;
                        $progress = (($totalFlashes - $previousMilestone) / ($nextMilestone - $previousMilestone)) * 100;
                        $progress = max(0, min(100, $progress));
                    @endphp
                    <div>
                        <div class="flex justify-between text-xs text-base-content/70 mb-1">
                            <span>{{ $previousMilestone }} days</span>
                            <span>{{ $nextMilestone }} days</span>
                        </div>
                        <progress class="progress progress-primary w-full h-3" value="{{ $progress }}" max="100"></progress>
                    </div>
                @else
                    <progress class="progress progress-success w-full h-3" value="100" max="100"></progress>
                @endif
            </div>
        </div>

        <!-- Flash Entry Form -->
        <div class="card bg-base-100 shadow mb-6">
            <div class="card-body">
                <h2 class="card-title text-xl mb-4">Log a New Activity</h2>
                <x-flash-form
                    :action="route('flashes.store')"
                    submit-text="Log Activity"
                />
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
                            <p class="mt-4 text-base-content/60">No activities yet. Log your first flash to get started!</p>
                        </div>
                    </div>
                </div>
            @endforelse
        </div>

        <!-- Pagination -->
        @if($flashes->hasPages())
            <div class="mt-6">
                {{ $flashes->links() }}
            </div>
        @endif
    </div>
</x-layout>
