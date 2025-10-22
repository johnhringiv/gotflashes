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

        // Format the query with bindings for logging
        $fullQuery = $this->formatQuery($event->sql, $event->bindings);

        // Log all queries to query channel if enabled
        if ($shouldLogAllQueries) {
            Log::channel('query')->debug('Database query executed', [
                'sql' => $fullQuery,
                'time_ms' => $time,
                'connection' => $event->connectionName,
                'slow_query' => $isSlowQuery,
            ]);
        }

        // Log slow queries to performance channel (only if not already logged to query channel)
        if ($isSlowQuery && $shouldLogSlowQueries && ! $shouldLogAllQueries) {
            Log::channel('performance')->warning('Slow database query detected', [
                'sql' => $fullQuery,
                'time_ms' => $time,
                'connection' => $event->connectionName,
                'threshold_ms' => $threshold,
            ]);
        }
    }

    /**
     * Format query with bindings for logging
     */
    private function formatQuery(string $sql, array $bindings): string
    {
        return vsprintf(str_replace('?', '%s', $sql), array_map(function ($binding) {
            if (is_string($binding)) {
                return "'$binding'";
            }
            if (is_bool($binding)) {
                return $binding ? '1' : '0';
            }
            if (is_null($binding)) {
                return 'NULL';
            }

            return $binding;
        }, $bindings));
    }
}
