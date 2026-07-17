<?php

namespace App\Providers;

use App\Http\Middleware\AuthenticationLoggingMiddleware;
use App\Http\Middleware\RequestLoggingMiddleware;
use App\Listeners\QueryLogListener;
use App\Support\SecurityLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class ObservabilityServiceProvider extends ServiceProvider
{
    /**
     * Warn-only transactional-email volume monitor. The provider (Resend free) caps
     * at 100 emails/day; we alert as we approach it so a quota drain is visible
     * in-app. This deliberately does NOT block — the per-IP/per-email throttles do the
     * source-level enforcement, and a blocking cap would drop legitimate mail.
     */
    private const MAIL_VOLUME_WARN_THRESHOLD = 80;

    private const MAIL_PROVIDER_DAILY_CAP = 100;

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register middleware
        $this->registerMiddleware();

        // Register event listeners
        $this->registerEventListeners();

        // Register custom error handlers
        $this->registerErrorHandlers();
    }

    /**
     * Register observability middleware
     */
    private function registerMiddleware(): void
    {
        $kernel = $this->app->make(Kernel::class);

        // Add request logging middleware globally
        $kernel->pushMiddleware(RequestLoggingMiddleware::class);

        // Add authentication logging middleware
        $kernel->pushMiddleware(AuthenticationLoggingMiddleware::class);
    }

    /**
     * Register event listeners for observability
     */
    private function registerEventListeners(): void
    {
        // Database query logging
        if (config('app.debug') || config('logging.log_queries', false)) {
            Event::listen(QueryExecuted::class, QueryLogListener::class);
        }

        // Log authentication events
        Event::listen(Login::class, function ($event) {
            SecurityLog::info('login_success', 'User logged in', [
                'user_id' => $event->user->id,
                'email' => $event->user->email,
                'remember' => $event->remember,
            ]);

            // Store login timestamp in session for duration tracking
            session()->put('login_timestamp', now());
        });

        Event::listen(Failed::class, function ($event) {
            SecurityLog::warning('auth_failed', 'Authentication failed', [
                'email' => $event->credentials['email'] ?? null,
                'guard' => $event->guard,
            ]);
        });

        Event::listen(Registered::class, function ($event) {
            SecurityLog::info('user_registered', 'New user registered', [
                'user_id' => $event->user->id,
                'email' => $event->user->email,
            ]);
        });

        // Warn-only daily email-volume monitor (alert, do not block). Surfaces a
        // distributed quota drain that per-IP/per-email throttles can't catch.
        Event::listen(MessageSent::class, function () {
            $key = 'mail-sent-count:'.now()->toDateString();

            // add() is atomic put-if-absent, so concurrent first-of-day sends can't
            // clobber each other's count (a plain has()/put() pair could reset it to 0).
            // The key TTL is endOfDay, which is the intended midnight daily reset.
            Cache::add($key, 0, now()->endOfDay());
            $count = Cache::increment($key);

            // Warn once per day when crossing the threshold. The separate warned-flag
            // (also atomic add()) means a cache flush that resets the counter mid-day
            // and re-crosses the threshold won't emit a second alert, while a strict
            // "=== threshold" would miss the warning entirely on any skipped/reset count.
            if (is_int($count) && $count >= self::MAIL_VOLUME_WARN_THRESHOLD
                && Cache::add('mail-volume-warned:'.now()->toDateString(), true, now()->endOfDay())) {
                // Logged directly (not via SecurityLog) on purpose: this is a cumulative daily
                // aggregate, not a per-request event. It can fire from a queue worker with no
                // request, and stamping it with the ip/user_agent of whichever send happened to
                // cross the threshold would falsely implicate that one client for the whole day.
                Log::channel('security')->warning('Transactional email volume approaching daily cap', [
                    'event' => 'mail_volume_warning',
                    'sent_today' => $count,
                    'warn_threshold' => self::MAIL_VOLUME_WARN_THRESHOLD,
                    'provider_daily_cap' => self::MAIL_PROVIDER_DAILY_CAP,
                ]);
            }
        });
    }

    /**
     * Register custom error handlers for better error tracking
     */
    private function registerErrorHandlers(): void
    {
        // Log uncaught exceptions with context
        app('Illuminate\Contracts\Debug\ExceptionHandler')->reportable(function (\Throwable $e) {
            Log::channel('structured')->error('Application error', [
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => collect($e->getTrace())->take(10)->toArray(), // Limit trace depth
                'previous_exception' => $e->getPrevious() ? get_class($e->getPrevious()) : null,
                'user_context' => [
                    'user_id' => auth()->id(),
                    'email' => auth()->user()?->email,
                    'ip' => request()->ip(),
                ],
                'request_context' => [
                    'url' => request()->fullUrl(),
                    'method' => request()->method(),
                    'user_agent' => request()->userAgent(),
                    'referer' => request()->header('referer'),
                ],
                'timestamp' => now()->toIso8601String(),
            ]);
        });

        // Log PHP warnings and notices in production
        if (app()->environment('production')) {
            set_error_handler(function ($severity, $message, $file, $line) {
                if (error_reporting() & $severity) {
                    Log::channel('structured')->warning('PHP warning/notice', [
                        'severity' => $severity,
                        'message' => $message,
                        'file' => $file,
                        'line' => $line,
                        'timestamp' => now()->toIso8601String(),
                    ]);
                }

                return false; // Let PHP handle it as well
            });
        }
    }
}
