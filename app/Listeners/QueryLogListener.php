<?php

namespace App\Listeners;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;

class QueryLogListener
{
    /**
     * Handle the event.
     */
    public function handle(QueryExecuted $event): void
    {
        $time = $event->time;
        $threshold = (int) config('logging.slow_query_threshold_ms', 100);

        // Check if we should log this query
        $isSlowQuery = $time > $threshold;
        $shouldLogAllQueries = config('logging.log_queries', false) && config('app.debug');
        $shouldLogSlowQueries = config('logging.log_slow_queries', true);

        // Skip if no logging is needed
        if (! $shouldLogAllQueries && ! ($isSlowQuery && $shouldLogSlowQueries)) {
            return;
        }

        // Log all queries to query channel if enabled
        if ($shouldLogAllQueries) {
            Log::channel('query')->debug('Database query executed', [
                'sql' => $event->sql,
                'bindings' => $event->bindings,
                'time_ms' => $time,
                'connection' => $event->connectionName,
                'slow_query' => $isSlowQuery,
            ]);
        }

        // Log slow queries to performance channel (only if not already logged to query channel)
        if ($isSlowQuery && $shouldLogSlowQueries && ! $shouldLogAllQueries) {
            Log::channel('performance')->warning('Slow database query detected', [
                'sql' => $event->sql,
                'bindings' => $event->bindings,
                'time_ms' => $time,
                'connection' => $event->connectionName,
                'threshold_ms' => $threshold,
            ]);
        }
    }
}
