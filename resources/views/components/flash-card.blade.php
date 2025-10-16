@props(['flash'])

<div class="card bg-base-100 shadow">
    <div class="card-body">
        <div class="flex space-x-3">
            @if($flash->user)
                <div class="avatar">
                    <div class="size-10 rounded-full">
                        <img src="https://avatars.laravel.cloud/{{ urlencode($flash->user->email) }}"
                             alt="{{ $flash->user->name }}'s avatar"
                             class="rounded-full" />
                    </div>
                </div>
            @else
                <div class="avatar placeholder">
                    <div class="size-10 rounded-full">
                        <img src="https://avatars.laravel.cloud/f61123d5-0b27-434c-a4ae-c653c7fc9ed6?vibe=stealth"
                             alt="Anonymous User"
                             class="rounded-full" />
                    </div>
                </div>
            @endif

            <div class="min-w-0 flex-1">
                <div class="flex justify-between w-full">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-semibold">{{ $flash->user->name }}</span>
                        <span class="text-base-content/60">·</span>
                        <span class="text-sm text-base-content/60">{{ $flash->date->format('M j, Y') }}</span>
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
                    </div>

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
                </div>

                <div class="mt-2 text-sm text-base-content/80 space-y-1">
                    @if($flash->location)
                        <div>📍 {{ $flash->location }}</div>
                    @endif

                    @if($flash->sail_number)
                        <div>⛵ Sail #{{ $flash->sail_number }}</div>
                    @endif

                    @if($flash->notes)
                        <p class="mt-2">{{ $flash->notes }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
