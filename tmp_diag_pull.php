<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

function line(string $s): void { echo $s . PHP_EOL; }

line('=== DEVICES TABLE ===');
$devices = DB::table('fingerprint_devices')->orderBy('id')->get();
foreach ($devices as $d) {
    $status = isset($d->status) ? $d->status : '?';
    $lastSync = isset($d->last_synced_at) ? $d->last_synced_at : (isset($d->last_sync_at) ? $d->last_sync_at : '-');
    $online = isset($d->is_online) ? $d->is_online : '-';
    line("id={$d->id} name='{$d->name}' ip={$d->ip_address} port={$d->port} status={$status} last_sync={$lastSync} online={$online}");
}
line('');

line('=== RAW PUNCHES TODAY (2026-08-12) PER DEVICE ===');
$today = DB::table('raw_attendance_logs')
    ->selectRaw('device_id, count(*) as cnt, min(punch_time) as first_punch, max(punch_time) as last_punch')
    ->whereDate('punch_time', '2026-08-12')
    ->groupBy('device_id')
    ->orderBy('device_id')
    ->get();
foreach ($today as $r) {
    line("device={$r->device_id} count={$r->cnt} first={$r->first_punch} last={$r->last_punch}");
}
line('');

line('=== RAW PUNCHES YESTERDAY (2026-08-11) PER DEVICE ===');
$yesterday = DB::table('raw_attendance_logs')
    ->selectRaw('device_id, count(*) as cnt, min(punch_time) as first_punch, max(punch_time) as last_punch')
    ->whereDate('punch_time', '2026-08-11')
    ->groupBy('device_id')
    ->orderBy('device_id')
    ->get();
foreach ($yesterday as $r) {
    line("device={$r->device_id} count={$r->cnt} first={$r->first_punch} last={$r->last_punch}");
}
line('');

line('=== PUNCHES LAST 3 HOURS (any) ===');
$recent = DB::table('raw_attendance_logs')
    ->selectRaw('device_id, count(*) as cnt, max(punch_time) as last_punch')
    ->where('punch_time', '>=', now()->subHours(3))
    ->groupBy('device_id')
    ->orderBy('device_id')
    ->get();
foreach ($recent as $r) {
    line("device={$r->device_id} count={$r->cnt} last={$r->last_punch}");
}
if ($recent->isEmpty()) {
    line('NONE — no punches at all in the last 3 hours');
}
line('');

line('=== NOW / LAST PROCESSED LOG ===');
line('now=' . now()->format('Y-m-d H:i:s'));
$lastLog = DB::table('raw_attendance_logs')->orderByDesc('id')->first();
if ($lastLog) {
    line('last raw log id=' . $lastLog->id . ' time=' . $lastLog->punch_time . ' device=' . $lastLog->device_id);
} else {
    line('no raw logs at all');
}

line('');
line('=== SESSIONS TODAY ===');
$sess = DB::table('attendance_sessions')->whereDate('attendance_date', '2026-08-12')
    ->selectRaw('count(*) as total, sum(case when check_out_at is null then 1 else 0 end) as open')
    ->first();
line('sessions total=' . $sess->total . ' open=' . $sess->open);
