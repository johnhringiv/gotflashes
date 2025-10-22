<?php

namespace App\Providers;

use App\Http\Middleware\AuthenticationLoggingMiddleware;
use App\Http\Middleware\RequestLoggingMiddleware;
use App\Listeners\QueryLogListener;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class ObservabilityServiceProvider extends ServiceProvider
{
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
        Event::listen(\Illuminate\Auth\Events\Login::class, function ($event) {
            Log::channel('security')->info('User logged in', [
                'event' => 'login_success',
                'user_id' => $event->user->id,
                'email' => $event->user->email,
                'remember' => $event->remember,
                'timestamp' => now()->toIso8601String(),
            ]);

            // Store login timestamp in session for duration tracking
            session()->put('login_timestamp', now());
        });

        Event::listen(\Illuminate\Auth\Events\Failed::class, function ($event) {
            Log::channel('security')->warning('Authentication failed', [
                'event' => 'auth_failed',
                'email' => $event->credentials['email'] ?? null,
                'guard' => $event->guard,
                'timestamp' => now()->toIso8601String(),
            ]);
        });

        Event::listen(\Illuminate\Auth\Events\Registered::class, function ($event) {
            Log::channel('security')->info('New user registered', [
                'event' => 'user_registered',
                'user_id' => $event->user->id,
                'email' => $event->user->email,
                'timestamp' => now()->toIso8601String(),
            ]);
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
