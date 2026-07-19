@php
    $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $counters = $stats['counters'];
    $hasActivity = $counters['activeSailors'] > 0;
    $eventTypeLabels = ['regatta' => 'Regatta', 'club_race' => 'Club Race', 'practice' => 'Practice', 'leisure' => 'Day Sailing'];
@endphp

<div>
    {{-- Header: title + year selector --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-primary">Community Stats</h1>
            <p class="text-base-content/70 mt-1">The Lightning community's season, at a glance</p>
        </div>
        <label class="flex items-center gap-2" for="stats-year">
            <span class="text-sm font-medium">Year</span>
            <select id="stats-year" wire:model.live="selectedYear" class="select select-bordered select-sm w-28">
                @foreach ($availableYears as $year)
                    <option value="{{ $year }}">{{ $year }}</option>
                @endforeach
            </select>
        </label>
    </div>

    {{-- A. Lightning fill-up hero --}}
    <div class="card bg-base-100 shadow-md mb-6" wire:key="hero-{{ $selectedYear }}">
        <div class="card-body items-center text-center">
            <x-lightning-fill :percentage="$goal ? $goalPercent : null" class="w-48 h-48 sm:w-60 sm:h-60" />
            @if ($goal)
                <p class="text-2xl font-bold mt-2">
                    {{ number_format($counters['totalQualifying']) }}
                    <span class="font-normal text-base-content/70">of</span>
                    {{ number_format($goal) }}
                    <span class="font-normal text-base-content/70">community sailing days</span>
                </p>
                <p class="text-lg {{ $goalPercent >= 100 ? 'text-success font-bold' : 'text-base-content/70' }}">
                    @if ($goalPercent >= 100)
                        Goal achieved! ⚡
                    @else
                        {{ $goalPercent }}% of the {{ $selectedYear }} goal
                    @endif
                </p>
            @else
                <p class="text-2xl font-bold mt-2">
                    {{ number_format($counters['totalQualifying']) }}
                    <span class="font-normal text-base-content/70">community sailing days in {{ $selectedYear }}</span>
                </p>
                @if (auth()->check() && auth()->user()->is_admin)
                    <p class="text-sm text-base-content/60">
                        No community goal set for {{ $selectedYear }} —
                        <a href="/admin/settings" class="link link-primary">set one in Settings</a>
                    </p>
                @endif
            @endif
        </div>
    </div>

    {{-- B. Key counters --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
        <div class="card bg-base-100 shadow-md">
            <div class="stat">
                <div class="stat-title">Qualifying Days</div>
                <div class="stat-value text-primary text-3xl">{{ number_format($counters['totalQualifying']) }}</div>
                <div class="stat-desc">Sailing + capped non-sailing</div>
            </div>
        </div>
        <div class="card bg-base-100 shadow-md">
            <div class="stat">
                <div class="stat-title">Active Sailors</div>
                <div class="stat-value text-primary text-3xl">{{ number_format($counters['activeSailors']) }}</div>
                <div class="stat-desc">Logged at least one day</div>
            </div>
        </div>
        <div class="card bg-base-100 shadow-md">
            <div class="stat">
                <div class="stat-title">Active Fleets</div>
                <div class="stat-value text-primary text-3xl">{{ number_format($counters['activeFleets']) }}</div>
                <div class="stat-desc">Represented on the water</div>
            </div>
        </div>
        <div class="card bg-base-100 shadow-md">
            <div class="stat">
                <div class="stat-title">Active Districts</div>
                <div class="stat-value text-primary text-3xl">{{ number_format($counters['activeDistricts']) }}</div>
                <div class="stat-desc">Represented on the water</div>
            </div>
        </div>
        <div class="card bg-base-100 shadow-md col-span-2 md:col-span-1">
            <div class="stat">
                <div class="stat-title">Award Achievers</div>
                <div class="stat-value text-primary text-3xl">{{ number_format($counters['awardAchievers']) }}</div>
                <div class="stat-desc">Reached 10+ days</div>
            </div>
        </div>
    </div>

    @if ($hasActivity)
        <div class="space-y-6" wire:loading.class="opacity-50 transition-opacity">
            {{-- C. Flashes by month, year over year --}}
            <x-chart-card chart-id="chart-monthly"
                          title="Flashes by month"
                          subtitle="All logged days, {{ $selectedYear }} vs {{ $selectedYear - 1 }}">
                <x-slot:table>
                    <table class="table table-xs">
                        <thead>
                            <tr><th>Month</th><th class="text-right">{{ $selectedYear }}</th><th class="text-right">{{ $selectedYear - 1 }}</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($monthNames as $i => $name)
                                <tr>
                                    <td>{{ $name }}</td>
                                    <td class="text-right">{{ $stats['monthly']['current'][$i] }}</td>
                                    <td class="text-right">{{ $stats['monthly']['previous'][$i] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-slot:table>
            </x-chart-card>

            {{-- D. Activity heatmap --}}
            <x-chart-card chart-id="chart-heatmap"
                          title="When the community sails"
                          subtitle="Every day of {{ $selectedYear }} — darker means more flashes">
                <x-slot:table>
                    <table class="table table-xs">
                        <thead><tr><th>Date</th><th class="text-right">Flashes</th></tr></thead>
                        <tbody>
                            @foreach ($stats['heatmap'] as $day => $count)
                                <tr><td>{{ $day }}</td><td class="text-right">{{ $count }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-slot:table>
            </x-chart-card>

            {{-- E. Event type mix --}}
            <x-chart-card chart-id="chart-event-mix"
                          title="Sailing mix through the season"
                          subtitle="Share of sailing days by event type">
                <x-slot:table>
                    <table class="table table-xs">
                        <thead>
                            <tr>
                                <th>Month</th>
                                @foreach ($eventTypeLabels as $label)
                                    <th class="text-right">{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($stats['eventMix'] as $month => $types)
                                @if (array_sum($types) > 0)
                                    <tr>
                                        <td>{{ $monthNames[$month - 1] }}</td>
                                        @foreach (array_keys($eventTypeLabels) as $type)
                                            <td class="text-right">{{ $types[$type] }}</td>
                                        @endforeach
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </x-slot:table>
            </x-chart-card>

            <div class="grid md:grid-cols-2 gap-6">
                {{-- F. New accounts by month --}}
                <x-chart-card chart-id="chart-signups"
                              title="New sailors by month"
                              subtitle="Accounts created in {{ $selectedYear }}">
                    <x-slot:table>
                        <table class="table table-xs">
                            <thead><tr><th>Month</th><th class="text-right">Signups</th></tr></thead>
                            <tbody>
                                @foreach ($monthNames as $i => $name)
                                    <tr><td>{{ $name }}</td><td class="text-right">{{ $stats['signups'][$i] }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-slot:table>
                </x-chart-card>

                {{-- G. Age distribution --}}
                <x-chart-card chart-id="chart-ages"
                              title="Sailor ages"
                              subtitle="Active sailors in {{ $selectedYear }}, by age group">
                    <x-slot:table>
                        <table class="table table-xs">
                            <thead><tr><th>Age group</th><th class="text-right">Sailors</th></tr></thead>
                            <tbody>
                                @foreach ($stats['ages']['labels'] as $i => $label)
                                    <tr><td>{{ $label }}</td><td class="text-right">{{ $stats['ages']['counts'][$i] }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-slot:table>
                </x-chart-card>
            </div>

            {{-- H. Award tier funnel --}}
            <x-chart-card chart-id="chart-funnel"
                          title="Progress toward awards"
                          subtitle="Where active sailors sit on the award ladder">
                <x-slot:table>
                    <table class="table table-xs">
                        <thead><tr><th>Band</th><th class="text-right">Sailors</th></tr></thead>
                        <tbody>
                            @foreach ($stats['funnel'] as $band)
                                <tr><td>{{ $band['label'] }}</td><td class="text-right">{{ $band['count'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-slot:table>
            </x-chart-card>

            {{-- I. Fun facts --}}
            @if (count($stats['funFacts']) > 0)
                <div>
                    <h2 class="text-xl font-bold mb-4">Fun facts</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach ($stats['funFacts'] as $fact)
                            <div class="card bg-base-100 shadow-md">
                                <div class="card-body py-4">
                                    <div class="text-sm text-base-content/60">{{ $fact['title'] }}</div>
                                    <div class="text-xl font-bold text-primary">{{ $fact['value'] }}</div>
                                    <div class="text-sm text-base-content/70">{{ $fact['detail'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @else
        {{-- Empty state: no activity for this year --}}
        <div class="card bg-base-100 shadow-md">
            <div class="card-body items-center text-center py-16">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <h2 class="text-xl font-bold mt-4">No activity logged for {{ $selectedYear }} yet</h2>
                <p class="text-base-content/70">Check back once sailors start logging their days on the water.</p>
            </div>
        </div>
    @endif

    {{-- Chart data for stats-charts.js (initial load; year changes arrive via the
         community-stats-updated browser event) --}}
    <script type="application/json" id="community-stats-data">{!! $chartJson !!}</script>
</div>
