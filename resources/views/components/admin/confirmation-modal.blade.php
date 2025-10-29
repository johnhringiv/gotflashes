@props([
    'show' => false,
    'title' => '',
    'cancelMethod' => 'cancelConfirmation',
])

@if($show)
    <div class="modal modal-open" role="dialog">
        <div class="modal-box">
            <button wire:click="{{ $cancelMethod }}" class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
            <h3 class="font-bold text-lg mb-4">{{ $title }}</h3>

            {{ $slot }}

            <div class="modal-action">
                <button wire:click="{{ $cancelMethod }}" class="btn">Cancel</button>
                {{ $action }}
            </div>
        </div>
        <div class="modal-backdrop" wire:click="{{ $cancelMethod }}"></div>
    </div>
@endif
