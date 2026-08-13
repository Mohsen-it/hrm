<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

function line(string $s): void { echo $s . PHP_EOL; }

line('NOW=' . Carbon::now()->format('Y-m-d H:i:s'));

line('');
line('=== LATEST 15 PUNCHES OVERALL (any date) ===');
$rows = DB::table('raw_attendance_logs')
    ->orderByDesc('punch_time')
    ->limit(15)
    ->get(['id', 'user_id', 'device_id', 'punch_time', 'punch_type', 'created_at']);
foreach ($rows as $r) {
    line('  id=' . $r->id . ' user=' . $r->user_id . ' dev=' . $r->device_id . ' punch=' . $r->punch_time . ' type=' . $r->punch_type . ' created=' . $r->created_at);
}

line('');
line('=== PUNCHES IN LAST 30 MINUTES ===');
$cutoff = Carbon::now()->subMinutes(30);
$recent = DB::table('raw_attendance_logs')
    ->where('punch_time', '>=', $cutoff)
    ->count();
line('punches_last_30min=' . $recent);

line('');
line('=== DEVICES status from DB ===');
$devices = DB::table('fingerprint_devices')->get(['id', 'name', 'ip_address', 'port', 'status', 'last_sync_at', 'updated_at']);
foreach ($devices as $d) {
    line('  dev=' . $d->id . " '{$d->name}' ip={$d->ip_address} port={$d->port} status={$d->status} last_sync={$d->last_sync_at} updated={$d->updated_at}");
}

line('');
line('=== SESSIONS created in last 30 min ===');
$sess = DB::table('attendance_sessions')->where('created_at', '>=', $cutoff)->count();
line('sessions_last_30min=' . $sess);
