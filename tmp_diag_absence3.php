<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Shifts\Services\AbsenceCalculationService;

function line(string $s): void { echo $s . PHP_EOL; }

$date = Carbon::today();
$dateStr = $date->toDateString();
line('TODAY=' . $dateStr);

$service = app(AbsenceCalculationService::class);
$expected = $service->getExpectedEmployees($date)->toArray();
$absent = $service->getAbsentEmployees($date)->toArray();
$present = array_values(array_diff($expected, $absent));

line('expected=' . count($expected) . ' absent=' . count($absent) . ' present=' . count($present));

[$utcFrom, $utcTo] = [Carbon::parse($dateStr)->startOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s'), Carbon::parse($dateStr)->endOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s')];
line('utc bounds: ' . $utcFrom . ' -> ' . $utcTo);

line('');
line('=== ABSENT employees with raw punches (would be false negatives) ===');
$rawForAbsent = DB::table('raw_attendance_logs')
    ->whereIn('user_id', $absent)
    ->whereBetween('punch_time', [$utcFrom, $utcTo])
    ->select('user_id', 'punch_time', 'device_id')
    ->orderBy('punch_time')
    ->get()
    ->groupBy('user_id');
line('absent_with_raw=' . count($rawForAbsent));
foreach ($rawForAbsent as $uid => $punches) {
    line('  user=' . $uid . ' punches=' . $punches->map(fn ($p) => $p->punch_time . '/' . $p->device_id)->implode(', '));
}

line('');
line('=== ABSENT employees with sessions in last 3 days ===');
$recentSessions = DB::table('attendance_sessions')
    ->whereIn('user_id', $absent)
    ->where('attendance_date', '>=', Carbon::parse($dateStr)->subDays(3)->toDateString())
    ->select('user_id', 'attendance_date', 'check_in_at', 'check_out_at')
    ->orderBy('attendance_date')
    ->get()
    ->groupBy('user_id');
foreach ($recentSessions as $uid => $sessions) {
    line('  user=' . $uid . ' sessions=' . $sessions->map(fn ($s) => $s->attendance_date . '(' . substr((string)$s->check_in_at, 11, 5) . '->' . ($s->check_out_at ? substr((string)$s->check_out_at, 11, 5) : 'open') . ')')->implode(', '));
}

line('');
line('=== SAMPLE: pick 5 absent employees - full recent punch timeline (raw) ===');
$sample = array_slice($absent, 0, 5);
foreach ($sample as $uid) {
    $logs = DB::table('raw_attendance_logs')
        ->where('user_id', $uid)
        ->where('punch_time', '>=', Carbon::parse($dateStr)->subDays(2)->setTimezone('UTC'))
        ->orderBy('punch_time')
        ->get(['punch_time', 'device_id']);
    $name = DB::table('users')->where('id', $uid)->value('name');
    line('user=' . $uid . ' ' . $name . ' raw_count=' . count($logs));
    foreach ($logs as $l) {
        line('    ' . $l->punch_time . ' dev=' . $l->device_id . ' (local=' . Carbon::parse($l->punch_time)->setTimezone('Asia/Riyadh')->format('Y-m-d H:i') . ')');
    }
}

line('');
line('=== TOTAL raw logs distinct users today (all) ===');
$rawAll = DB::table('raw_attendance_logs')
    ->whereBetween('punch_time', [$utcFrom, $utcTo])
    ->distinct()
    ->pluck('user_id');
line('raw_distinct_users=' . count($rawAll));
line('raw_users_in_expected=' . count($rawAll->intersect($expected)));

line('');
line('=== Sessions distinct users today ===');
$sessUsers = DB::table('attendance_sessions')->where('attendance_date', $dateStr)->distinct()->pluck('user_id');
line('session_users=' . count($sessUsers));
line('session_users_in_expected=' . count($sessUsers->intersect($expected)));
