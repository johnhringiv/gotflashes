<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Site-operator tier, above the award-admin `is_admin`. Gates site
            // configuration (community goal now; operational views like issue #48
            // later). Independent of is_admin — a user may hold either or both.
            $table->boolean('is_super_admin')->default(false)->after('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
        });
    }
};
