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
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('district_id')->nullable()->constrained('districts')->onDelete('set null');
            $table->foreignId('fleet_id')->nullable()->constrained('fleets')->onDelete('set null');
            $table->integer('year');
            $table->timestamps();

            // Unique constraint: one membership record per user per year
            $table->unique(['user_id', 'year']);

            // Indexes for efficient queries
            $table->index('year');
            $table->index('district_id');
            $table->index('fleet_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
