<x-layout>
    <x-slot:title>
        Leaderboard
    </x-slot:title>
    <x-slot:description>
        View the Lightning Class G.O.T. Flashes leaderboard rankings. See top sailors, fleets, and districts by sailing days tracked in 2025.
    </x-slot:description>

    <div class="max-w-6xl mx-auto">
        <div class="mb-6">
            <h1 class="text-3xl font-bold">
                <span class="text-primary">{{ now()->year }}</span>
                <span class="text-accent">Leaderboard</span>
            </h1>
            <p class="text-base-content/70 mt-2">Top Lightning sailors by total flashes this year</p>
        </div>

        @livewire('leaderboard')
    </div>
</x-layout>
