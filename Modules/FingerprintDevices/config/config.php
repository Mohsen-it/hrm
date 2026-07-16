<?php

return [
    'name' => 'FingerprintDevices',

    /*
    |--------------------------------------------------------------------------
    | ZKTeco Bridge Service
    |--------------------------------------------------------------------------
    |
    | The Python Flask service that wraps the pyzk library and proxies
    | low-level ZKTeco device operations. The Laravel side talks to it
    | exclusively over HTTP.
    |
    */

    'zkteco_bridge_url' => env('ZKTECO_BRIDGE_URL', 'http://127.0.0.1:5000'),

    'zkteco_bridge_timeout' => (int) env('ZKTECO_BRIDGE_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Connection test rate limit
    |--------------------------------------------------------------------------
    |
    | Rate-limit (per-user) for the `test-connection` endpoint to avoid
    | flooding devices with TCP/UDP probes.
    |
    */

    'connection_rate_limit' => (int) env('ZKTECO_RATE_LIMIT', 10),

    'connection_rate_decay' => (int) env('ZKTECO_RATE_DECAY', 1),
];
