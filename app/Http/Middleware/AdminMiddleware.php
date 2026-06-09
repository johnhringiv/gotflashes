<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated and is an admin
        if (! $request->user() || ! $request->user()->is_admin) {
            // Log denied admin access so probing of /admin/* is visible in the security channel.
            Log::channel('security')->warning('Admin access denied', [
                'event' => 'admin_access_denied',
                'user_id' => $request->user()?->id,
                'email' => $request->user()?->email,
                'ip' => $request->ip(),
                'path' => $request->path(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toIso8601String(),
            ]);

            abort(403, 'Unauthorized. Admin access required.');
        }

        return $next($request);
    }
}
