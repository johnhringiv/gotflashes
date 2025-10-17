<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Flash extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'activity_type',
        'event_type',
        'location',
        'sail_number',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'sail_number' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
