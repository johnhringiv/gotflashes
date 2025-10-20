<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Member extends Model
{
    protected $fillable = [
        'user_id',
        'district_id',
        'fleet_id',
        'year',
    ];

    /**
     * Get the user that this membership belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the district for this membership.
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /**
     * Get the fleet for this membership.
     */
    public function fleet(): BelongsTo
    {
        return $this->belongsTo(Fleet::class);
    }
}
