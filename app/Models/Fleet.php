<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fleet extends Model
{
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
     * Get the users in the fleet.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the membership records for the fleet.
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }
}
