<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

function line(string $s): void { echo $s . PHP_EOL; }

$dateStr = Carbon::today()->toDateString();

// Absent employees from rot=2 grp=4 (largest absent group)
$repo = app(Modules\Shifts\Repositories\RotationAssignmentRepository::class);
$assignments = $repo->getLatestActiveAssignments();
$absentGroup = $assignments->filter(fn ($a) => $a->rotation_id == 2 && $a->rotation_group_id == 4);

line('rot=2 grp=4 assigned=' . $absentGroup->count());
$employeeIds = $absentGroup->pluck('employee_id')->all();

$sessUsers = DB::table('attendance_sessions')->where('attendance_date', $dateStr)->whereIn('user_id', $employeeIds)->distinct()->pluck('user_id');
$utcFrom = Carbon::parse($dateStr)->startOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
$utcTo = Carbon::parse($dateStr)->endOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
$rawUsers = DB::table('raw_attendance_logs')->whereIn('user_id', $employeeIds)->whereBetween('punch_time', [$utcFrom, $utcTo])->distinct()->pluck('user_id');
$present = $sessUsers->merge($rawUsers)->unique();
$absent = array_values(array_diff($employeeIds, $present->toArray()));

line('rot=2 grp=4 present=' . count($present) . ' absent=' . count($absent));
line('');

// Sample 8 absent employees - full punch history last 4 days in LOCAL time
$sample = array_slice($absent, 0, 8);
foreach ($sample as $uid) {
    $name = DB::table('users')->where('id', $uid)->value('name');
    $logs = DB::table('raw_attendance_logs')
        ->where('user_id', $uid)
        ->where('punch_time', '>=', Carbon::parse($dateStr)->subDays(4)->startOfDay()->setTimezone('UTC'))
        ->orderBy('punch_time')
        ->get(['punch_time', 'device_id', 'punch_type']);
    $sessions = DB::table('attendance_sessions')
        ->where('user_id', $uid)
        ->where('attendance_date', '>=', Carbon::parse($dateStr)->subDays(4)->toDateString())
        ->orderBy('check_in_at')
        ->get(['attendance_date', 'check_in_at', 'check_out_at']);
    line("user={$uid} {$name}");
    line('  RAW (local):');
    foreach ($logs as $l) {
        line('    ' . Carbon::parse($l->punch_time)->setTimezone('Asia/Riyadh')->format('Y-m-d H:i') . ' dev=' . $l->device_id . ' type=' . $l->punch_type);
    }
    line('  SESSIONS:');
    foreach ($sessions as $s) {
        line('    ' . $s->attendance_date . ' ' . ($s->check_in_at ? substr($s->check_in_at, 11, 5) : 'null') . '->' . ($s->check_out_at ? substr($s->check_out_at, 11, 5) : 'open'));
    }
    if ($logs->isEmpty() && $sessions->isEmpty()) {
        line('    (no data)');
    }
    line('');
}

// Also: latest raw log timestamp in DB overall (is the pull working?)
$latest = DB::table('raw_attendance_logs')->max('punch_time');
line('LATEST_RAW_LOG=' . $latest . ' (local=' . ($latest ? Carbon::parse($latest)->setTimezone('Asia/Riyadh')->format('Y-m-d H:i:s') : 'n/a') . ')');

// total raw logs today local morning (05:00-12:00 UTC = 08:00-15:00 local)
$morningFrom = Carbon::parse($dateStr . ' 05:00:00')->setTimezone('UTC')->format('Y-m-d H:i:s');
$morningTo = Carbon::parse($dateStr . ' 12:00:00')->setTimezone('UTC')->format('Y-m-d H:i:s');
$morningCount = DB::table('raw_attendance_logs')->whereBetween('punch_time', [$morningFrom, $morningTo])->count();
line('RAW_PUNCHES_TODAY_08:00-15:00_LOCAL=' . $morningCount);
