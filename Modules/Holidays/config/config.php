<?php

return [
    'name' => 'Holidays',

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */

    // Holidays inside this window are exposed via the `holidays.dates`
    // helper. The integration service consults this list when patching
    // daily attendance summaries.
    'lookback_days' => (int) env('HOLIDAYS_LOOKBACK_DAYS', 7),
    'lookahead_days' => (int) env('HOLIDAYS_LOOKAHEAD_DAYS', 365),

    // Code used for the generic "public" holiday category.
    'public_code' => env('HOLIDAYS_PUBLIC_CODE', 'public'),
];
