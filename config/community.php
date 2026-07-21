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
    | 2025: 1431 distinct sailor-days across 127 sailors (season 2025-02-13 to
    | 2025-11-09). Source: 2025 season report (Report 12-10-25.xlsx), counted as
    | distinct (credited sailor, activity date) pairs from the per-activity rows —
    | the report credits the sailor who sailed, not the submitting account. The
    | activity date is "Date 2"; a March 17–26 batch (140 rows) predates that
    | field, so those fall back to the submission timestamp. No activity-type
    | split in the old data, so all count as sailing days.
    | (The prior 696 came from using the submission timestamp for every row,
    | which collapsed batch-logged days.)
    |
    */

    'historical_totals' => [
        2025 => 1431,
    ],

];
