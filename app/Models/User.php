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
        'district',
        'fleet_number',
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
}
