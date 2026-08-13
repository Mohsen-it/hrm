<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Activity idle gap
    |--------------------------------------------------------------------------
    |
    | How long (in minutes) a user may stay idle before their working
    | session is considered finished. A gap of this length or longer between
    | two recorded actions stops the work-time counter, so a short break
    | (e.g. leaving the computer for two minutes) is not counted as working
    | time. Smaller values produce stricter, more precise totals.
    */
    'idle_gap_minutes' => 2,
];
