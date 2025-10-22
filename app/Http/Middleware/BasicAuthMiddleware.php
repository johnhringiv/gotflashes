<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BasicAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip Basic Auth for health check endpoint
        if ($request->is('up')) {
            return $next($request);
        }

        // Only apply Basic Auth in production environment
        if (app()->environment('production')) {
            $username = config('auth.basic.username');
            $password = config('auth.basic.password');

            // If credentials are not configured, skip authentication
            if (empty($username) || empty($password)) {
                return $next($request);
            }

            // Check if PHP_AUTH_USER and PHP_AUTH_PW are set
            $inputUser = $request->getUser();
            $inputPass = $request->getPassword();

            // Verify credentials
            if ($inputUser !== $username || $inputPass !== $password) {
                return response('Unauthorized', 401)
                    ->header('WWW-Authenticate', 'Basic realm="G.O.T. Flashes Staging"');
            }
        }

        return $next($request);
    }
}
