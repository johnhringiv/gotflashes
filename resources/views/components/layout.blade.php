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
            <li><a href="{{ route('flashes.index') }}" class="btn btn-ghost btn-sm">Activities</a></li>
        </ul>
    </div>
    <div class="navbar-end gap-2">
        <a href="#" class="btn btn-ghost btn-sm">Sign In</a>
        <a href="#" class="btn btn-primary btn-sm">Sign Up</a>
    </div>
</nav>

<main class="flex-1 container mx-auto px-4 py-8">
    {{ $slot }}
</main>

<footer class="footer footer-center p-5 bg-base-300 text-base-content text-xs">
    <div>
        <p>© {{ date('Y') }} Lightning Class Association - GOT-FLASHES Challenge Tracker</p>
        <p class="text-xs opacity-70">Track your sailing days • Earn awards • Build the Lightning community</p>
    </div>
</footer>
</body>
</html>
