<!DOCTYPE html>
<html lang="en" data-theme="lofi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ isset($title) ? $title . ' - GOT-FLASHES' : 'GOT-FLASHES Challenge Tracker' }}</title>
    <link rel="preconnect" href="<https://fonts.bunny.net>">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5/themes.css" rel="stylesheet" type="text/css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex flex-col bg-base-200 font-sans">
<nav class="navbar bg-base-100 shadow-md">
    <div class="navbar-start">
        <a href="/" class="flex items-center px-2">
            <img src="{{ asset('images/got_flashes.png') }}" alt="GOT-FLASHES Challenge Tracker" class="h-12">
        </a>
    </div>
    <div class="navbar-center hidden lg:flex">
        <ul class="menu menu-horizontal px-1">
            <li><a href="/" class="btn btn-ghost btn-sm">Home</a></li>
            @auth
                <li><a href="{{ route('flashes.index') }}" class="btn btn-ghost btn-sm">Activities</a></li>
            @endauth
            <li><a href="{{ route('leaderboard') }}" class="btn btn-ghost btn-sm">Leaderboard</a></li>
        </ul>
    </div>
    <div class="navbar-end gap-2">
        @auth
            <span class="text-sm">{{ auth()->user()->name }}</span>
            <form method="POST" action="/logout" class="inline">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm">Logout</button>
            </form>
        @else
            <a href="/login" class="btn btn-ghost btn-sm">Sign In</a>
            <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Sign Up</a>
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
