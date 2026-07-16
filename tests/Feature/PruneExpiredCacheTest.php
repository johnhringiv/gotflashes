<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PruneExpiredCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_only_expired_rows(): void
    {
        config(['cache.default' => 'database']);

        DB::table('cache')->insert([
            ['key' => 'live', 'value' => 'x', 'expiration' => now()->addHour()->getTimestamp()],
            ['key' => 'dead', 'value' => 'x', 'expiration' => now()->subHour()->getTimestamp()],
        ]);

        $this->artisan('cache:prune-expired')->assertSuccessful();

        $this->assertDatabaseHas('cache', ['key' => 'live']);
        $this->assertDatabaseMissing('cache', ['key' => 'dead']);
    }

    public function test_it_is_a_noop_when_cache_store_is_not_database(): void
    {
        config(['cache.default' => 'array']);

        DB::table('cache')->insert([
            ['key' => 'dead', 'value' => 'x', 'expiration' => now()->subHour()->getTimestamp()],
        ]);

        $this->artisan('cache:prune-expired')->assertSuccessful();

        // Untouched: the command only prunes when the database store is active.
        $this->assertDatabaseHas('cache', ['key' => 'dead']);
    }
}
