<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventIndexingNonProduction
{
    /**
     * Add a noindex header on every non-production response so staging/dev
     * environments (e.g. dev.gotflashes.com) are never picked up by search engines.
     *
     * This is the actual no-index guarantee: it prevents indexing (not just crawling),
     * works for non-HTML responses, and is unconditional, so it holds even if the basic-auth
     * gate's credentials are blank. Production is unaffected.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! app()->environment('production')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }

        return $response;
    }
}
