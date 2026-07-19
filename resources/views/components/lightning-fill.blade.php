@props(['percentage' => null])

@php
    // Bolt silhouette inside a 200x200 viewBox; also used as the fill clip.
    $boltPath = 'M114 18 L60 106 L92 106 L76 182 L142 88 L106 88 Z';
    $fill = $percentage === null ? 0 : max(0, min(100, $percentage));
    $complete = $percentage !== null && $percentage >= 100;
@endphp

<svg viewBox="0 0 200 200"
     {{ $attributes->merge(['class' => 'lightning-fill'.($complete ? ' lightning-fill-complete' : '')]) }}
     role="img"
     aria-label="Community goal progress: {{ $percentage === null ? 'no goal set' : $fill.'%' }}">
    <defs>
        <linearGradient id="lightning-fill-gradient" x1="0" y1="1" x2="0" y2="0">
            <stop offset="0%" stop-color="var(--color-primary)" />
            <stop offset="100%" stop-color="var(--color-secondary)" />
        </linearGradient>
        <clipPath id="lightning-fill-bolt">
            <path d="{{ $boltPath }}" />
        </clipPath>
    </defs>

    {{-- Circle backdrop --}}
    <circle cx="100" cy="100" r="94" fill="oklch(100% 0 0deg)" stroke="var(--color-primary)" stroke-width="5" />

    {{-- Empty-state bolt --}}
    <path d="{{ $boltPath }}" fill="var(--color-base-300)" />

    {{-- Rising gradient fill, clipped to the bolt --}}
    @if ($percentage !== null)
        <g clip-path="url(#lightning-fill-bolt)">
            <rect x="0" y="0" width="200" height="200"
                  fill="url(#lightning-fill-gradient)"
                  class="lightning-fill-rect"
                  style="--fill-pct: {{ $fill }}%" />
        </g>
    @endif
</svg>
