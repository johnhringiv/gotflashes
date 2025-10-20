<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Remove old district and fleet_number columns
            // Now using members table for year-specific affiliations
            $table->dropColumn(['district', 'fleet_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Restore old columns if migration is rolled back
            $table->string('district', 100)->nullable();
            $table->integer('fleet_number')->nullable();
        });
    }
};
