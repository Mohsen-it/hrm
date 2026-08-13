<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

function line(string $s): void { echo $s . PHP_EOL; }

$dateStr = Carbon::today()->toDateString();

line('=== LAST PUNCH PER DEVICE ===');
$devices = DB::table('fingerprint_devices')->get();
foreach ($devices as $d) {
    $last = DB::table('raw_attendance_logs')->where('device_id', $d->id)->max('punch_time');
    $today = DB::table('raw_attendance_logs')->where('device_id', $d->id)
        ->whereBetween('punch_time', [Carbon::parse($dateStr)->startOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s'), Carbon::parse($dateStr)->endOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s')])
        ->count();
    line("  device={$d->id} name='{$d->name}' ip={$d->ip_address} last_punch={$last} (today={$today})");
}

line('');
line('=== LAST 15 raw logs today by time ===');
$rows = DB::table('raw_attendance_logs')
    ->whereBetween('punch_time', [Carbon::parse($dateStr)->startOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s'), Carbon::parse($dateStr)->endOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s')])
    ->orderByDesc('punch_time')
    ->limit(15)
    ->get(['user_id', 'device_id', 'punch_time']);
foreach ($rows as $r) {
    line('  ' . $r->punch_time . ' local=' . Carbon::parse($r->punch_time)->setTimezone('Asia/Riyadh')->format('H:i') . ' user=' . $r->user_id . ' dev=' . $r->device_id);
}

line('');
line('=== Device table columns + active status ===');
foreach ($devices as $d) {
    line('  device=' . $d->id . ' active=' . ($d->is_active ?? '?') . ' last_sync=' . ($d->last_sync_at ?? '?') . ' status=' . ($d->status ?? '?'));
}

line('');
line('=== When did devices 3 & 5 last send punches? (last 5 each) ===');
foreach ([3, 5] as $devId) {
    $lasts = DB::table('raw_attendance_logs')->where('device_id', $devId)->orderByDesc('punch_time')->limit(5)->get(['punch_time', 'user_id']);
    line("device={$devId}:");
    foreach ($lasts as $l) {
        line('  ' . $l->punch_time . ' (local ' . Carbon::parse($l->punch_time)->setTimezone('Asia/Riyadh')->format('Y-m-d H:i:s') . ') user=' . $l->user_id);
    }
}
