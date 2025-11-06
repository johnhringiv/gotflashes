<div>
    <!-- Earned Awards Card -->
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
                                [$badgeImage, $title] = match($award) {
                                    10 => ['got_10_transparent.png', '10 Day Award'],
                                    25 => ['got_25_transparent.png', '25 Day Award'],
                                    50 => ['got_50_transparent.png', '50 Day Award (Burgee)'],
                                    default => ['got_50_transparent.png', 'Award']
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
                            <img src="{{ asset('images/burgee_50_transparent.png') }}" alt="Burgee" class="h-9 w-auto object-contain inline-block">
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
</div>
