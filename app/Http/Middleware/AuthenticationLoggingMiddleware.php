<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuthenticationLoggingMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Log authentication-related events
        if ($request->is('login') && $request->isMethod('POST')) {
            $this->logLoginAttempt($request, $response);
        } elseif ($request->is('register') && $request->isMethod('POST')) {
            $this->logRegistrationAttempt($request, $response);
        } elseif ($request->is('logout') && $request->isMethod('POST')) {
            $this->logLogoutEvent($request);
        }

        return $response;
    }

    /**
     * Log login attempts
     */
    private function logLoginAttempt(Request $request, Response $response): void
    {
        // Check if user is authenticated after response (works with redirect()->intended())
        $successful = $response->getStatusCode() === 302 && auth()->check();

        Log::channel('security')->info('Login attempt', [
            'event' => 'login_attempt',
            'email' => $request->input('email'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'success' => $successful,
            'status_code' => $response->getStatusCode(),
            'timestamp' => now()->toIso8601String(),
        ]);

        if (! $successful) {
            Log::channel('security')->warning('Failed login attempt', [
                'event' => 'login_failed',
                'email' => $request->input('email'),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toIso8601String(),
            ]);
        }
    }

    /**
     * Log registration attempts
     */
    private function logRegistrationAttempt(Request $request, Response $response): void
    {
        // Check if user is authenticated after response (more reliable than URL matching)
        $successful = $response->getStatusCode() === 302 && auth()->check();

        Log::channel('security')->info('Registration attempt', [
            'event' => 'registration_attempt',
            'email' => $request->input('email'),
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'district' => $request->input('district'),
            'fleet_number' => $request->input('fleet_number'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'success' => $successful,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Log logout events
     */
    private function logLogoutEvent(Request $request): void
    {
        Log::channel('security')->info('User logout', [
            'event' => 'logout',
            'user_id' => auth()->id(),
            'email' => auth()->user()?->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'session_duration_minutes' => $this->calculateSessionDuration(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Calculate session duration in minutes
     */
    private function calculateSessionDuration(): ?float
    {
        if (! session()->has('login_timestamp')) {
            return null;
        }

        $loginTime = session()->get('login_timestamp');

        return now()->diffInMinutes($loginTime);
    }
}
