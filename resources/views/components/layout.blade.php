<!DOCTYPE html>
<html lang="en" data-theme="lightning">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    @php
        $baseUrl = 'https://gotflashes.com';
        $pageTitle = isset($title) ? $title . ' - GOT-FLASHES' : 'GOT-FLASHES Challenge Tracker';
        $pageDescription = $description ?? 'Track your Lightning Class sailing days and earn awards. Get Out There - FLASHES encourages sailors to log sailing activities toward 10, 25, and 50+ day annual awards.';
        $currentPath = request()->getPathInfo();
        $pageUrl = $baseUrl . $currentPath;
        $ogImage = $baseUrl . '/images/got_flashes.png';
    @endphp

    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    <meta name="author" content="Lightning Class Association">

    <!-- Canonical URL -->
    <link rel="canonical" href="{{ $pageUrl }}">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ $pageUrl }}">
    <meta property="og:site_name" content="GOT-FLASHES Challenge Tracker">
    <meta property="og:locale" content="en_US">
    <meta property="og:image" content="{{ $ogImage }}">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $pageDescription }}">
    <meta name="twitter:image" content="{{ $ogImage }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex flex-col bg-base-200 font-sans">
<nav class="navbar shadow-md" style="background-color: var(--color-primary); color: var(--color-primary-content);">
    <div class="navbar-start">
        <a href="/" class="flex items-center px-2">
            <img src="/images/got_flashes.png" alt="GOT-FLASHES Challenge Tracker" class="h-12">
        </a>
    </div>
    <div class="navbar-center hidden lg:flex">
        <ul class="menu menu-horizontal px-1">
            <li><a href="/" class="btn btn-ghost btn-sm hover:bg-white/10 {{ request()->path() === '/' ? '!text-white !font-bold underline decoration-accent decoration-2 underline-offset-4' : 'text-white/80' }}">Home</a></li>
            @auth
                <li><a href="/flashes" class="btn btn-ghost btn-sm hover:bg-white/10 {{ str_starts_with(request()->path(), 'flashes') ? '!text-white !font-bold underline decoration-accent decoration-2 underline-offset-4' : 'text-white/80' }}">Activities</a></li>
            @endauth
            <li><a href="/leaderboard" class="btn btn-ghost btn-sm hover:bg-white/10 {{ str_starts_with(request()->path(), 'leaderboard') ? '!text-white !font-bold underline decoration-accent decoration-2 underline-offset-4' : 'text-white/80' }}">Leaderboard</a></li>
        </ul>
    </div>
    <div class="navbar-end gap-2">
        @auth
            <span class="text-sm text-white">{{ auth()->user()->name }}</span>
            <form method="POST" action="/logout" class="inline">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm text-white hover:bg-white/10">Logout</button>
            </form>
        @else
            <a href="/login" class="btn btn-ghost btn-sm text-white hover:bg-white/10">Sign In</a>
            <a href="/register" class="btn btn-accent btn-sm">Sign Up</a>
        @endauth
    </div>
</nav>

<main class="flex-1 container mx-auto px-4 py-8">
    <!-- Success Toast -->
    @if (session('success'))
        <div class="toast toast-top toast-center z-50" style="top: 5rem;">
            <div class="alert alert-success" id="success-toast">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0 stroke-current" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        </div>
        <script>
            setTimeout(() => {
                const toast = document.getElementById('success-toast');
                if (toast) {
                    toast.style.transition = 'opacity 0.5s';
                    toast.style.opacity = '0';
                    setTimeout(() => toast.parentElement.remove(), 500);
                }
            }, 3000);
        </script>
    @endif

    {{ $slot }}
</main>

<footer class="footer footer-center p-5 bg-base-300 text-base-content text-xs">
    <div>
        <p>© {{ date('Y') }} Lightning Class Association - GOT-FLASHES Challenge Tracker</p>
        <p class="text-xs opacity-70">Track your sailing days • Earn awards • Build the Lightning community</p>
        <p class="text-xs opacity-60 mt-2">
            Created by <a href="https://johnhringiv.com/" target="_blank" rel="noopener noreferrer" class="link link-hover">John Ring</a>
        </p>
    </div>
</footer>
</body>
</html>
