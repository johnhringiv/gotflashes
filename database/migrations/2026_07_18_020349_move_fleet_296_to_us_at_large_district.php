<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * ILCA office request: move Fleet #296 (Kansas City area, "Odd Winds") from
     * the Mississippi Valley District to the US@Large District.
     *
     * The app enforces that a member's district matches their fleet's district
     * (RegistrationForm/ProfileForm only allow a fleet within the chosen
     * district), and the district leaderboard ranks by members.district_id — so
     * moving the fleet also re-syncs its existing members' district to keep the
     * data consistent and the leaderboard correct.
     */
    private const FLEET_NUMBER = 296;

    private const FROM_DISTRICT = 'Mississippi Valley';

    private const TO_DISTRICT = 'US@Large';

    public function up(): void
    {
        $this->reassign(self::TO_DISTRICT);
    }

    public function down(): void
    {
        $this->reassign(self::FROM_DISTRICT);
    }

    /**
     * Point Fleet #296 — and its members — at the given district (by name).
     * Idempotent and safe to re-run; skips quietly if the fleet or district is
     * absent in an environment.
     */
    private function reassign(string $districtName): void
    {
        $districtId = DB::table('districts')->where('name', $districtName)->value('id');
        $fleetId = DB::table('fleets')->where('fleet_number', self::FLEET_NUMBER)->value('id');

        if ($districtId === null || $fleetId === null) {
            return;
        }

        DB::table('fleets')
            ->where('id', $fleetId)
            ->update(['district_id' => $districtId, 'updated_at' => now()]);

        // Keep members of this fleet in sync with the fleet's district.
        DB::table('members')
            ->where('fleet_id', $fleetId)
            ->update(['district_id' => $districtId, 'updated_at' => now()]);
    }
};
