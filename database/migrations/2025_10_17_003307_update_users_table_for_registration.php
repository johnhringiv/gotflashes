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
            // Remove the old name column
            $table->dropColumn('name');

            // Add new columns
            $table->string('first_name');
            $table->string('last_name');
            $table->date('date_of_birth');
            $table->enum('gender', ['male', 'female', 'non_binary', 'prefer_not_to_say']);
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city');
            $table->string('state');
            $table->string('zip_code', 20);
            $table->string('country');
            $table->string('district', 100)->nullable();
            $table->integer('fleet_number')->nullable();
            $table->string('yacht_club')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Remove new columns
            $table->dropColumn([
                'first_name',
                'last_name',
                'date_of_birth',
                'gender',
                'address_line1',
                'address_line2',
                'city',
                'state',
                'zip_code',
                'country',
                'district',
                'fleet_number',
                'yacht_club',
            ]);

            // Add back old name column
            $table->string('name');
        });
    }
};
