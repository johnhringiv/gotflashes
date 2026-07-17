<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS in every deployed environment (production + staging/dev), never in
        // local or testing. Upstream TLS terminates at Cloudflare/HAProxy so the app sees
        // plain HTTP; without this, route()/redirects on staging emit http:// URLs and the
        // AJAX forms (forgot/reset password) get mixed-content-blocked. Matches the
        // deployed-env gate used by BasicAuthMiddleware.
        if (! app()->environment(['local', 'testing'])) {
            URL::forceScheme('https');
        }

        // Register CSP nonce Blade directive
        Blade::directive('cspNonce', function () {
            return "<?php echo 'nonce=\"' . app('csp-nonce') . '\"'; ?>";
        });

        // Log slow database queries in production
        if (app()->environment('production')) {
            DB::listen(function ($query) {
                // Log queries that take more than 100ms
                if ($query->time > 100) {
                    Log::warning('Slow database query detected', [
                        'duration_ms' => round($query->time, 2),
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                        'connection' => $query->connectionName,
                    ]);
                }
            });
        }
    }
}
