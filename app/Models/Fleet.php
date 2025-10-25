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
