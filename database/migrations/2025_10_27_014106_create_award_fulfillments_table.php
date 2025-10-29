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
        Schema::create('award_fulfillments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->smallInteger('year');
            $table->tinyInteger('award_tier')->comment('10, 25, or 50');
            $table->enum('status', ['processing', 'sent']);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Unique constraint: one award per user per tier per year
            $table->unique(['user_id', 'year', 'award_tier'], 'unique_user_year_tier');

            // Index for performance
            $table->index(['year', 'status'], 'idx_year_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('award_fulfillments');
    }
};
