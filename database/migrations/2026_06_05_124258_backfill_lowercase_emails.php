<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Lowercase any existing mixed-case emails so they match the values the
     * application now writes (User::normalizeEmail + email mutators). Runs
     * before the stricter validation/normalization is relied upon for lookups.
     *
     * Irreversible: the original casing isn't recorded, so there's no down().
     */
    public function up(): void
    {
        DB::table('users')
            ->whereRaw('email <> LOWER(email)')
            ->update(['email' => DB::raw('LOWER(email)')]);

        DB::table('users')
            ->whereNotNull('pending_email')
            ->whereRaw('pending_email <> LOWER(pending_email)')
            ->update(['pending_email' => DB::raw('LOWER(pending_email)')]);
    }

    /**
     * Reverse the migrations.
     *
     * No-op: lowercasing is not reversible (original casing was not preserved).
     */
    public function down(): void
    {
        //
    }
};
