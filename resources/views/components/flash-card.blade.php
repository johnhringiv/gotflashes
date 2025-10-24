@props(['flash', 'minDate', 'maxDate'])

<div class="card bg-base-100 shadow">
    <div class="card-body">
        <div class="flex justify-between w-full">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-sm font-semibold">{{ $flash->date->format('M j, Y') }}</span>
                <span class="text-base-content/60">·</span>
                <span class="text-sm text-base-content/60">{{ $flash->created_at->diffForHumans() }}</span>
                @if ($flash->updated_at->gt($flash->created_at->addSeconds(120)))
                    <span class="text-base-content/60">·</span>
                    <span class="text-sm text-base-content/60">(edited {{ $flash->updated_at->diffForHumans() }})</span>
                @endif
                @if($flash->activity_type === 'sailing' && $flash->event_type)
                    @php
                        $eventTypeLabel = match($flash->event_type) {
                            'leisure' => 'Day Sailing',
                            'club_race' => 'Club Race',
                            default => ucfirst(str_replace('_', ' ', $flash->event_type))
                        };
                    @endphp
                    <span class="badge badge-primary badge-sm">Sailing - {{ $eventTypeLabel }}</span>
                @else
                    <span class="badge badge-primary badge-sm">{{ ucfirst(str_replace('_', ' ', $flash->activity_type)) }}</span>
                @endif
                @if($flash->created_at->isToday())
                    <span class="badge badge-success badge-sm">Just logged</span>
                @endif
            </div>

            @can('update', $flash)
                @if($flash->isEditable($minDate, $maxDate))
                    <div class="flex gap-1">
                        <button type="button"
                            wire:click="openEditModal({{ $flash->id }})"
                            class="btn btn-ghost btn-xs">
                            Edit
                        </button>
                        <button type="button"
                            wire:click="delete({{ $flash->id }})"
                            wire:confirm="Are you sure you want to delete this flash?"
                            class="btn btn-ghost btn-xs text-error">
                            Delete
                        </button>
                    </div>
                @endif
            @endcan
        </div>

        <div class="mt-2 text-sm text-base-content/80 space-y-1">
            @if($flash->location)
                <div class="flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                    </svg>
                    {{ $flash->location }}
                </div>
            @endif

            @if($flash->sail_number)
                <div>⛵ Sail #{{ $flash->sail_number }}</div>
            @endif

            @if($flash->notes)
                <p class="mt-2 break-words whitespace-pre-wrap">{{ $flash->notes }}</p>
            @endif
        </div>
    </div>
</div>
