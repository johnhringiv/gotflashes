<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Key-value application settings (e.g. the annual community sailing goal,
 * stored under "community_goal_{year}").
 */
class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Get a setting value by key, or the default if not set.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    /**
     * Set (create or update) a setting value. A null value clears the setting.
     */
    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
