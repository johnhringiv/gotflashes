<x-layout>
    <x-slot:title>
        Home
    </x-slot:title>

    <div class="max-w-4xl mx-auto">
        <!-- Hero Section -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body text-center">
                <h1 class="text-4xl font-bold mb-4">Welcome to the GOT-FLASHES Challenge!</h1>
                <p class="text-lg text-base-content/80">
                    Track your Lightning sailing days, earn awards, and join sailors across the class in getting on the water more often.
                </p>
            </div>
        </div>

        <!-- Award Tiers -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
            <div class="card bg-base-100 shadow-lg">
                <div class="card-body items-center text-center">
                    <div class="text-5xl font-bold text-primary">10</div>
                    <h3 class="card-title text-xl">Days</h3>
                    <p class="text-base-content/70">First tier award - Get started!</p>
                </div>
            </div>
            <div class="card bg-base-100 shadow-lg">
                <div class="card-body items-center text-center">
                    <div class="text-5xl font-bold text-secondary">25</div>
                    <h3 class="card-title text-xl">Days</h3>
                    <p class="text-base-content/70">Second tier award - Keep sailing!</p>
                </div>
            </div>
            <div class="card bg-base-100 shadow-lg">
                <div class="card-body items-center text-center">
                    <div class="text-5xl font-bold text-accent">50+</div>
                    <h3 class="card-title text-xl">Days</h3>
                    <p class="text-base-content/70">Third tier award + Burgee eligibility!</p>
                </div>
            </div>
        </div>

        <!-- What Counts Section -->
        <div class="card bg-base-100 shadow-xl mt-8">
            <div class="card-body">
                <h2 class="card-title text-2xl mb-4">What Counts as a Day?</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="font-bold text-lg mb-2">Sailing Days</h3>
                        <ul class="list-disc list-inside space-y-1 text-base-content/80">
                            <li>Any time sailing on ANY Lightning</li>
                            <li>Skipper or crew - both count!</li>
                            <li>Even one hour counts as a full day</li>
                            <li>Goal: Get the boat off the dock!</li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="font-bold text-lg mb-2">Non-Sailing Days</h3>
                        <ul class="list-disc list-inside space-y-1 text-base-content/80">
                            <li>Boat or trailer maintenance</li>
                            <li>Race Committee work (when Lightnings race)</li>
                            <li>Maximum 5 non-sailing days per year</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Who Can Participate -->
        <div class="card bg-base-100 shadow-xl mt-8">
            <div class="card-body">
                <h2 class="card-title text-2xl mb-4">Who Can Participate?</h2>
                <p class="text-base-content/80 text-lg">
                    Everyone who sails on Lightnings! You don't need to be a boat owner. Crews, friends, and anyone who gets time on a Lightning can track their days and earn awards.
                </p>
                <div class="alert alert-info mt-4">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>All activities must be logged by December 31st to count for the current year.</span>
                </div>
            </div>
        </div>

        <!-- Call to Action -->
        <div class="text-center mt-8 mb-8">
            <div class="alert alert-success">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>Ready to start tracking? Use the Sign Up button above to create your account and join the challenge!</span>
            </div>
        </div>
    </div>
</x-layout>
