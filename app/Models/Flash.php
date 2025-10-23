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

    /**
     * Determine if this flash can be edited/deleted.
     * A flash is editable if its date falls within the allowed date range (minDate to maxDate).
     *
     * @param  \Carbon\Carbon  $minDate  The minimum allowed date (from grace period logic)
     * @param  \Carbon\Carbon  $maxDate  The maximum allowed date (typically now + 1 day)
     */
    public function isEditable(\Carbon\Carbon $minDate, \Carbon\Carbon $maxDate): bool
    {
        return $this->date >= $minDate && $this->date <= $maxDate;
    }
}
