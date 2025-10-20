<?php

namespace Tests\Unit;

use App\Models\District;
use App\Models\Fleet;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Note: Districts and fleets are seeded automatically by the migration
        // via RefreshDatabase trait
    }

    public function test_member_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $district = District::first();
        $fleet = Fleet::first();

        $member = Member::create([
            'user_id' => $user->id,
            'district_id' => $district->id,
            'fleet_id' => $fleet->id,
            'year' => 2025,
        ]);

        $this->assertInstanceOf(User::class, $member->user);
        $this->assertEquals($user->id, $member->user->id);
    }

    public function test_member_belongs_to_district(): void
    {
        $user = User::factory()->create();
        $district = District::first();
        $fleet = Fleet::first();

        $member = Member::create([
            'user_id' => $user->id,
            'district_id' => $district->id,
            'fleet_id' => $fleet->id,
            'year' => 2025,
        ]);

        $this->assertInstanceOf(District::class, $member->district);
        $this->assertEquals($district->id, $member->district->id);
    }

    public function test_member_belongs_to_fleet(): void
    {
        $user = User::factory()->create();
        $district = District::first();
        $fleet = Fleet::first();

        $member = Member::create([
            'user_id' => $user->id,
            'district_id' => $district->id,
            'fleet_id' => $fleet->id,
            'year' => 2025,
        ]);

        $this->assertInstanceOf(Fleet::class, $member->fleet);
        $this->assertEquals($fleet->id, $member->fleet->id);
    }

    public function test_member_can_be_unaffiliated(): void
    {
        $user = User::factory()->create();

        $member = Member::create([
            'user_id' => $user->id,
            'district_id' => null,
            'fleet_id' => null,
            'year' => 2025,
        ]);

        $this->assertNull($member->district_id);
        $this->assertNull($member->fleet_id);
        $this->assertNull($member->district);
        $this->assertNull($member->fleet);
    }

    public function test_user_can_have_multiple_memberships_across_years(): void
    {
        $user = User::factory()->create();
        $district1 = District::skip(0)->first();
        $district2 = District::skip(1)->first();
        $fleet1 = Fleet::where('district_id', $district1->id)->first();
        $fleet2 = Fleet::where('district_id', $district2->id)->first();

        // 2024 membership
        Member::create([
            'user_id' => $user->id,
            'district_id' => $district1->id,
            'fleet_id' => $fleet1->id,
            'year' => 2024,
        ]);

        // 2025 membership (different district/fleet)
        Member::create([
            'user_id' => $user->id,
            'district_id' => $district2->id,
            'fleet_id' => $fleet2->id,
            'year' => 2025,
        ]);

        $this->assertEquals(2, $user->members()->count());
        $this->assertEquals($district1->id, $user->membershipForYear(2024)->district_id);
        $this->assertEquals($district2->id, $user->membershipForYear(2025)->district_id);
    }

    public function test_user_cannot_have_duplicate_membership_for_same_year(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        $user = User::factory()->create();
        $district = District::first();
        $fleet = Fleet::first();

        // First membership for 2025
        Member::create([
            'user_id' => $user->id,
            'district_id' => $district->id,
            'fleet_id' => $fleet->id,
            'year' => 2025,
        ]);

        // Try to create duplicate membership for 2025
        Member::create([
            'user_id' => $user->id,
            'district_id' => $district->id,
            'fleet_id' => $fleet->id,
            'year' => 2025,
        ]);
    }

    public function test_user_membership_for_year_returns_correct_record(): void
    {
        $user = User::factory()->create();
        $district = District::first();
        $fleet = Fleet::first();

        Member::create([
            'user_id' => $user->id,
            'district_id' => $district->id,
            'fleet_id' => $fleet->id,
            'year' => 2025,
        ]);

        $membership = $user->membershipForYear(2025);

        $this->assertInstanceOf(Member::class, $membership);
        $this->assertEquals(2025, $membership->year);
        $this->assertEquals($district->id, $membership->district_id);
    }

    public function test_user_membership_for_year_returns_null_when_no_membership(): void
    {
        $user = User::factory()->create();

        $membership = $user->membershipForYear(2025);

        $this->assertNull($membership);
    }

    public function test_user_current_membership_returns_current_year_record(): void
    {
        $user = User::factory()->create();
        $district = District::first();
        $fleet = Fleet::first();
        $currentYear = now()->year;

        Member::create([
            'user_id' => $user->id,
            'district_id' => $district->id,
            'fleet_id' => $fleet->id,
            'year' => $currentYear,
        ]);

        $membership = $user->currentMembership();

        $this->assertInstanceOf(Member::class, $membership);
        $this->assertEquals($currentYear, $membership->year);
    }

    public function test_deleting_user_cascades_to_members(): void
    {
        $user = User::factory()->create();
        $district = District::first();
        $fleet = Fleet::first();

        Member::create([
            'user_id' => $user->id,
            'district_id' => $district->id,
            'fleet_id' => $fleet->id,
            'year' => 2025,
        ]);

        $memberId = $user->members()->first()->id;

        $user->delete();

        $this->assertDatabaseMissing('members', ['id' => $memberId]);
    }

    public function test_deleting_district_cascades_to_fleets_and_nullifies_member_records(): void
    {
        $user = User::factory()->create();
        $district = District::first();
        $fleet = Fleet::where('district_id', $district->id)->first();

        $member = Member::create([
            'user_id' => $user->id,
            'district_id' => $district->id,
            'fleet_id' => $fleet->id,
            'year' => 2025,
        ]);

        // Deleting district cascades to delete its fleets
        // This triggers onDelete('set null') on members table for both district_id and fleet_id
        $district->delete();

        $member->refresh();
        $this->assertNull($member->district_id);
        $this->assertNull($member->fleet_id); // Fleet was deleted, so also set to null
    }

    public function test_deleting_fleet_sets_fleet_id_to_null(): void
    {
        $user = User::factory()->create();
        $district = District::first();
        $fleet = Fleet::first();

        $member = Member::create([
            'user_id' => $user->id,
            'district_id' => $district->id,
            'fleet_id' => $fleet->id,
            'year' => 2025,
        ]);

        $fleet->delete();

        $member->refresh();
        $this->assertNull($member->fleet_id);
        $this->assertNotNull($member->district_id); // District should remain
    }

    public function test_user_can_have_different_memberships_for_different_years(): void
    {
        $user = User::factory()->create();

        // Get different districts and fleets for each year
        $california = District::where('name', 'California')->first();
        $centralAtlantic = District::where('name', 'Central Atlantic')->first();
        $canada = District::where('name', 'Central Canada')->first();

        $fleet194 = Fleet::where('district_id', $california->id)->first();
        $fleet1 = Fleet::where('district_id', $centralAtlantic->id)->first();
        $canadaFleet = Fleet::where('district_id', $canada->id)->first();

        // 2023: California / Fleet 194
        Member::create([
            'user_id' => $user->id,
            'district_id' => $california->id,
            'fleet_id' => $fleet194->id,
            'year' => 2023,
        ]);

        // 2024: Central Atlantic / Fleet 1
        Member::create([
            'user_id' => $user->id,
            'district_id' => $centralAtlantic->id,
            'fleet_id' => $fleet1->id,
            'year' => 2024,
        ]);

        // 2025: Central Canada / Canada Fleet
        Member::create([
            'user_id' => $user->id,
            'district_id' => $canada->id,
            'fleet_id' => $canadaFleet->id,
            'year' => 2025,
        ]);

        // Verify user has 3 different memberships
        $this->assertEquals(3, $user->members()->count());

        // Verify each year has different affiliations
        $membership2023 = $user->membershipForYear(2023);
        $membership2024 = $user->membershipForYear(2024);
        $membership2025 = $user->membershipForYear(2025);

        $this->assertEquals($california->id, $membership2023->district_id);
        $this->assertEquals($fleet194->id, $membership2023->fleet_id);

        $this->assertEquals($centralAtlantic->id, $membership2024->district_id);
        $this->assertEquals($fleet1->id, $membership2024->fleet_id);

        $this->assertEquals($canada->id, $membership2025->district_id);
        $this->assertEquals($canadaFleet->id, $membership2025->fleet_id);

        // Verify all three districts are different
        $this->assertNotEquals($membership2023->district_id, $membership2024->district_id);
        $this->assertNotEquals($membership2024->district_id, $membership2025->district_id);
        $this->assertNotEquals($membership2023->district_id, $membership2025->district_id);
    }

    public function test_membership_carries_forward_from_previous_year_if_not_updated(): void
    {
        $user = User::factory()->create();
        $district = District::first();
        $fleet = Fleet::first();

        // User signs up in 2024
        Member::create([
            'user_id' => $user->id,
            'district_id' => $district->id,
            'fleet_id' => $fleet->id,
            'year' => 2024,
        ]);

        // No membership created for 2025 (user didn't update)

        // When querying for 2025, should get 2024 membership
        $membership2025 = $user->membershipForYear(2025);

        $this->assertNotNull($membership2025);
        $this->assertEquals(2024, $membership2025->year);
        $this->assertEquals($district->id, $membership2025->district_id);
        $this->assertEquals($fleet->id, $membership2025->fleet_id);
    }

    public function test_membership_carries_forward_from_most_recent_previous_year(): void
    {
        $user = User::factory()->create();
        $district1 = District::skip(0)->first();
        $district2 = District::skip(1)->first();
        $fleet1 = Fleet::where('district_id', $district1->id)->first();
        $fleet2 = Fleet::where('district_id', $district2->id)->first();

        // 2023: District 1
        Member::create([
            'user_id' => $user->id,
            'district_id' => $district1->id,
            'fleet_id' => $fleet1->id,
            'year' => 2023,
        ]);

        // 2024: District 2 (updated)
        Member::create([
            'user_id' => $user->id,
            'district_id' => $district2->id,
            'fleet_id' => $fleet2->id,
            'year' => 2024,
        ]);

        // No membership for 2025 or 2026

        // 2025 should use 2024 membership (most recent)
        $membership2025 = $user->membershipForYear(2025);
        $this->assertEquals(2024, $membership2025->year);
        $this->assertEquals($district2->id, $membership2025->district_id);

        // 2026 should also use 2024 membership (most recent)
        $membership2026 = $user->membershipForYear(2026);
        $this->assertEquals(2024, $membership2026->year);
        $this->assertEquals($district2->id, $membership2026->district_id);
    }

    public function test_membership_returns_null_if_no_previous_years_exist(): void
    {
        $user = User::factory()->create();

        // No membership records at all

        // Should return null for any year
        $this->assertNull($user->membershipForYear(2025));
        $this->assertNull($user->membershipForYear(2024));
    }
}
