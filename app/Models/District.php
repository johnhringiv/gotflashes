<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class District extends Model
{
    use HasFactory;

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
     * Get the users in the district (through members table).
     */
    public function users(): HasManyThrough
    {
        return $this->hasManyThrough(
            User::class,
            Member::class,
            'district_id', // Foreign key on members table
            'id',          // Foreign key on users table
            'id',          // Local key on districts table
            'user_id'      // Local key on members table
        );
    }

    /**
     * Get the membership records for the district.
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }
}
