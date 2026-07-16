<?php

namespace App\Http\Middleware;

use App\Support\SecurityLog;
use Closure;
use Illuminate\Http\Request;
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
            SecurityLog::warning('admin_access_denied', 'Admin access denied', [
                'user_id' => $request->user()?->id,
                'email' => $request->user()?->email,
                'path' => $request->path(),
            ]);

            abort(403, 'Unauthorized. Admin access required.');
        }

        return $next($request);
    }
}
