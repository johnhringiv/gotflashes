<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    protected $fillable = [
        'name',
    ];

    /**
     * Get the fleets for the district.
     */
    public function fleets(): HasMany
    {
        return $this->hasMany(Fleet::class);
    }

    /**
     * Get the users in the district.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the membership records for the district.
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }
}
