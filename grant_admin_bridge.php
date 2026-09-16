<?php

/**
 * Bridge privilege grant — run AFTER turning the VPN off.
 *
 * Usage: php grant_admin_bridge.php [PIN] [privilege]
 *   PIN        employee code (default: 20591)
 *   privilege  0 = member, 14 = admin (default: 14)
 *
 * Flow: checks bridge health → probes TCP 4370 to every device →
 * dispatches one SyncUserToDeviceViaBridgeJob per device (pyzk set_user,
 * user record only — fingerprints/face/cards are never touched).
 */

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\FingerprintDevices\Jobs\SyncUserToDeviceViaBridgeJob;
use Modules\Users\Models\User;

$pin = (string) ($argv[1] ?? '20591');
$privilege = (int) ($argv[2] ?? 14);

if (! in_array($privilege, [0, 14], true)) {
    echo "ERROR: privilege must be 0 (member) or 14 (admin).\n";
    exit(1);
}

// ── Step 1: bridge health ─────────────────────────────────────────────
$bridgeUrl = rtrim((string) config('attendanceintegration.drivers.zkteco.bridge_url'), '/');
try {
    $health = Http::timeout(10)->get($bridgeUrl.'/health')->json();
} catch (Throwable $e) {
    echo 'ERROR: bridge unreachable at '.$bridgeUrl.' ('.$e->getMessage().").\n";
    echo "Start it first, then re-run this script.\n";
    exit(1);
}
echo 'bridge: '.($health['status'] ?? '?').' pyzk='.var_export($health['pyzk_available'] ?? null, true)."\n";
if (empty($health['pyzk_available'])) {
    echo "ERROR: pyzk library missing in bridge venv — cannot talk TCP to devices.\n";
    exit(1);
}

// ── Step 2: TCP 4370 probe per device ─────────────────────────────────
$devices = DB::table('fingerprint_devices')
    ->where('is_push_enabled', true)
    ->orderBy('id')
    ->get(['id', 'name', 'serial_number', 'ip_address', 'port']);

$reachable = [];
foreach ($devices as $d) {
    $fp = @fsockopen((string) $d->ip_address, (int) $d->port, $errno, $errstr, 4);
    $ok = $fp !== false;
    if ($fp) {
        fclose($fp);
    }
    echo ($ok ? '  [OK]   ' : '  [FAIL] ').$d->ip_address.':'.$d->port.' '.$d->name."\n";
    if ($ok) {
        $reachable[] = $d;
    }
}

if (empty($reachable)) {
    echo "ERROR: no device reachable on TCP 4370 — VPN still on or firewall closed. Aborting.\n";
    exit(1);
}

// ── Step 3: resolve employee ──────────────────────────────────────────
$user = User::where('employee_code', $pin)->first(['id', 'employee_code', 'full_name_ar', 'full_name_en', 'name']);
if (! $user) {
    echo "ERROR: no user with employee_code {$pin}.\n";
    exit(1);
}
$name = (string) ($user->full_name_ar ?: $user->full_name_en ?: $user->name ?: $pin);

// ── Step 4: dispatch bridge jobs ──────────────────────────────────────
foreach ($reachable as $d) {
    SyncUserToDeviceViaBridgeJob::dispatch((int) $d->id, $pin, $name, $privilege);
    echo "queued: device {$d->id} ({$d->serial_number}) pin={$pin} privilege={$privilege}\n";
}

echo "\nDone. Wait 2-3 minutes (queue workers pick the jobs up), then check PIN {$pin} on any device.\n";
echo 'Watch progress: most recent BRIDGE_USER_SYNC_JOB lines in storage/logs/laravel-'.date('Y-m-d').".log\n";
