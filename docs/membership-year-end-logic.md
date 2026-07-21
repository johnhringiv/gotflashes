# Year-Specific Membership Logic

## Overview

The G.O.T. Flashes application tracks district and fleet memberships **per calendar year**.
Each year a user has activity is associated with the district/fleet they were affiliated with
**for that year**, so historical leaderboards and exports remain accurate even when a user
changes affiliation over time.

Affiliations are resolved with **query-time carry-forward**: if a user has no membership row
for a given year, the system uses their most recent membership from a prior year. There is no
pre-computed year-end snapshot — affiliations are resolved when queries run.

## Business Rules

### 1. Per-Year Membership Model

**Core Principle:** A user's flashes for a given year are credited to the district/fleet on
their membership record for that year. If no record exists for that year, the most recent
prior-year membership is carried forward.

**Rationale:**
- Allows users to change affiliations over time while preserving historical accuracy
- Provides a fair, consistent way to aggregate leaderboard standings per year
- Avoids a maintenance job: new years "just work" by carrying forward the last known affiliation
- Prevents gaming the system by retroactively switching affiliations on prior years

### 2. Membership Records

The `members` table tracks per-year affiliations:

```
members
  - user_id (foreign key to users)
  - district_id (foreign key to districts, NOT NULL)
  - fleet_id (foreign key to fleets, NOT NULL)
  - year (integer)
  - unique constraint on (user_id, year)
```

**Key Constraints:**
- One membership record per user per year
- `district_id` and `fleet_id` are NOT NULL — "Unaffiliated/None" is a real
  district row and a real fleet row (fleet_number 0, always selectable
  alongside any district; both excluded from the grouped leaderboards).
  See the `make_none_a_real_district_and_fleet` migration.
- Cascade delete when the user is deleted
- Deleting a district/fleet that still has member records is blocked

### 3. When Membership Records Are Created or Updated

**At Registration:**
- A membership record is created for the **current year** only
  (`RegistrationForm`, via `UserDataService::buildMemberData(...)`)
- Uses the `district_id` / `fleet_id` selected during registration
- If the user selects "Unaffiliated/None", the sentinel None district/fleet ids are stored

**On Profile Update:**
- Updating district/fleet in the profile writes to the **current year's** membership row
  (created if absent), leaving prior years untouched (historical preservation)

**Future years (carry-forward):**
- Membership rows are **not** pre-created for future years (there is no scheduled snapshot job)
- When a query targets a year the user has no row for, the most recent membership with
  `year <= target` is used. See [§6 Carry-Forward Resolution](#6-carry-forward-resolution).

### 4. Leaderboard Calculations

All three leaderboards resolve each user's affiliation for the selected year via the
carry-forward subquery (`Leaderboard.php`), then aggregate:

- **Sailor Leaderboard:** flash counts from `flashes` (filtered by year), district/fleet from
  the carried-forward membership for that year.
- **Fleet Leaderboard:** aggregates sailors' qualifying flashes grouped by their carried-forward
  `fleet_id` for the year; unaffiliated users are excluded.
- **District Leaderboard:** same, grouped by carried-forward `district_id`.

### 5. Unaffiliated Users

Users whose resolved membership points at the None district/fleet rows:
- Have their flashes count toward their personal totals
- Do **not** contribute to district or fleet leaderboard totals
- Still appear on the sailor leaderboard

### 6. Carry-Forward Resolution

When no membership row exists for the exact target year, the most recent membership with
`year <= target` is used. This is implemented in two places:

**Leaderboard (SQL subquery)** — `app/Livewire/Leaderboard.php`:
```php
// Most recent membership <= target year, per user
$mostRecentMembership = DB::table('members as m1')
    ->select('m1.*')
    ->joinSub(
        DB::table('members')
            ->select('user_id', DB::raw('MAX(year) as max_year'))
            ->where('year', '<=', $year)
            ->groupBy('user_id'),
        'm2',
        fn ($join) => $join
            ->on('m1.user_id', '=', 'm2.user_id')
            ->on('m1.year', '=', 'm2.max_year'),
    );
```

**User model (PHP)** — `app/Models/User.php`:
```php
public function membershipForYear(int $year): ?Member
{
    // Exact match first, then carry forward from the most recent prior year
    return $this->members->firstWhere('year', $year)
        ?? $this->members->where('year', '<', $year)->sortByDesc('year')->first();
}

public function currentMembership(): ?Member
{
    return $this->membershipForYear(now()->year);
}
```

> **Note:** `ExportController` currently joins memberships on an **exact** `members.year = flash year`
> match (no carry-forward), so an exported flash in a year with no membership row shows as
> unaffiliated. This differs from the leaderboard/`User` carry-forward behavior and is tracked
> as a code inconsistency to reconcile.

### 7. Edge Cases

**New users mid-year:**
- Membership row created for the current year; appear on current-year leaderboards immediately.

**User changes affiliation:**
- The current year's membership row is updated; all of that year's flashes are credited to the
  new district/fleet. Prior years remain unchanged.

**User queried for a year with no membership row:**
- Carry-forward uses the most recent prior membership (leaderboards/`User`); export treats it
  as unaffiliated (see note above).

**User deletes account:**
- Membership records are cascade-deleted; historical leaderboard data no longer includes them.

**District/Fleet deleted:**
- Blocked while member records still reference the row (`district_id`/`fleet_id`
  are NOT NULL); reassign the members first. Reference data is effectively
  append-only in practice.

### 8. Implementation Notes & Possible Future Work

- **User model methods:** `members()` (HasMany), `membershipForYear(int $year)`,
  `currentMembership()` — see [§6](#6-carry-forward-resolution).
- **Reconcile export resolution:** align `ExportController` with the carry-forward model used
  elsewhere (see note in §6).
- **Year-end snapshot job (not implemented):** a scheduled task could materialize next-year
  membership rows on Jan 1 instead of relying on query-time carry-forward. Not currently needed —
  carry-forward already produces correct results — but listed for awareness.

## Query Examples

### Sailor leaderboard for a year (carry-forward membership)
```php
$members = $mostRecentMembership; // subquery from §6 (year <= $year)

$sailors = DB::table('users')
    ->joinSub($members, 'members', 'members.user_id', '=', 'users.id')
    ->where('members.year', '<=', $year)
    // ... join per-year flash counts, order by qualifying flashes ...
    ->paginate(15);
```

### A user's affiliation history
```php
$history = $user->members()
    ->with(['district', 'fleet'])
    ->orderBy('year', 'desc')
    ->get();
```

## Testing Considerations

1. Verify a membership record is created at registration (current year only).
2. Profile affiliation changes update the current year's row, not prior years.
3. Leaderboards resolve the correct membership per year, including carry-forward when no row
   exists for the target year.
4. Unaffiliated users don't appear in fleet/district totals.
5. Multi-year scenarios with different affiliations per year resolve correctly.