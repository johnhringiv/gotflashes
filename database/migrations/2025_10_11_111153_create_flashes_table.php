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
        Schema::create('flashes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->enum('activity_type', ['sailing', 'maintenance', 'race_committee']);
            $table->enum('event_type', ['regatta', 'club_race', 'practice', 'leisure'])->nullable();
            $table->string('yacht_club', 100)->nullable();
            $table->integer('fleet_number')->nullable();
            $table->string('location', 255)->nullable();
            $table->integer('sail_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Ensure one activity per date per user
            $table->unique(['user_id', 'date']);

            // Indexes for common queries
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flashes');
    }
};
