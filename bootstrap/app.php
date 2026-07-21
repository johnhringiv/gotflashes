<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\BasicAuthMiddleware;
use App\Http\Middleware\ContentSecurityPolicy;
use App\Http\Middleware\PreventIndexingNonProduction;
use App\Http\Middleware\SuperAdminMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Defense-in-depth only. nginx (docker/nginx.conf) owns real-client-IP resolution
        // via the realip module + CF-Connecting-IP, rewriting REMOTE_ADDR to the true client
        // before PHP sees it, so this does not drive $request->ip() and HTTPS is owned by
        // AppServiceProvider's forceScheme. Do not point logic (IP logging, rate limiting) at
        // it — see the "Proxy & Real Client IP" note in CLAUDE.md.
        $middleware->trustProxies(
            at: env('TRUSTED_PROXY_IP'),
            headers: Request::HEADER_X_FORWARDED_FOR |
                     Request::HEADER_X_FORWARDED_HOST |
                     Request::HEADER_X_FORWARDED_PROTO
        );

        $middleware->append(ContentSecurityPolicy::class);
        // Keep non-production environments (dev.gotflashes.com) out of search indexes.
        // Appended before BasicAuthMiddleware so it wraps the gate: a 401 challenge from
        // basic auth still carries the noindex header on its way out.
        $middleware->append(PreventIndexingNonProduction::class);
        $middleware->append(BasicAuthMiddleware::class);
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'super_admin' => SuperAdminMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A stale CSRF token (session expired while a form sat open) would
        // otherwise dead-end on Laravel's generic 419 "Page Expired" screen.
        // Convert it into a recoverable response: AJAX forms (forgot/reset
        // password) get a JSON message their fetch handler turns into a toast;
        // standard form posts (login) are redirected back to a fresh page with
        // a fresh token and a warning flash shown by the layout's toast system.
        //
        // Laravel's prepareException() maps TokenMismatchException to a generic
        // HttpException(419) before render callbacks run, so we match the
        // HttpException and filter on the 419 status (419 only ever originates
        // from a CSRF token mismatch). Returning null lets other statuses fall
        // through to default handling.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your session expired. Please refresh the page and try again.',
                ], 419);
            }

            return redirect()->back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->with('warning', 'Your session expired. Please try again.');
        });
    })->create();
