<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

function line(string $s): void { echo $s . PHP_EOL; }

$dateStr = Carbon::today()->toDateString();

line('=== RAW LOG VOLUME: last 12 days ===');
for ($i = 12; $i >= 0; $i--) {
    $d = Carbon::today()->subDays($i);
    $from = $d->copy()->startOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
    $to = $d->copy()->endOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
    $raw = DB::table('raw_attendance_logs')->whereBetween('punch_time', [$from, $to])->count();
    $sess = DB::table('attendance_sessions')->where('attendance_date', $d->toDateString())->count();
    $distinctRaw = DB::table('raw_attendance_logs')->whereBetween('punch_time', [$from, $to])->distinct()->count('user_id');
    line('  ' . $d->format('Y-m-d D') . " raw={$raw} distinct_users={$distinctRaw} sessions={$sess}");
}

line('');
line('=== RAW LOGS TODAY by device ===');
$from = Carbon::parse($dateStr)->startOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
$to = Carbon::parse($dateStr)->endOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
$devices = DB::table('raw_attendance_logs')
    ->whereBetween('punch_time', [$from, $to])
    ->selectRaw('device_id, COUNT(*) as c')
    ->groupBy('device_id')
    ->orderByDesc('c')
    ->get();
foreach ($devices as $d) {
    line('  device=' . $d->device_id . ': ' . $d->c . ' punches');
}

line('');
line('=== Yesterday (08-11) raw by device ===');
$yFrom = Carbon::parse($dateStr)->subDay()->startOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
$yTo = Carbon::parse($dateStr)->subDay()->endOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
$devicesY = DB::table('raw_attendance_logs')
    ->whereBetween('punch_time', [$yFrom, $yTo])
    ->selectRaw('device_id, COUNT(*) as c')
    ->groupBy('device_id')
    ->orderByDesc('c')
    ->get();
foreach ($devicesY as $d) {
    line('  device=' . $d->device_id . ': ' . $d->c . ' punches');
}

line('');
line('=== OPEN SESSIONS count (never closed) ===');
$open = DB::table('attendance_sessions')->whereNull('check_out_at')->count();
line('open_sessions_total=' . $open);
$openRecent = DB::table('attendance_sessions')->whereNull('check_out_at')->where('check_in_at', '>=', Carbon::parse($dateStr)->subDays(7)->startOfDay())->count();
line('open_sessions_last7d=' . $openRecent);

// Sample: open sessions for absent employees - what's their check_in time distribution
line('');
line('=== open sessions per day (check_in date) ===');
$openByDay = DB::table('attendance_sessions')
    ->whereNull('check_out_at')
    ->selectRaw('DATE(check_in_at) as d, COUNT(*) as c')
    ->groupBy('d')
    ->orderByDesc('d')
    ->limit(10)
    ->get();
foreach ($openByDay as $r) {
    line('  ' . $r->d . ': ' . $r->c);
}

line('');
line('=== Check last processing state: unprocessed raw logs ===');
$cols = DB::select('SHOW COLUMNS FROM raw_attendance_logs');
line(json_encode(array_map(fn ($c) => $c->Field, $cols)));
