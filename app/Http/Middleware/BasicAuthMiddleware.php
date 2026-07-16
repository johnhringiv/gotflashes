<?php

namespace App\Http\Middleware;

use App\Support\SecurityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BasicAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip Basic Auth for health check endpoint
        if ($request->is('up')) {
            return $next($request);
        }

        // Apply Basic Auth in every deployed environment (production + staging/dev), never in
        // local or testing. On staging (dev.gotflashes.com) this keeps the clone private; if no
        // credentials are configured it falls through (the noindex header still applies there).
        if (! app()->environment(['local', 'testing'])) {
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
                // Log failed access to the gated (staging/dev) environment so probing is visible.
                // Never log the submitted password — only the attempted username.
                SecurityLog::warning('basic_auth_failed', 'Basic auth failed', [
                    'attempted_username' => $inputUser,
                    'path' => $request->path(),
                ]);

                return response('Unauthorized', 401)
                    ->header('WWW-Authenticate', 'Basic realm="G.O.T. Flashes Staging"');
            }
        }

        return $next($request);
    }
}
