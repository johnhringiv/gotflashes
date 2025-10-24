<?php

namespace App\Services;

use Carbon\Carbon;

class DateRangeService
{
    /**
     * Get the allowed date range based on grace period logic.
     *
     * January allows previous year entries (grace period),
     * February onward restricts to current year only.
     *
     * @return array{Carbon, Carbon} [$minDate, $maxDate]
     */
    public static function getAllowedDateRange(?Carbon $now = null): array
    {
        $now = $now ?? now();

        $minDate = $now->copy()->startOfYear();
        if ($now->month === 1) {
            // January: allow previous year entries (grace period)
            $minDate = $now->copy()->subYear()->startOfYear();
        }

        // Max date: today + 1 day for timezone tolerance
        $maxDate = $now->copy()->addDay();

        return [$minDate, $maxDate];
    }
}
