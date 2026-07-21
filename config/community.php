<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pre-launch historical totals
    |--------------------------------------------------------------------------
    |
    | The app launched in 2026 (see app.start_year), so it has no per-day data
    | for prior seasons. These community qualifying-day totals come from the old
    | manual process and are used for the prior-year benchmark on the /stats
    | hero and to inform the annual goal. They are NOT selectable years.
    |
    | 2025: 1550 logged sailor-days across 127 sailors (season 2025-02-13 to
    | 2025-11-09). Source: 2025 season report (Report 12-10-25.xlsx) — one row per
    | logged activity, crediting the sailor who sailed (not the submitting account).
    | Every row is counted: last year's collection was messy but the rows are not
    | true same-day duplicates, so they are NOT deduplicated. No activity-type split
    | in the old data, so all count as sailing days.
    | (The prior 696 came from collapsing rows by submission timestamp.)
    |
    */

    'historical_totals' => [
        2025 => 1550,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default community goal
    |--------------------------------------------------------------------------
    |
    | Fallback annual qualifying-days goal shown on /stats when no year-specific
    | goal has been set in the settings table, so a fresh deploy shows a target
    | out of the box. A site admin can override per year at /admin/settings.
    |
    */

    'default_goal' => 2500,

];
