<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'date_of_birth',
        'gender',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'zip_code',
        'country',
        'yacht_club',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
        ];
    }

    /**
     * Get the user's full name.
     */
    public function getNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function flashes(): HasMany
    {
        return $this->hasMany(Flash::class);
    }

    /**
     * Get the user's membership records (year-end affiliations).
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    /**
     * Get the user's award fulfillment records.
     */
    public function awardFulfillments(): HasMany
    {
        return $this->hasMany(AwardFulfillment::class);
    }

    /**
     * Get the user's membership for a specific year.
     * If no membership exists for the specified year, carries forward from the most recent previous year.
     *
     * Example: User signs up in 2024, doesn't update in 2025 -> 2025 uses 2024 membership.
     */
    public function membershipForYear(int $year): ?Member
    {
        // First, try to find exact membership for this year
        /** @var Member|null $membership */
        $membership = $this->members()->where('year', $year)->first();

        if ($membership) {
            return $membership;
        }

        // If not found, get the most recent membership before this year (carry-forward)
        /** @var Member|null */
        return $this->members()
            ->where('year', '<', $year)
            ->orderBy('year', 'desc')
            ->first();
    }

    /**
     * Get the user's current year membership.
     */
    public function currentMembership(): ?Member
    {
        return $this->membershipForYear(now()->year);
    }

    /**
     * Calculate qualifying flashes for a specific year.
     * Qualifying flashes = sailing days (unlimited) + non-sailing days (capped at 5).
     */
    public function qualifyingFlashesForYear(int $year): int
    {
        $sailingCount = $this->flashes()
            ->whereYear('date', $year)
            ->where('activity_type', 'sailing')
            ->count();

        $nonSailingCount = $this->flashes()
            ->whereYear('date', $year)
            ->whereIn('activity_type', ['maintenance', 'race_committee'])
            ->count();

        return $sailingCount + min($nonSailingCount, 5);
    }

    /**
     * Get detailed flash statistics for a specific year.
     * Returns an object with sailing count, non-sailing count, and total.
     *
     * @return object{sailing: int, nonSailing: int, total: int}
     */
    public function flashStatsForYear(int $year): object
    {
        $sailingCount = $this->flashes()
            ->whereYear('date', $year)
            ->where('activity_type', 'sailing')
            ->count();

        $nonSailingCount = $this->flashes()
            ->whereYear('date', $year)
            ->whereIn('activity_type', ['maintenance', 'race_committee'])
            ->count();

        return (object) [
            'sailing' => $sailingCount,
            'nonSailing' => $nonSailingCount,
            'total' => $sailingCount + min($nonSailingCount, 5),
        ];
    }

    /**
     * Scope to add qualifying flashes count for a specific year.
     * This is used for leaderboard queries where we need to sort by flash count.
     * Also adds sailing_count and first_entry_date for tie-breaking.
     */
    public function scopeWithFlashesCount($query, $year = null)
    {
        $year = $year ?? now()->year;

        return $query
            ->selectSub(function ($subQuery) use ($year) {
                // Calculate total: sailing days (unlimited) + non-sailing days (capped at 5)
                // Non-sailing days = maintenance + race_committee activities
                // Uses MIN() to cap non-sailing days at maximum of 5 per year
                $subQuery->selectRaw('
                    (SELECT count(*) FROM flashes
                     WHERE users.id = flashes.user_id
                     AND strftime(\'%Y\', date) = ?
                     AND activity_type = \'sailing\')
                    +
                    MIN(
                        (SELECT count(*) FROM flashes
                         WHERE users.id = flashes.user_id
                         AND strftime(\'%Y\', date) = ?
                         AND activity_type IN (\'maintenance\', \'race_committee\')),
                        5
                    )
                ', [(string) $year, (string) $year]);
            }, 'flashes_count')
            ->selectSub(function ($subQuery) use ($year) {
                // Sailing count for tie-breaking
                $subQuery->selectRaw('
                    count(*) FROM flashes
                    WHERE users.id = flashes.user_id
                    AND strftime(\'%Y\', date) = ?
                    AND activity_type = \'sailing\'
                ', [(string) $year]);
            }, 'sailing_count')
            ->selectSub(function ($subQuery) use ($year) {
                // First entry date (created_at) for final tie-breaking
                $subQuery->selectRaw('
                    MIN(created_at) FROM flashes
                    WHERE users.id = flashes.user_id
                    AND strftime(\'%Y\', date) = ?
                ', [(string) $year]);
            }, 'first_entry_date');
    }

    /**
     * Get the date when user first reached a specific award threshold.
     * Used in admin dashboard to show when user earned an award.
     *
     * @param  int  $year  The year to check
     * @param  int  $tier  The award tier (10, 25, or 50)
     * @return \Carbon\Carbon|null The date threshold was reached, or null if not reached
     */
    public function thresholdDateForYear(int $year, int $tier): ?\Carbon\Carbon
    {
        // Get all flashes for the year, ordered by date ASC
        $flashes = $this->flashes()
            ->whereYear('date', $year)
            ->orderBy('date', 'asc')
            ->get();

        $sailingCount = 0;
        $nonSailingCount = 0;

        /** @var Flash $flash */
        foreach ($flashes as $flash) {
            if ($flash->activity_type === 'sailing') {
                $sailingCount++;
            } else {
                $nonSailingCount++;
            }

            // Apply 5 non-sailing day cap
            $cumulativeTotal = $sailingCount + min($nonSailingCount, 5);

            // Check if we've crossed the threshold
            if ($cumulativeTotal >= $tier) {
                return \Carbon\Carbon::parse($flash->date);
            }
        }

        return null; // Threshold not reached
    }
}
