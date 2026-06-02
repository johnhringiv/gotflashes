<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fleets requested by the ILCA office (issue #29):
     * - #528 Paw Paw — new fleet, Michigan District
     * - #246 Lake Winnipesaukee — resurrected, New England District
     * - #296 Kansas City — resurrected, Mississippi Valley District
     *
     * [district name, fleet number, fleet name]
     */
    private const FLEETS = [
        ['Michigan', 528, 'Paw Paw'],
        ['New England', 246, 'Lake Winnipesaukee'],
        ['Mississippi Valley', 296, 'Kansas City'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::FLEETS as [$districtName, $fleetNumber, $fleetName]) {
            $districtId = DB::table('districts')->where('name', $districtName)->value('id');

            if ($districtId === null) {
                // District should already exist from the initial schema seed;
                // skip rather than fail if an environment is missing it.
                continue;
            }

            // Idempotent: safe to re-run and won't duplicate the unique fleet_number.
            DB::table('fleets')->updateOrInsert(
                ['fleet_number' => $fleetNumber],
                [
                    'district_id' => $districtId,
                    'fleet_name' => $fleetName,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('fleets')
            ->whereIn('fleet_number', array_column(self::FLEETS, 1))
            ->delete();
    }
};
