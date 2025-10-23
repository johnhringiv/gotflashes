<div class="space-y-4">
    @forelse($flashes as $flash)
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
                                    wire:click="confirmDelete({{ $flash->id }})"
                                    class="btn btn-ghost btn-xs text-error">
                                    Delete
                                </button>
                            </div>
                        @endif
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
    @empty
        <div class="hero py-12">
            <div class="hero-content text-center">
                <div>
                    <svg class="mx-auto h-12 w-12 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"></path>
                    </svg>
                    <p class="mt-4 text-base-content/60">No activities yet. Log your first flash to get started!</p>
                </div>
            </div>
        </div>
    @endforelse

    <!-- Pagination -->
    @if($flashes->hasPages())
        <div class="mt-6">
            {{ $flashes->links('pagination::livewire-tailwind') }}
        </div>
    @endif

    <!-- Edit Modal -->
    @if($editingFlashId)
        <div class="modal modal-open" role="dialog">
            <div class="modal-box max-w-2xl">
                <button wire:click="closeEditModal" class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
                <h3 class="font-bold text-lg mb-4">Edit Activity</h3>
                @livewire('flash-form', ['flash' => \App\Models\Flash::find($editingFlashId), 'submitText' => 'Update Activity'], key('edit-flash-' . $editingFlashId))
            </div>
            <div class="modal-backdrop" wire:click="closeEditModal"></div>
        </div>
    @endif

    <!-- Delete Confirmation Modal -->
    @if($deletingFlashId)
        <div class="modal modal-open" role="dialog">
            <div class="modal-box">
                <button wire:click="cancelDelete" class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
                <h3 class="font-bold text-lg mb-4">Delete Activity</h3>
                <p class="py-4">Are you sure you want to delete this activity? This action cannot be undone.</p>
                <div class="modal-action">
                    <button wire:click="cancelDelete" class="btn">Cancel</button>
                    <button wire:click="delete" class="btn btn-error">Delete</button>
                </div>
            </div>
            <div class="modal-backdrop" wire:click="cancelDelete"></div>
        </div>
    @endif
</div>
