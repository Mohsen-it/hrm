<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

function line(string $s): void { echo $s . PHP_EOL; }

$dateStr = Carbon::today()->toDateString();

line('=== HOURLY RAW PUNCHES TODAY (local hours) per device ===');
$from = Carbon::parse($dateStr)->startOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
$to = Carbon::parse($dateStr)->endOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');

$rows = DB::table('raw_attendance_logs')
    ->whereBetween('punch_time', [$from, $to])
    ->get(['device_id', 'punch_time']);

$byHour = [];
foreach ($rows as $r) {
    $h = Carbon::parse($r->punch_time)->setTimezone('Asia/Riyadh')->format('Y-m-d H:00');
    $byHour[$h][$r->device_id] = ($byHour[$h][$r->device_id] ?? 0) + 1;
}
ksort($byHour);
foreach ($byHour as $h => $devs) {
    $parts = [];
    foreach ($devs as $dev => $c) {
        $parts[] = "dev{$dev}:{$c}";
    }
    line('  ' . $h . '  ' . implode(' | ', $parts));
}

line('');
line('=== Same for yesterday (for comparison) ===');
$yFrom = Carbon::parse($dateStr)->subDay()->startOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
$yTo = Carbon::parse($dateStr)->subDay()->endOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
$rowsY = DB::table('raw_attendance_logs')
    ->whereBetween('punch_time', [$yFrom, $yTo])
    ->get(['device_id', 'punch_time']);
$byHourY = [];
foreach ($rowsY as $r) {
    $h = Carbon::parse($r->punch_time)->setTimezone('Asia/Riyadh')->format('Y-m-d H:00');
    $byHourY[$h][$r->device_id] = ($byHourY[$h][$r->device_id] ?? 0) + 1;
}
ksort($byHourY);
foreach ($byHourY as $h => $devs) {
    $parts = [];
    foreach ($devs as $dev => $c) {
        $parts[] = "dev{$dev}:{$c}";
    }
    line('  ' . $h . '  ' . implode(' | ', $parts));
}
