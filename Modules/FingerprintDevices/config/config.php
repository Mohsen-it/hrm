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

    /*
    |--------------------------------------------------------------------------
    | ADMS Unification — Pull & Push Channels
    |--------------------------------------------------------------------------
    |
    | pull_fingerprints_via: how pending biometrics are obtained.
    |   - adms   : only via ADMS push (BiodataParser → user_fingerprints). Bridge
    |              pull (getAllTemplates) is skipped unless explicitly forced.
    |   - bridge : classic TCP pull via pyzk.
    |   - both   : try ADMS first, bridge as fallback (legacy).
    |
    | push_user_via: how new users are sent to devices.
    |   - adms   : queue DATA UPDATE USERINFO via device_commands (ADMS poll).
    |   - bridge : direct TCP via pyzk bridge.
    |   - both   : ADMS first + bridge verification (highest reliability).
    |
    | These defaults enforce the user's request: ADMS everywhere.
    |
    */
    'pull_fingerprints_via' => env('PULL_FINGERPRINTS_VIA', 'adms'),

    'push_user_via' => env('PUSH_USER_VIA', 'adms'),
];
