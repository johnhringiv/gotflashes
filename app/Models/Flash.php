<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Flash extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'activity_type',
        'event_type',
        'yacht_club',
        'fleet_number',
        'location',
        'sail_number',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'fleet_number' => 'integer',
        'sail_number' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
