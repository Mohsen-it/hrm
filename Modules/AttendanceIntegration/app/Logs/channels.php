<?php

return [
    'attendance_integration' => [
        'driver' => 'daily',
        'path' => storage_path('logs/attendance-integration.log'),
        'level' => env('ATTENDANCE_INTEGRATION_LOG_LEVEL', 'debug'),
        'days' => 30,
    ],

    'attendance_push' => [
        'driver' => 'daily',
        'path' => storage_path('logs/attendance-push.log'),
        'level' => env('ATTENDANCE_PUSH_LOG_LEVEL', 'debug'),
        'days' => 14,
    ],

    'attendance_sync' => [
        'driver' => 'daily',
        'path' => storage_path('logs/attendance-sync.log'),
        'level' => env('ATTENDANCE_SYNC_LOG_LEVEL', 'debug'),
        'days' => 14,
    ],
];
