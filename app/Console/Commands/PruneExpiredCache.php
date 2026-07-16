<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneExpiredCache extends Command
{
    protected $signature = 'cache:prune-expired';

    protected $description = 'Delete expired rows from the database cache store (they are otherwise only evicted lazily on re-read, so short-lived rate-limiter keys accumulate forever).';

    public function handle(): int
    {
        if (config('cache.default') !== 'database') {
            $this->info('Cache store is not "database"; nothing to prune.');

            return self::SUCCESS;
        }

        $deleted = DB::connection(config('cache.stores.database.connection'))
            ->table(config('cache.stores.database.table', 'cache'))
            ->where('expiration', '<', now()->getTimestamp())
            ->delete();

        $this->info("Pruned {$deleted} expired cache row(s).");

        return self::SUCCESS;
    }
}
