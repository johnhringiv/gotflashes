<x-layout>
    <x-slot:title>
        My Activities
    </x-slot:title>

    <div class="max-w-6xl mx-auto">
        <div class="mb-6">
            <h1 class="text-3xl font-bold">My Activities</h1>
            <p class="text-base-content/70 mt-2">Your sailing days and progress toward awards</p>
        </div>

        <!-- Progress Card (Livewire) -->
        @livewire('progress-card')

        <!-- Flash Entry Form (Livewire) -->
        <div class="card bg-base-100 shadow mb-6">
            <div class="card-body">
                <h2 class="card-title text-xl mb-4">Log a New Activity</h2>
                @livewire('flash-form', ['submitText' => 'Log Activity'])
            </div>
        </div>

        <!-- Flash List (Livewire) -->
        @livewire('flash-list')
    </div>
</x-layout>
