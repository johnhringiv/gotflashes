@props(['flash'])

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
                    <span class="badge badge-primary badge-sm">Sailing - {{ ucfirst(str_replace('_', ' ', $flash->event_type)) }}</span>
                @else
                    <span class="badge badge-primary badge-sm">{{ ucfirst(str_replace('_', ' ', $flash->activity_type)) }}</span>
                @endif
                @if($flash->created_at->isToday())
                    <span class="badge badge-success badge-sm">Just logged</span>
                @endif
            </div>

            @can('update', $flash)
                <div class="flex gap-1">
                    <a href="/flashes/{{ $flash->id }}/edit" class="btn btn-ghost btn-xs">
                        Edit
                    </a>
                    <form method="POST" action="/flashes/{{ $flash->id }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            onclick="return confirm('Are you sure you want to delete this flash?')"
                            class="btn btn-ghost btn-xs text-error">
                            Delete
                        </button>
                    </form>
                </div>
            @endcan
        </div>

        <div class="mt-2 text-sm text-base-content/80 space-y-1">
            @if($flash->location)
                <div>📍 {{ $flash->location }}</div>
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
