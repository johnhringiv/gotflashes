<?php

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
        // Trust HAProxy (on pfSense) and recognize forwarded headers
        $middleware->trustProxies(
            at: env('TRUSTED_PROXY_IP'),
            headers: Request::HEADER_X_FORWARDED_FOR |
                     Request::HEADER_X_FORWARDED_HOST |
                     Request::HEADER_X_FORWARDED_PROTO
        );

        $middleware->append(\App\Http\Middleware\ContentSecurityPolicy::class);
        $middleware->append(\App\Http\Middleware\BasicAuthMiddleware::class);
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
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
