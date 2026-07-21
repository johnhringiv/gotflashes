<?php

namespace App\Http\Middleware;

use App\Support\SecurityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminMiddleware
{
    /**
     * Gate site-operator routes (site settings; operational views).
     * Requires the elevated `is_super_admin` flag, above the award-admin
     * `is_admin` checked by AdminMiddleware.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isSuperAdmin()) {
            // Log denied access so probing of the site-admin area is visible.
            SecurityLog::warning('super_admin_access_denied', 'Site admin access denied', [
                'user_id' => $request->user()?->id,
                'email' => $request->user()?->email,
                'path' => $request->path(),
            ]);

            abort(403, 'Unauthorized. Site admin access required.');
        }

        return $next($request);
    }
}
