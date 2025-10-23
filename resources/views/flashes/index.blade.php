<x-layout>
    <x-slot:title>
        My Activities
    </x-slot:title>

    <div class="max-w-6xl mx-auto">
        <div class="mb-6">
            <h1 class="text-3xl font-bold">My Activities</h1>
            <p class="text-base-content/70 mt-2">Your sailing days and progress toward awards</p>
        </div>

        <!-- Earned Awards Card (Option 2) -->
        @if(count($earnedAwards) > 0)
            <div class="card bg-gradient-to-br from-primary/10 to-secondary/10 shadow-lg mb-6 border-2 border-primary/20">
                <div class="card-body py-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="card-title text-xl mb-1">{{ $currentYear }} Earned Awards</h2>
                            <p class="text-sm text-base-content/70">Congratulations on your achievements!</p>
                        </div>
                        <div class="flex gap-6">
                            @foreach($earnedAwards as $award)
                                @php
                                    $badgeImage = match($award) {
                                        10 => 'got-10-badge.png',
                                        25 => 'got-25-badge.png',
                                        50 => 'got-50-badge.png',
                                        default => 'got-50-badge.png'
                                    };
                                    $title = match($award) {
                                        10 => '10 Day Award',
                                        25 => '25 Day Award',
                                        50 => '50 Day Award (Burgee)',
                                        default => 'Award'
                                    };
                                @endphp
                                <div class="flex flex-col items-center gap-2">
                                    <img src="{{ asset('images/' . $badgeImage) }}" alt="{{ $title }}" class="w-20 h-20 object-contain drop-shadow-lg">
                                    <span class="text-xs font-semibold text-center">{{ $title }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Progress Card -->
        <div class="card bg-base-100 shadow mb-6">
            <div class="card-body">
                <h2 class="card-title text-xl mb-4">{{ $currentYear }} Progress</h2>

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
                            <div class="stat-value text-3xl">
                                <img src="{{ asset('images/burgee-50.jpg') }}" alt="Burgee" class="h-9 w-auto object-contain inline-block">
                            </div>
                            <div class="stat-desc text-xs">All tiers completed!</div>
                        </div>
                    @endif
                </div>

                <!-- Progress Bar -->
                @php
                    // Calculate progress percentage (0 to 50+ scale)
                    $maxScale = 50;
                    $progress = min(100, ($totalFlashes / $maxScale) * 100);

                    // Award marker positions (at 10, 25, 50 days)
                    $marker10 = (10 / $maxScale) * 100;
                    $marker25 = (25 / $maxScale) * 100;
                    $marker50 = (50 / $maxScale) * 100;
                @endphp
                <div>
                    <div class="relative mb-2">
                        <progress class="progress progress-primary w-full h-3" value="{{ $progress }}" max="100"></progress>

                        <!-- Award markers -->
                        <div class="absolute top-0 left-0 w-full h-full pointer-events-none">
                            <!-- 10 Day Marker -->
                            <div class="absolute flex flex-col items-center" style="left: {{ $marker10 }}%; transform: translateX(-50%);">
                                <div class="w-8 h-8 rounded-full border-2 {{ in_array(10, $earnedAwards) ? 'border-primary bg-primary' : 'border-base-300 bg-base-100' }} -mt-2.5"></div>
                                <span class="text-xs mt-1 text-base-content/60">10</span>
                            </div>

                            <!-- 25 Day Marker -->
                            <div class="absolute flex flex-col items-center" style="left: {{ $marker25 }}%; transform: translateX(-50%);">
                                <div class="w-8 h-8 rounded-full border-2 {{ in_array(25, $earnedAwards) ? 'border-primary bg-primary' : 'border-base-300 bg-base-100' }} -mt-2.5"></div>
                                <span class="text-xs mt-1 text-base-content/60">25</span>
                            </div>

                            <!-- 50 Day Marker -->
                            <div class="absolute flex flex-col items-center" style="left: {{ $marker50 }}%; transform: translateX(-50%);">
                                <div class="w-8 h-8 rounded-full border-2 {{ in_array(50, $earnedAwards) ? 'border-primary bg-primary' : 'border-base-300 bg-base-100' }} -mt-2.5"></div>
                                <span class="text-xs mt-1 text-base-content/60">50+</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Flash Entry Form -->
        <div class="card bg-base-100 shadow mb-6">
            <div class="card-body">
                <h2 class="card-title text-xl mb-4">Log a New Activity</h2>
                <x-flash-form
                    :action="route('flashes.store')"
                    submit-text="Log Activity"
                    :existing-dates="$existingDates"
                    :min-date="$minDate"
                    :max-date="$maxDate"
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
