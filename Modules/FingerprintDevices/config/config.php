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
    | Default is `both`: identity flows instantly over ADMS while privilege
    | (which this fleet's firmware only honors over TCP) is enforced by the
    | bridge. Bridge jobs retry with backoff, so edits made while the
    | terminals are unreachable still converge once they are reachable.
    |
    */
    'push_user_via' => env('PUSH_USER_VIA', 'both'),

    /*
    |--------------------------------------------------------------------------
    | Distribution safety (anti-flood)
    |--------------------------------------------------------------------------
    |
    | device_writes_enabled: emergency kill-switch for BULK device writes
    |   (Distribute* jobs + bulk console commands). When false, distribution
    |   jobs self-delete without touching devices and bulk commands abort.
    |   The event-driven single-employee path (EmployeeAdmsObserver) is NEVER
    |   gated by this flag, and attendance INTAKE is never affected.
    |
    | distribution_max_age_hours: queued distribution jobs older than this
    |   are obsolete (device state moved on) and self-delete without writing.
    |
    */
    'device_writes_enabled' => env('DEVICE_WRITES_ENABLED', true),

    'distribution_max_age_hours' => (int) env('DISTRIBUTION_MAX_AGE_HOURS', 72),

    /*
    |--------------------------------------------------------------------------
    | Fingerprint distribution channels
    |--------------------------------------------------------------------------
    |
    | distribute_fingerprint_via_bridge: direct-TCP propagation
    | (DistributeFingerprintJob via the pyzk bridge). This is the PROVEN
    | channel on this fleet (fleet-wide spread observed 2026-09-02) and is
    | enabled by default. Requires TCP 4370 from this server to each
    | terminal; when the network blocks it, jobs fail fast (short
    | timeouts) into failed_jobs instead of clogging workers.
    |
    | distribute_fingerprint_via_adms: queue fp_template rows into
    | device_commands (same pattern as face templates). Enabled: fingerprints
    | travel over the classic FINGERTMP table (``DATA UPDATE FINGERTMP``),
    | which this fleet honors — the earlier unified-``biodata`` variants
    | were ACKed but never stored, so only the FINGERTMP body format may
    | be used (see DeviceCommandService::queueFingerprintTemplate).
    |
    */
    'distribute_fingerprint_via_bridge' => env('DISTRIBUTE_FINGERPRINT_VIA_BRIDGE', true),

    'distribute_fingerprint_via_adms' => env('DISTRIBUTE_FINGERPRINT_VIA_ADMS', true),

    /*
    |--------------------------------------------------------------------------
    | Destructive-command refusal + delete circuit breaker
    |--------------------------------------------------------------------------
    |
    | allow_dangerous_commands: restart / clear_users / clear_logs can wipe
    |   or reboot terminals. Nothing in the system uses them; queueing them
    |   is refused outright unless this is explicitly enabled.
    |
    | delete_breaker_threshold / delete_breaker_window_minutes: deleting more
    |   than this many employees within the window trips the breaker — further
    |   DELETEs are held (logged + replayable) instead of hitting devices.
    |   Single deletions are never affected.
    |
    */
    'allow_dangerous_commands' => env('DEVICE_ALLOW_DANGEROUS_COMMANDS', false),

    'delete_breaker_threshold' => (int) env('ADMS_DELETE_BREAKER_THRESHOLD', 5),

    'delete_breaker_window_minutes' => (int) env('ADMS_DELETE_BREAKER_WINDOW_MINUTES', 10),
];
