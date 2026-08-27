<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Modules\FingerprintDevices\Services\DeviceCommandService;

echo "=== DEVICE STATUS ===\n";
$devices = DB::table('fingerprint_devices')
    ->join('fingerprint_device_types', 'fingerprint_devices.device_type_id', '=', 'fingerprint_device_types.id')
    ->select('fingerprint_devices.*', 'fingerprint_device_types.name as type_name')
    ->get();

foreach ($devices as $d) {
    $push = $d->is_push_enabled ? 'ON' : 'OFF';
    echo "  [{$d->id}] {$d->name} | SN: {$d->serial_number} | IP: {$d->ip_address} | Status: {$d->status} | Push: {$push}\n";
}

echo "\n=== COMMAND QUEUE SUMMARY ===\n";
$stats = DB::table('device_commands')
    ->select('device_id', 'status', 'command_type', DB::raw('count(*) as cnt'))
    ->groupBy('device_id', 'status', 'command_type')
    ->orderBy('device_id')
    ->orderBy('status')
    ->get();

$currentDevice = null;
foreach ($stats as $s) {
    if ($s->device_id !== $currentDevice) {
        $currentDevice = $s->device_id;
        $devName = DB::table('fingerprint_devices')->where('id', $currentDevice)->value('name') ?? '?';
        echo "\n  Device [{$currentDevice}] {$devName}:\n";
    }
    echo "    {$s->status} {$s->command_type}: {$s->cnt}\n";
}

echo "\n=== FAILED FACE COMMANDS (sample) ===\n";
$failed = DB::table('device_commands')
    ->where('command_type', 'face_template')
    ->where('status', 'failed')
    ->orderByDesc('updated_at')
    ->limit(5)
    ->get(['id', 'device_id', 'error_message', 'retry_count', 'max_retries', 'updated_at']);

if ($failed->isEmpty()) {
    echo "  None! All face commands succeeded.\n";
} else {
    foreach ($failed as $f) {
        $devName = DB::table('fingerprint_devices')->where('id', $f->device_id)->value('name') ?? '?';
        echo "  CMD#{$f->id} | {$devName} | retries: {$f->retry_count}/{$f->max_retries} | {$f->error_message} | {$f->updated_at}\n";
    }
}

echo "\n=== FACE TEMPLATE COVERAGE ===\n";
$coverage = DB::table('user_fingerprints')
    ->where('template_type', 'face')
    ->whereNotNull('template_data')
    ->where('template_data', '!=', '')
    ->select('device_id', DB::raw('count(distinct user_id) as user_count'))
    ->groupBy('device_id')
    ->get();

foreach ($coverage as $c) {
    $devName = DB::table('fingerprint_devices')->where('id', $c->device_id)->value('name') ?? '?';
    echo "  {$devName}: {$c->user_count} users with face data\n";
}

echo "\n=== RE-QUEUING FAILED FACE COMMANDS ===\n";
$result = DeviceCommandService::class;
$service = app($result);
$retryResult = $service->retryFailedFaceCommands(limit: 500);
echo "  Requeued: {$retryResult['requeued']} | Total failed: {$retryResult['total_failed']}\n";

echo "\n=== DISTRIBUTING MISSING FACE SETS (dry-run) ===\n";
$exitCode = Artisan::call('fingerprints:distribute-missing-faces', ['--dry-run' => true]);
echo Artisan::output();
