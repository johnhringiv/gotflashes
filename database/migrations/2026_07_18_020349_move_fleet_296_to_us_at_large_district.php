<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * ILCA office request: move Fleet #296 (Kansas City area, "Odd Winds") from
     * the Mississippi Valley District to the US@Large District.
     *
     * This is a forward reclassification (the fleet's current district), not a
     * historical correction. The app enforces that a member's district matches
     * their fleet's district (RegistrationForm/ProfileForm only allow a fleet
     * within the chosen district), and the district leaderboard ranks by
     * members.district_id — so moving the fleet also re-syncs its members'
     * current-year membership.
     *
     * `members` is a per-year snapshot table (see docs/membership-year-end-logic.md):
     * prior years are immutable so historical leaderboards stay accurate, and
     * profile-driven affiliation changes only touch the current year. We follow
     * that rule here and scope the member re-sync to the current year. (Fleet #296
     * was resurrected in June 2026 and has only a current-year member anyway, so
     * this is the same result — but the year scope keeps the pattern safe.)
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

        // Re-sync only the CURRENT-year membership rows for this fleet — prior
        // years are immutable snapshots (historical leaderboard accuracy).
        DB::table('members')
            ->where('fleet_id', $fleetId)
            ->where('year', (int) now()->year)
            ->update(['district_id' => $districtId, 'updated_at' => now()]);
    }
};
