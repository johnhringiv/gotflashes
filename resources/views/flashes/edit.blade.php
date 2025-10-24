<x-layout>
    <x-slot:title>
        Edit Flash Activity
    </x-slot:title>

    <div class="max-w-2xl mx-auto">
        <h1 class="text-3xl font-bold mt-8">Edit Flash Activity</h1>

        <div class="card bg-base-100 shadow mt-8">
            <div class="card-body">
                @livewire('flash-form', [
                    'flash' => $flash,
                    'action' => route('flashes.update', $flash),
                    'method' => 'PUT',
                    'submitText' => 'Update Activity'
                ])
                <div class="card-actions justify-between mt-4">
                    <a href="{{ route('flashes.index') }}" class="btn btn-ghost btn-sm">
                        Cancel
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-layout>