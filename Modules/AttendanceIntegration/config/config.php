<?php

return [
    'name' => 'AttendanceIntegration',

    'default_driver' => env('ATTENDANCE_INTEGRATION_DRIVER', 'zkteco'),

    'drivers' => [
        'zkteco' => [
            'bridge_url' => env('ZKTECO_BRIDGE_URL', 'http://127.0.0.1:5000'),
            'bridge_timeout' => (int) env('ZKTECO_BRIDGE_TIMEOUT', 30),
        ],
        'suprema' => [
            'api_url' => env('SUPREMA_API_URL', ''),
            'api_key' => env('SUPREMA_API_KEY', ''),
        ],
        'hikvision' => [
            'api_url' => env('HIKVISION_API_URL', 'http://127.0.0.1:5001'),
            'bridge_timeout' => (int) env('HIKVISION_BRIDGE_TIMEOUT', 30),
            'username' => env('HIKVISION_USERNAME', 'admin'),
            'password' => env('HIKVISION_PASSWORD', ''),
        ],
    ],

    'push' => [
        'rate_limit' => (int) env('ATTENDANCE_PUSH_RATE_LIMIT', 60),
        'rate_decay' => (int) env('ATTENDANCE_PUSH_RATE_DECAY', 1),
        'duplicate_window_seconds' => (int) env('ATTENDANCE_DUPLICATE_WINDOW_SECONDS', 30),
        'max_retry_attempts' => (int) env('ATTENDANCE_PUSH_MAX_RETRIES', 3),
    ],

    'live_feed' => [
        'max_items' => (int) env('ATTENDANCE_LIVE_FEED_MAX', 100),
        'cache_ttl_hours' => (int) env('ATTENDANCE_LIVE_FEED_TTL', 6),
    ],
];
