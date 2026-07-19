<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make "Unaffiliated/None" a real district and fleet instead of NULL.
     *
     * The nullable columns forced a three-way sentinel dance ('none' string
     * from the UI vs null vs untouched) through both profile/registration
     * forms and produced a string of validation bugs. With real rows:
     * - district_id/fleet_id are always valid foreign keys (NOT NULL),
     * - "None" validates like any other selection (plain exists checks),
     * - leaderboards exclude the None rows explicitly instead of relying on
     *   inner joins dropping nulls.
     *
     * The None fleet gets fleet_number 0 (real fleets are numbered from 1)
     * and belongs to the None district; validation special-cases it as
     * selectable alongside ANY district. No production members are
     * unaffiliated at migration time, so the backfill is a no-op there —
     * it exists for dev/test databases.
     */
    private const NONE_DISTRICT_NAME = 'Unaffiliated/None';

    private const NONE_FLEET_NUMBER = 0;

    public function up(): void
    {
        $noneDistrictId = DB::table('districts')->where('name', self::NONE_DISTRICT_NAME)->value('id')
            ?? DB::table('districts')->insertGetId([
                'name' => self::NONE_DISTRICT_NAME,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $noneFleetId = DB::table('fleets')->where('fleet_number', self::NONE_FLEET_NUMBER)->value('id')
            ?? DB::table('fleets')->insertGetId([
                'district_id' => $noneDistrictId,
                'fleet_number' => self::NONE_FLEET_NUMBER,
                'fleet_name' => 'None',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table('members')->whereNull('district_id')->update([
            'district_id' => $noneDistrictId,
            'updated_at' => now(),
        ]);
        DB::table('members')->whereNull('fleet_id')->update([
            'fleet_id' => $noneFleetId,
            'updated_at' => now(),
        ]);

        Schema::table('members', function (Blueprint $table) {
            $table->foreignId('district_id')->nullable(false)->change();
            $table->foreignId('fleet_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->foreignId('district_id')->nullable()->change();
            $table->foreignId('fleet_id')->nullable()->change();
        });

        $noneDistrictId = DB::table('districts')->where('name', self::NONE_DISTRICT_NAME)->value('id');
        $noneFleetId = DB::table('fleets')->where('fleet_number', self::NONE_FLEET_NUMBER)->value('id');

        if ($noneFleetId !== null) {
            DB::table('members')->where('fleet_id', $noneFleetId)->update(['fleet_id' => null, 'updated_at' => now()]);
            DB::table('fleets')->where('id', $noneFleetId)->delete();
        }
        if ($noneDistrictId !== null) {
            DB::table('members')->where('district_id', $noneDistrictId)->update(['district_id' => null, 'updated_at' => now()]);
            DB::table('districts')->where('id', $noneDistrictId)->delete();
        }
    }
};
