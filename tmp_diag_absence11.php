<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

function line(string $s): void { echo $s . PHP_EOL; }

$dateStr = Carbon::today()->toDateString();
line('TODAY=' . $dateStr . ' NOW=' . Carbon::now()->format('Y-m-d H:i:s'));

line('');
line('=== UNPROCESSED RAW LOGS (processed=0) ===');
$unproc = DB::table('raw_attendance_logs')->where('processed', 0)->count();
line('unprocessed_total=' . $unproc);
if ($unproc > 0) {
    $recent = DB::table('raw_attendance_logs')->where('processed', 0)->orderByDesc('punch_time')->limit(15)->get(['id', 'user_id', 'device_id', 'punch_time', 'punch_type', 'created_at']);
    foreach ($recent as $r) {
        line('  id=' . $r->id . ' user=' . $r->user_id . ' dev=' . $r->device_id . ' punch=' . $r->punch_time . ' type=' . $r->punch_type . ' created=' . $r->created_at);
    }
    line('  oldest_unprocessed=' . DB::table('raw_attendance_logs')->where('processed', 0)->min('punch_time'));
}

line('');
line('=== ALL raw logs today: count by processed status ===');
$todayFrom = Carbon::parse($dateStr)->startOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
$todayTo = Carbon::parse($dateStr)->endOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
$byProc = DB::table('raw_attendance_logs')
    ->whereBetween('punch_time', [$todayFrom, $todayTo])
    ->selectRaw('processed, COUNT(*) as c')
    ->groupBy('processed')
    ->get();
foreach ($byProc as $r) {
    line('  processed=' . $r->processed . ': ' . $r->c);
}

line('');
line('=== Raw logs TODAY but OUTSIDE UTC bounds (stored in local time?) ===');
// All raw logs with punch_time between local-midnight and local-now, regardless of UTC conversion
$localStart = $dateStr . ' 00:00:00';
$localNow = Carbon::now()->format('Y-m-d H:i:s');
$countStoredLocal = DB::table('raw_attendance_logs')
    ->whereBetween('punch_time', [$localStart, $localNow])
    ->count();
line('raw logs stored between local ' . $localStart . ' and ' . $localNow . ' = ' . $countStoredLocal);

line('');
line('=== Do any ABSENT employees have raw punches stored "locally" today? ===');
$service = app(Modules\Shifts\Services\AbsenceCalculationService::class);
$absent = $service->getAbsentEmployees(Carbon::today())->toArray();
line('absent_count=' . count($absent));
$rawLocal = DB::table('raw_attendance_logs')
    ->whereIn('user_id', $absent)
    ->whereBetween('punch_time', [$localStart, $localNow])
    ->select('user_id', 'punch_time', 'device_id', 'punch_type')
    ->orderBy('punch_time')
    ->get();
line('absent_with_raw_stored_local=' . count($rawLocal));
foreach ($rawLocal->groupBy('user_id')->take(10) as $uid => $rows) {
    $name = DB::table('users')->where('id', $uid)->value('name');
    line('  user=' . $uid . ' ' . $name . ': ' . $rows->map(fn ($r) => $r->punch_time . '(dev' . $r->device_id . ',' . $r->punch_type . ')')->implode(' | '));
}

line('');
line('=== AttendanceSessions today: how many sessions exist for absent employees (any attendance_date) ===');
$sessAbsent = DB::table('attendance_sessions')
    ->whereIn('user_id', $absent)
    ->where('attendance_date', $dateStr)
    ->select('user_id', 'check_in_at', 'check_out_at')
    ->get();
line('absent_with_session_today=' . count($sessAbsent));
foreach ($sessAbsent->groupBy('user_id')->take(10) as $uid => $rows) {
    $name = DB::table('users')->where('id', $uid)->value('name');
    line('  user=' . $uid . ' ' . $name . ': ' . $rows->map(fn ($r) => ($r->check_in_at ? substr($r->check_in_at, 11, 5) : 'null') . '->' . ($r->check_out_at ? substr($r->check_out_at, 11, 5) : 'open'))->implode(' | '));
}
