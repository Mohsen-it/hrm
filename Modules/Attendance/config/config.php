<?php

return [
    'name' => 'Attendance',

    /*
    |--------------------------------------------------------------------------
    | Calculation defaults
    |--------------------------------------------------------------------------
    */

    'super_admin_id' => env('USERS_SUPER_ADMIN_ID', 10000),

    // Grace minutes applied when an employee's first punch is slightly before
    // the scheduled start time; punches within this window never count as late.
    'default_grace_minutes' => (int) env('ATTENDANCE_DEFAULT_GRACE_MINUTES', 0),

    // Minimum minutes past the expected shift end before a punch is treated
    // as overtime (consumed by AttendanceSessionTypeService).
    'overtime_grace_minutes' => (int) env('ATTENDANCE_OVERTIME_GRACE_MINUTES', 60),

    // Detection window (in minutes) for "missing check-out" anomalies.
    'missing_checkout_minutes' => (int) env('ATTENDANCE_MISSING_CHECKOUT_MINUTES', 60),

    // Default chunk size used by the auto-calculation service when iterating
    // users or date ranges to avoid unbounded memory usage.
    'chunk_size' => (int) env('ATTENDANCE_CHUNK_SIZE', 200),

    // Status codes used consistently across the attendance domain.
    'statuses' => [
        'present' => 'present',
        'absent' => 'absent',
        'late' => 'late',
        'early_leave' => 'early_leave',
        'missing_punch' => 'missing_punch',
        'holiday' => 'holiday',
        'vacation' => 'vacation',
        'weekend' => 'weekend',
        'unassigned' => 'unassigned',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */

    'cache' => [
        // Default TTL (seconds) for attendance cache entries.
        'ttl' => (int) env('ATTENDANCE_CACHE_TTL', 300),

        // Per-key TTL overrides (substring match => seconds).
        'ttl_overrides' => [
            'daily_kpis' => (int) env('ATTENDANCE_CACHE_TTL_DAILY', 60),
            'live_sessions' => (int) env('ATTENDANCE_CACHE_TTL_LIVE', 30),
            'anomalies' => (int) env('ATTENDANCE_CACHE_TTL_ANOMALIES', 120),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    'notifications' => [
        // Role whose members receive broadcast / mass notifications.
        'admin_role' => env('ATTENDANCE_ADMIN_ROLE', 'admin'),

        // Channels the generic notification wrapper will route through.
        'channels' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('ATTENDANCE_NOTIFICATION_CHANNELS', 'mail,database'))
        ))),

        // Mass-lateness / mass-absence ratio thresholds.
        'mass_lateness_ratio' => (float) env('ATTENDANCE_MASS_LATENESS_RATIO', 0.30),
        'mass_absence_ratio' => (float) env('ATTENDANCE_MASS_ABSENCE_RATIO', 0.25),
    ],
];
