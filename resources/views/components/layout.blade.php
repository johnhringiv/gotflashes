<!DOCTYPE html>
<html lang="en" data-theme="lightning">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    @php
        $baseUrl = rtrim(config('app.url'), '/');
        $pageTitle = isset($title) ? $title . ' - G.O.T. Flashes' : 'G.O.T. Flashes Challenge Tracker';
        $pageDescription = $description ?? 'Track your Lightning Class sailing days and earn awards. Get Out There - FLASHES encourages sailors to log sailing activities toward 10, 25, and 50+ day annual awards.';
        $currentPath = request()->getPathInfo();
        $pageUrl = $baseUrl . $currentPath;
        $ogImage = $baseUrl . '/images/got_flashes.png';
    @endphp

    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    <meta name="author" content="Lightning Class Association">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">

    <!-- Canonical URL -->
    <link rel="canonical" href="{{ $pageUrl }}">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ $pageUrl }}">
    <meta property="og:site_name" content="G.O.T. Flashes Challenge Tracker">
    <meta property="og:locale" content="en_US">
    <meta property="og:image" content="{{ $ogImage }}">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $pageDescription }}">
    <meta name="twitter:image" content="{{ $ogImage }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen flex flex-col bg-base-200 font-sans">
<nav class="navbar shadow-md" style="background-color: var(--color-primary); color: var(--color-primary-content);">
    <div class="navbar-start">
        <!-- Mobile menu dropdown -->
        <div class="dropdown lg:hidden">
            <button type="button" tabindex="0" class="btn btn-ghost btn-circle text-white" aria-label="Menu">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <ul tabindex="0" class="menu dropdown-content mt-3 z-50 p-3 shadow-lg bg-base-100 rounded-box w-56 text-base-content">
                <li><a href="/" class="text-base py-3 {{ request()->path() === '/' ? 'active font-bold text-accent' : '' }}">Home</a></li>
                @auth
                    <li><a href="/flashes" class="text-base py-3 {{ str_starts_with(request()->path(), 'flashes') ? 'active font-bold text-accent' : '' }}">Activities</a></li>
                @endauth
                <li><a href="/leaderboard" class="text-base py-3 {{ str_starts_with(request()->path(), 'leaderboard') ? 'active font-bold text-accent' : '' }}">Leaderboard</a></li>

                @auth
                    <li class="menu-title mt-2 text-xs opacity-70">Account</li>
                    <li class="text-sm px-4 py-2 text-warning font-semibold">{{ auth()->user()->name }}</li>
                    <li>
                        <form method="POST" action="/logout">
                            @csrf
                            <button type="submit" class="text-base py-3 text-error font-semibold">Logout</button>
                        </form>
                    </li>
                @else
                    <li class="menu-title mt-2"></li>
                    <li><a href="/login" class="text-base py-3 {{ request()->path() === 'login' ? 'active font-bold text-accent' : '' }}">Sign In</a></li>
                    <li><a href="/register" class="text-base py-3 {{ request()->path() === 'register' ? 'active font-bold text-accent' : '' }}">Sign Up</a></li>
                @endauth
            </ul>
        </div>

        <!-- Logo -->
        <a href="/" class="flex items-center px-2">
            <img src="/images/got_flashes.png" alt="G.O.T. Flashes Challenge Tracker" class="h-12">
        </a>
    </div>

    <!-- Desktop menu (centered) -->
    <div class="navbar-center hidden lg:flex">
        <ul class="menu menu-horizontal px-1">
            <li><a href="/" class="btn btn-ghost btn-sm hover:bg-white/10 {{ request()->path() === '/' ? '!text-white !font-bold underline decoration-accent decoration-2 underline-offset-4' : 'text-white/80' }}">Home</a></li>
            @auth
                <li><a href="/flashes" class="btn btn-ghost btn-sm hover:bg-white/10 {{ str_starts_with(request()->path(), 'flashes') ? '!text-white !font-bold underline decoration-accent decoration-2 underline-offset-4' : 'text-white/80' }}">Activities</a></li>
            @endauth
            <li><a href="/leaderboard" class="btn btn-ghost btn-sm hover:bg-white/10 {{ str_starts_with(request()->path(), 'leaderboard') ? '!text-white !font-bold underline decoration-accent decoration-2 underline-offset-4' : 'text-white/80' }}">Leaderboard</a></li>
        </ul>
    </div>

    <!-- Auth buttons (desktop only) -->
    <div class="navbar-end gap-2 hidden lg:flex">
        @auth
            <span class="text-sm text-warning font-semibold">{{ auth()->user()->name }}</span>
            <form method="POST" action="/logout" class="inline">
                @csrf
                <button type="submit" class="btn btn-error btn-sm">Logout</button>
            </form>
        @else
            <a href="/login" class="btn btn-ghost btn-sm text-white hover:bg-white/10 {{ request()->path() === 'login' ? '!font-bold underline decoration-accent decoration-2 underline-offset-4' : '' }}">Sign In</a>
            <a href="/register" class="btn btn-accent btn-sm {{ request()->path() === 'register' ? '!font-bold underline decoration-white decoration-2 underline-offset-4' : '' }}">Sign Up</a>
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
    @endif

    <!-- Warning Toast -->
    @if (session('warning'))
        <div class="toast toast-top toast-center z-50" style="top: 5rem;">
            <div class="alert alert-warning" id="warning-toast">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0 stroke-current" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span>{{ session('warning') }}</span>
            </div>
        </div>
    @endif

    {{ $slot }}
</main>

<footer class="footer footer-center p-5 bg-base-300 text-base-content text-xs">
    <div>
        <p>© {{ date('Y') }} Lightning Class Association - G.O.T. Flashes Challenge Tracker</p>
        <p class="text-xs opacity-70">Track your sailing days • Earn awards • Build the Lightning community</p>
        <p class="text-xs opacity-60 mt-2">
            Created by <a href="https://johnhringiv.com/" target="_blank" rel="noopener noreferrer" class="link link-hover">John Ring</a>
        </p>
    </div>
</footer>
@livewireScripts
</body>
</html>
