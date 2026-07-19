<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Fleet extends Model
{
    use HasFactory;

    protected $fillable = [
        'district_id',
        'fleet_number',
        'fleet_name',
    ];

    /**
     * Fleet number of the sentinel "None" fleet (real fleets are numbered
     * from 1). A real row so member affiliations are never null; selectable
     * alongside ANY district, and excluded from the fleet leaderboard.
     */
    public const NONE_NUMBER = 0;

    public static function noneId(): int
    {
        // once(): the id is fixed after the migration runs, and rules()/API
        // endpoints call this repeatedly per request. Laravel flushes the
        // memo between tests, so RefreshDatabase stays safe.
        return once(fn (): int => (int) static::query()->where('fleet_number', self::NONE_NUMBER)->value('id'));
    }

    /**
     * Get the district that owns the fleet.
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /**
     * Get the users in the fleet (through members table).
     */
    public function users(): HasManyThrough
    {
        return $this->hasManyThrough(
            User::class,
            Member::class,
            'fleet_id',  // Foreign key on members table
            'id',        // Foreign key on users table
            'id',        // Local key on fleets table
            'user_id'    // Local key on members table
        );
    }

    /**
     * Get the membership records for the fleet.
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }
}
