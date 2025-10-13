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
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-sm font-semibold">{{ $flash->user ? $flash->user->name : 'Anonymous' }}</span>
                    <span class="text-base-content/60">·</span>
                    <span class="text-sm text-base-content/60">{{ $flash->date->format('M j, Y') }}</span>
                    <span class="text-base-content/60">·</span>
                    <span class="text-sm text-base-content/60">{{ $flash->created_at->diffForHumans() }}</span>
                    @if($flash->activity_type === 'sailing' && $flash->event_type)
                        <span class="badge badge-primary badge-sm">Sailing - {{ ucfirst(str_replace('_', ' ', $flash->event_type)) }}</span>
                    @else
                        <span class="badge badge-primary badge-sm">{{ ucfirst(str_replace('_', ' ', $flash->activity_type)) }}</span>
                    @endif
                </div>

                <div class="mt-2 text-sm text-base-content/80 space-y-1">
                    @if($flash->location)
                        <div>📍 {{ $flash->location }}</div>
                    @endif

                    @if($flash->yacht_club)
                        <div>⛵ {{ $flash->yacht_club }}</div>
                    @endif

                    @if($flash->fleet_number || $flash->sail_number)
                        <div class="flex gap-3">
                            @if($flash->fleet_number)
                                <span>Fleet {{ $flash->fleet_number }}</span>
                            @endif
                            @if($flash->sail_number)
                                <span>Sail #{{ $flash->sail_number }}</span>
                            @endif
                        </div>
                    @endif

                    @if($flash->notes)
                        <p class="mt-2">{{ $flash->notes }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
