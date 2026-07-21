@php
    $counters = $stats['counters'];
    $hasActivity = $counters['activeSailors'] > 0;
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
            <x-lightning-fill
                :percentage="$goal ? $goalPercent : null"
                :prior-percentage="$priorPercent"
                :goal-label="$goal ? number_format($goal) : null"
                :prior-year="$priorPercent !== null ? $selectedYear - 1 : null"
                class="w-48 sm:w-60" />
            @if ($goal)
                <p class="text-2xl font-bold mt-2">
                    {{ number_format($counters['totalQualifying']) }}
                    <span class="font-normal text-base-content/70">community qualifying days in {{ $selectedYear }}</span>
                </p>
                <p class="text-lg {{ $goalPercent >= 100 ? 'text-success font-bold' : 'text-base-content/70' }}">
                    @if ($goalPercent >= 100)
                        Goal achieved! ⚡
                    @else
                        {{ $goalPercent }}% of the {{ number_format($goal) }}-day {{ $selectedYear }} goal
                    @endif
                </p>
                @if ($priorTotal > 0)
                    <p class="text-sm text-base-content/60">
                        {{ $selectedYear - 1 }} finished at {{ number_format($priorTotal) }} days
                    </p>
                @endif
            @else
                <p class="text-2xl font-bold mt-2">
                    {{ number_format($counters['totalQualifying']) }}
                    <span class="font-normal text-base-content/70">community qualifying days in {{ $selectedYear }}</span>
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
    @php
        $counterTiles = [
            ['title' => 'Qualifying Days', 'value' => $counters['totalQualifying'], 'desc' => 'Sailing + capped non-sailing'],
            ['title' => 'Active Sailors', 'value' => $counters['activeSailors'], 'desc' => 'Logged at least one day'],
            ['title' => 'Active Fleets', 'value' => $counters['activeFleets'], 'desc' => 'Represented on the water'],
            ['title' => 'Active Districts', 'value' => $counters['activeDistricts'], 'desc' => 'Represented on the water'],
            ['title' => 'Award Achievers', 'value' => $counters['awardAchievers'], 'desc' => 'Reached 10+ days', 'span' => 'col-span-2 md:col-span-1'],
        ];
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
        @foreach ($counterTiles as $i => $tile)
            <div class="card bg-base-100 shadow-md {{ $tile['span'] ?? '' }}">
                <div class="stat">
                    <div class="stat-title">{{ $tile['title'] }}</div>
                    {{-- Alternating brand blues: primary (dark) / secondary (the homepage "25" blue) --}}
                    <div class="stat-value text-3xl {{ $i % 2 === 0 ? 'text-primary' : 'text-secondary' }}">{{ number_format($tile['value']) }}</div>
                    <div class="stat-desc">{{ $tile['desc'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($hasActivity)
        <div class="space-y-6" wire:loading.class="opacity-50 transition-opacity">
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

            {{-- items-start so expanding one card's data table doesn't push its
                 grid neighbor down (default stretch equalizes row height). --}}
            <div class="grid md:grid-cols-2 gap-6 items-start stats-duo">
                {{-- G. Age distribution --}}
                <x-chart-card chart-id="chart-ages"
                              title="Sailor ages"
                              subtitle="Active sailors in {{ $selectedYear }}, by Lightning Class age division and gender">
                    <x-slot:table>
                        <table class="table table-xs">
                            <thead>
                                <tr>
                                    <th>Division</th>
                                    <th>Ages</th>
                                    @foreach ($stats['ages']['genders'] as $g)
                                        <th class="text-right">{{ $g['label'] }}</th>
                                    @endforeach
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($stats['ages']['labels'] as $i => $label)
                                    @php $row = $stats['ages']['counts'][$label] ?? []; @endphp
                                    <tr>
                                        <td>{{ $label }}</td>
                                        <td class="opacity-70">{{ $stats['ages']['ranges'][$i] }}</td>
                                        @foreach ($stats['ages']['genders'] as $g)
                                            <td class="text-right">{{ $row[$g['key']] ?? 0 }}</td>
                                        @endforeach
                                        <td class="text-right">{{ array_sum($row) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-slot:table>
                </x-chart-card>

                {{-- H. Award tier funnel --}}
                <x-chart-card chart-id="chart-funnel"
                              title="From sign-up to award"
                              subtitle="How far {{ $selectedYear }} sailors climb — registered, active, then each award">
                    <x-slot:table>
                        <table class="table table-xs">
                            <thead><tr><th>Stage</th><th class="text-right">Sailors</th><th class="text-right">% of registered</th></tr></thead>
                            <tbody>
                                @php $registered = $stats['funnel'][0]['count'] ?? 0; @endphp
                                @foreach ($stats['funnel'] as $stage)
                                    <tr>
                                        <td>{{ $stage['label'] }}</td>
                                        <td class="text-right">{{ $stage['count'] }}</td>
                                        <td class="text-right opacity-70">{{ $registered ? round($stage['count'] / $registered * 100) : 0 }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-slot:table>
                </x-chart-card>
            </div>

            {{-- F2. Community growth — cumulative sailors, stackable --}}
            <x-chart-card chart-id="chart-cumulative"
                          title="Community growth"
                          subtitle="Running total of registered sailors through {{ $selectedYear }} — stack by gender or age, count or share">
                <x-slot:table>
                    <table class="table table-xs">
                        <thead><tr><th>Date</th><th class="text-right">Total sailors</th></tr></thead>
                        <tbody>
                            @foreach ($stats['sailorGrowth']['totals'] as $point)
                                <tr><td>{{ $point['date'] }}</td><td class="text-right">{{ $point['total'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-slot:table>
            </x-chart-card>

            {{-- F3. Flashes over the season — interactive running total --}}
            <x-chart-card chart-id="chart-flash-filter"
                          title="Flashes over the season"
                          subtitle="Running total of every flash in {{ $selectedYear }} — toggle activity types, genders, age groups, or count vs share" />

            {{-- J. Fun facts --}}
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
