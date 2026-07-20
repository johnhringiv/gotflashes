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
    | 2025: 696 distinct sailor-days across 127 sailors (season 2025-03-17 to
    | 2025-11-12). Source: 2025 season report (Report 12-10-25.xlsx).
    |
    */

    'historical_totals' => [
        2025 => 696,
    ],

];
