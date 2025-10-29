<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AwardFulfillment extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'year',
        'award_tier',
        'status',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [];
    }

    /**
     * Get the user that owns the award fulfillment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
