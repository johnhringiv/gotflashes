@props(['chartId', 'title', 'subtitle' => null])

<div {{ $attributes->merge(['class' => 'card bg-base-100 shadow-md']) }}>
    <div class="card-body">
        <h2 class="card-title text-lg">{{ $title }}</h2>
        @if ($subtitle)
            <p class="text-sm opacity-70 -mt-1">{{ $subtitle }}</p>
        @endif

        {{-- D3 owns this subtree; Livewire must never morph it --}}
        <div wire:ignore>
            <div id="{{ $chartId }}" class="stats-chart" data-stats-chart></div>
        </div>

        {{-- Accessible, hover-free twin of the chart (updates with the year) --}}
        @isset($table)
            <details class="mt-2 text-sm">
                <summary class="cursor-pointer opacity-70 hover:opacity-100 select-none">View data table</summary>
                <div class="overflow-x-auto mt-2 max-h-72 overflow-y-auto">
                    {{ $table }}
                </div>
            </details>
        @endisset
    </div>
</div>
