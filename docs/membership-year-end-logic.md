# Membership Year-End Logic

## Overview

The GOT-FLASHES application tracks district and fleet memberships using a **year-end snapshot** approach. This means users are considered members of the district and fleet they are affiliated with at the end of each calendar year, regardless of any mid-year changes.

## Business Rules

### 1. Year-End Snapshot Model

**Core Principle:** Users are credited to the district and fleet they belong to on December 31st of each year for leaderboard purposes.

**Rationale:**
- Allows users to change affiliations mid-year (e.g., moving to a new district)
- Provides a fair and consistent way to aggregate leaderboard standings
- Simplifies multi-year leaderboard calculations
- Prevents gaming the system by switching affiliations to top-performing groups

### 2. Membership Records

The `members` table tracks year-end affiliations:

```
members
  - user_id (foreign key to users)
  - district_id (foreign key to districts, nullable)
  - fleet_id (foreign key to fleets, nullable)
  - year (integer)
  - unique constraint on (user_id, year)
```

**Key Constraints:**
- One membership record per user per year
- Both district_id and fleet_id are nullable (for unaffiliated users)
- Cascade delete when user is deleted
- Set null when district/fleet is deleted

### 3. When Membership Records Are Created

**At Registration:**
- When a user registers, a membership record is created for the current year
- Uses the district_id and fleet_id selected during registration
- If user selects "Unaffiliated/None", both fields are set to null

**Year-End Snapshot (December 31st):**
- For existing users, a membership record for the next year should be created based on their current affiliation
- This snapshot happens automatically (future implementation: scheduled job)
- Captures the user's district/fleet status as of December 31st at 11:59 PM

**Mid-Year Updates:**
- Users can update their profile to change district/fleet affiliation
- Changes are reflected immediately in their current year membership record
- Previous years' membership records remain unchanged (historical preservation)

### 4. Leaderboard Calculations

**Sailor Leaderboard:**
- Uses flash counts from `flashes` table (filtered by year)
- Uses district/fleet from `members` table (filtered by year)
- Example: User's 2025 flashes are credited to their 2025 membership district/fleet

**Fleet Leaderboard:**
- Aggregates all sailors' flashes by their year-end fleet affiliation
- Groups by `members.fleet_id` for the specified year
- Shows total flashes for all members of each fleet

**District Leaderboard:**
- Aggregates all sailors' flashes by their year-end district affiliation
- Groups by `members.district_id` for the specified year
- Shows total flashes for all members of each district

### 5. Unaffiliated Users

Users who select "Unaffiliated/None" or "None":
- Have `district_id` and/or `fleet_id` set to null in members table
- Their flashes count toward their personal totals
- Do **not** contribute to district or fleet leaderboard totals
- Still appear on the sailor leaderboard

### 6. Edge Cases

**New Users Mid-Year:**
- Create membership record for current year with selected affiliations
- Appear on current year leaderboards immediately

**User Changes Affiliation Mid-Year:**
- Update current year membership record with new district/fleet
- All flashes for that year are credited to the new affiliation
- Previous years' affiliations remain unchanged

**User Deletes Account:**
- Membership records are cascade deleted
- Historical leaderboard data no longer includes their contributions

**District/Fleet Deleted:**
- Membership records set district_id/fleet_id to null (set null on delete)
- Affected users become "unaffiliated" for that year
- Historical data preserved but not attributed to deleted district/fleet

### 7. Implementation Details

**User Model Methods:**
```php
// Get membership for specific year
$user->membershipForYear(2025); // Returns Member|null

// Get current year membership
$user->currentMembership(); // Returns Member|null

// Access district through membership
$membership = $user->currentMembership();
$district = $membership?->district;
$fleet = $membership?->fleet;
```

**Leaderboard Queries:**
```php
// Join members table to get year-end affiliations
User::join('members', 'users.id', '=', 'members.user_id')
    ->where('members.year', 2025)
    ->where('members.district_id', $districtId)
    ->withFlashesCount(2025)
    ->get();
```

### 8. Future Considerations

**Year-End Snapshot Job:**
- Scheduled task runs on December 31st at 11:59 PM
- Creates membership records for next year based on current year's final state
- Ensures continuity for users who don't update their profile

**Grace Period:**
- Allow users to update previous year's affiliation until January 31st
- Useful for corrections or users who moved late in December
- After grace period, previous years become read-only

**Historical Views:**
- Users can view their affiliation history
- Show which district/fleet they were in for each year
- Display year-by-year flash contributions

**Award Certificates:**
- Use membership records to print correct district/fleet on certificates
- Ensures accuracy even when viewing historical awards

## Query Examples

### Current Year Sailor Leaderboard
```php
$sailors = User::select('users.*')
    ->join('members', 'users.id', '=', 'members.user_id')
    ->where('members.year', now()->year)
    ->withFlashesCount(now()->year)
    ->orderBy('flashes_count', 'desc')
    ->paginate(15);
```

### Fleet Leaderboard for Specific Year
```php
$fleets = Fleet::select('fleets.*')
    ->selectRaw('COUNT(DISTINCT members.user_id) as member_count')
    ->selectRaw('SUM(qualifying_flashes) as total_flashes')
    ->join('members', 'fleets.id', '=', 'members.fleet_id')
    ->where('members.year', $year)
    ->groupBy('fleets.id')
    ->orderBy('total_flashes', 'desc')
    ->get();
```

### User's Affiliation History
```php
$history = $user->members()
    ->with(['district', 'fleet'])
    ->orderBy('year', 'desc')
    ->get();
```

## Testing Considerations

When testing the membership system:
1. Verify membership record created at registration
2. Test mid-year affiliation changes update current year only
3. Confirm leaderboards use correct year's membership data
4. Validate unaffiliated users don't appear in fleet/district totals
5. Test multi-year scenarios with different affiliations per year