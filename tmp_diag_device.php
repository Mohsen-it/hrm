<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Services\FingerprintDeviceService;

function line(string $s): void { echo $s . PHP_EOL; }

$service = app(FingerprintDeviceService::class);
$devices = FingerprintDevice::where('status', '!=', 'deactivated')->get();

line('=== DEVICE CONNECTIVITY TEST ===');
foreach ($devices as $device) {
    $t0 = microtime(true);
    try {
        $ok = $service->testConnection($device);
        $ms = round((microtime(true) - $t0) * 1000);
        line("  device={$device->id} '{$device->name}' ip={$device->ip_address} port={$device->port} => " . ($ok ? 'ONLINE' : 'OFFLINE') . " ({$ms}ms)");
    } catch (\Throwable $e) {
        $ms = round((microtime(true) - $t0) * 1000);
        line("  device={$device->id} '{$device->name}' => ERROR ({$ms}ms): " . $e->getMessage());
    }
}

line('');
line('=== PULL ATTENDANCE from device 1 (last active) ===');
$device = FingerprintDevice::find(1);
if ($device) {
    $t0 = microtime(true);
    try {
        $result = $service->syncAttendance($device);
        $ms = round((microtime(true) - $t0) * 1000);
        line('  result=' . json_encode($result) . " ({$ms}ms)");
    } catch (\Throwable $e) {
        $ms = round((microtime(true) - $t0) * 1000);
        line('  ERROR (' . $ms . 'ms): ' . $e->getMessage());
    }
}
