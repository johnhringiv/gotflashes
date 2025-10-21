<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
