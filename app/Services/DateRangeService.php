<?php

namespace App\Services;

use Carbon\Carbon;

class DateRangeService
{
    /**
     * Get the allowed date range based on grace period logic.
     *
     * January allows previous year entries (grace period),
     * but ONLY if the current year is after the app's START_YEAR.
     * February onward restricts to current year only.
     *
     * Examples:
     * - January 2026 (START_YEAR=2026): Min date = Jan 1, 2026 (no grace period)
     * - January 2027 (START_YEAR=2026): Min date = Jan 1, 2026 (grace period active)
     * - January 2028 (START_YEAR=2026): Min date = Jan 1, 2027 (grace period active)
     * - February 2027 (START_YEAR=2026): Min date = Jan 1, 2027 (no grace period)
     *
     * @return array{Carbon, Carbon} [$minDate, $maxDate]
     */
    public static function getAllowedDateRange(?Carbon $now = null): array
    {
        $now = $now ?? now();
        $startYear = (int) config('app.start_year', 2026);

        $minDate = $now->copy()->startOfYear();

        // Grace period logic: Only allow previous year in January if we're past the start year
        if ($now->month === 1 && $now->year > $startYear) {
            // January grace period: allow previous year entries
            $minDate = $now->copy()->subYear()->startOfYear();
        }

        // Max date: today + 1 day for timezone tolerance
        $maxDate = $now->copy()->addDay();

        return [$minDate, $maxDate];
    }
}
