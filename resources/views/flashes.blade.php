<x-layout>
    <x-slot:title>
        All Activities
    </x-slot:title>

    <div class="max-w-6xl mx-auto">
        <div class="mb-6">
            <h1 class="text-3xl font-bold">All Flash Activities</h1>
            <p class="text-base-content/70 mt-2">Recent sailing days logged by Lightning sailors</p>
        </div>

        <div class="space-y-4">
            @forelse($flashes as $flash)
                <x-flash-card :flash="$flash" />
            @empty
                <div class="hero py-12">
                    <div class="hero-content text-center">
                        <div>
                            <svg class="mx-auto h-12 w-12 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"></path>
                            </svg>
                            <p class="mt-4 text-base-content/60">No activities yet. Be the first to log your flash!</p>
                        </div>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</x-layout>
