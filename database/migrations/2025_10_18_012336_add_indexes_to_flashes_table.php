<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('flashes', function (Blueprint $table) {
            // Composite index for leaderboard queries (user + date + activity type)
            $table->index(['user_id', 'date', 'activity_type'], 'idx_flashes_leaderboard');

            // Index for tie-breaking by first entry timestamp
            $table->index('created_at', 'idx_flashes_created_at');
        });

        // Expression index for year filtering (SQLite specific optimization)
        DB::statement("CREATE INDEX idx_flashes_year ON flashes(strftime('%Y', date))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flashes', function (Blueprint $table) {
            $table->dropIndex('idx_flashes_leaderboard');
            $table->dropIndex('idx_flashes_created_at');
        });

        DB::statement('DROP INDEX IF EXISTS idx_flashes_year');
    }
};
