<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ContentSecurityPolicy
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Generate a unique nonce for this request
        $nonce = Str::random(32);

        // Store nonce in app container for access in views
        app()->instance('csp-nonce', $nonce);

        $response = $next($request);

        // In development, Vite runs on a separate port (hot file exists when Vite is running)
        $viteRunning = file_exists(public_path('hot'));
        $viteDevServer = $viteRunning ? ' http://127.0.0.1:5173 ws://127.0.0.1:5173' : '';

        // Build CSP directives
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'".$viteDevServer,
            "style-src 'self' 'unsafe-inline'".$viteDevServer,
            "img-src 'self' data:".$viteDevServer,
            "font-src 'self' data:",
            "connect-src 'self'".$viteDevServer,
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        $csp = implode('; ', $directives);

        // Enforce CSP policy
        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
