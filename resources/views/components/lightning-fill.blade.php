@props([
    'percentage' => null,       // current / goal, 0–100 (null = no goal set)
    'priorPercentage' => null,  // prior-year total / goal, 0–100 (null/0 = hide line)
    'goalLabel' => null,        // formatted goal, e.g. "3,000"
    'priorYear' => null,        // e.g. 2025, for the benchmark chip
])

@php
    $fill = $percentage === null ? 0 : max(0, min(100, $percentage));
    $complete = $percentage !== null && $percentage >= 100;
    $showPrior = $priorPercentage !== null && $priorPercentage > 0;
    $priorFrac = $showPrior ? max(0, min(100, $priorPercentage)) / 100 : 0;
@endphp

<div {{ $attributes->merge(['class' => 'lightning-fill'.($complete ? ' lightning-fill-complete' : '')]) }}
     style="--lf-fill: {{ $fill }}%; --lf-prior: {{ $priorFrac }};"
     role="img"
     aria-label="Community goal progress: {{ $percentage === null ? 'no goal set' : round($fill).'% of goal' }}{{ $showPrior && $priorYear ? ', '.$priorYear.' benchmark shown' : '' }}">
    @if ($goalLabel !== null)
        <div class="lightning-fill-goal">{{ $goalLabel }} <span>goal</span></div>
    @endif

    <div class="lightning-fill-circle">
        {{-- The bolt silhouette (Lightning Class insignia) masks these layers --}}
        <div class="lightning-fill-bolt">
            <div class="lightning-fill-track"></div>
            @if ($percentage !== null)
                <div class="lightning-fill-rise"></div>
            @endif
        </div>

        {{-- Prior-year benchmark: solid across the bolt (masked, never overhangs),
             a dashed leader out to the year chip on the right. --}}
        @if ($showPrior)
            <div class="lightning-fill-prior-solid" aria-hidden="true"></div>
            <div class="lightning-fill-prior-leader" aria-hidden="true"></div>
            @if ($priorYear)
                <div class="lightning-fill-prior-chip" aria-hidden="true">{{ $priorYear }}</div>
            @endif
        @endif
    </div>
</div>
