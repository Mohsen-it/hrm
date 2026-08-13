<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Shifts\Models\Rotation;

function line(string $s): void { echo $s . PHP_EOL; }

line('NOW=' . Carbon::now()->format('Y-m-d H:i:s') . ' tz=' . Carbon::now()->getTimezone());
$date = Carbon::today();
$dateStr = $date->toDateString();
line('TODAY=' . $dateStr . ' ' . $date->format('l'));

line('');
line('=== ROTATIONS patterns & isWorkDay today ===');
$rotations = Rotation::with('groups')->get();
foreach ($rotations as $rot) {
    $pat = is_array($rot->pattern) ? implode(',', $rot->pattern) : 'N/A';
    line("rot={$rot->id} '{$rot->name}' pattern=[{$pat}] cycle={$rot->cycle_length} work={$rot->work_days_count} anchor={$rot->anchor_start_date} groups={$rot->groups->count()}");
    foreach ($rot->groups as $g) {
        $engine = app(Modules\Shifts\Services\RotationEngine::class);
        $work = $engine->isWorkDay($rot, $g, $date) ? 'WORK' : 'rest';
        line("   grp={$g->id} '{$g->name}' start={$g->start_date} idx={$g->group_index} => {$work}");
    }
}

line('');
line('=== getExpectedEmployees recomputed manually (department + rotation breakdown) ===');
$service = app(Modules\Shifts\Services\AbsenceCalculationService::class);
$expected = $service->getExpectedEmployees($date)->toArray();
line('expected_total=' . count($expected));

// How many expected have a session or raw punch today?
[$utcFrom, $utcTo] = [Carbon::parse($dateStr)->startOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s'), Carbon::parse($dateStr)->endOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s')];

$sessUsers = DB::table('attendance_sessions')->where('attendance_date', $dateStr)->whereIn('user_id', $expected)->distinct()->pluck('user_id');
$rawUsers = DB::table('raw_attendance_logs')->whereIn('user_id', $expected)->whereBetween('punch_time', [$utcFrom, $utcTo])->distinct()->pluck('user_id');
$present = $sessUsers->merge($rawUsers)->unique()->values();
$absent = array_values(array_diff($expected, $present->toArray()));

line('present(session+raw)=' . count($present) . ' absent=' . count($absent));

line('');
line('=== ABSENT DETAILS: rotation/group per employee ===');
$repo = app(Modules\Shifts\Repositories\RotationAssignmentRepository::class);
$assignments = $repo->getLatestActiveAssignments()->keyBy('employee_id');
$engine = app(Modules\Shifts\Services\RotationEngine::class);

$byRotation = [];
foreach ($absent as $uid) {
    $a = $assignments->get($uid);
    $rot = $a?->rotation;
    $grp = $a?->rotationGroup;
    $key = $rot ? "rot={$rot->id} '{$rot->name}' / grp={$grp?->id}" : 'NO-ASSIGNMENT';
    $byRotation[$key] = ($byRotation[$key] ?? 0) + 1;
}
arsort($byRotation);
foreach ($byRotation as $k => $c) {
    line("  {$c}x {$k}");
}

line('');
line('=== Raw punches today (local time) per absent employee ===');
$rawForAbsent = DB::table('raw_attendance_logs')
    ->whereIn('user_id', $absent)
    ->whereBetween('punch_time', [$utcFrom, $utcTo])
    ->select('user_id', 'punch_time')
    ->get()
    ->groupBy('user_id');
line('absent_with_raw_utc=' . count($rawForAbsent));

line('');
line('=== Sessions today (by attendance_date) for absent ===');
$sessForAbsent = DB::table('attendance_sessions')
    ->whereIn('user_id', $absent)
    ->where('attendance_date', $dateStr)
    ->select('user_id', 'check_in_at')
    ->get()
    ->groupBy('user_id');
line('absent_with_session=' . count($sessForAbsent));
