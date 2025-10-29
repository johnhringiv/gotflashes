<x-layout>
    <x-slot:title>
        404 - Page Not Found
    </x-slot:title>
    <x-slot:description>
        The page you're looking for doesn't exist. Return to G.O.T. Flashes Challenge Tracker and continue tracking your sailing days.
    </x-slot:description>

    <div class="max-w-2xl mx-auto">
        <!-- 404 Error Card -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <!-- Header with Logo -->
                <div class="flex flex-col md:flex-row items-center gap-6 mb-6">
                    <div class="flex-shrink-0">
                        <img src="/images/lightning_logo.png" alt="Lightning Class" class="h-32 w-auto">
                    </div>
                    <div class="flex-1 text-center md:text-left">
                        <div class="text-8xl font-bold text-primary mb-4">404</div>
                        <h1 class="text-3xl font-bold mb-4">Lost at Sea?</h1>
                        <p class="text-lg text-base-content/80">
                            Looks like this page drifted off course. The page you're looking for doesn't exist or has been moved.
                        </p>
                    </div>
                </div>

                <!-- Navigation Suggestions -->
                <div class="divider">Where would you like to sail?</div>

                <div class="flex flex-col sm:flex-row gap-3 w-full">
                    <a href="/" class="btn btn-primary flex-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Home
                    </a>

                    <a href="/leaderboard" class="btn btn-accent flex-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        Leaderboard
                    </a>
                </div>

                <!-- Helpful Tip -->
                <div class="alert alert-info mt-6 text-left">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <p class="text-sm">
                            <strong>Need help?</strong> Visit the home page to learn about the G.O.T. Flashes Challenge and how to track your sailing days.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layout>