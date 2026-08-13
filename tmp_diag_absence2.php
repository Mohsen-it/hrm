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
line('TODAY=' . $dateStr . ' ' . $date->format('l'));

$service = app(AbsenceCalculationService::class);

line('');
line('=== getExpectedEmployees ===');
$expected = $service->getExpectedEmployees($date);
line('expected_count=' . count($expected));
line('expected_ids=' . $expected->implode(','));

line('');
line('=== getAbsentEmployees ===');
$absent = $service->getAbsentEmployees($date);
line('absent_count=' . count($absent));
line('absent_ids=' . $absent->implode(','));

$present = $expected->diff($absent);
line('present_ids=' . $present->implode(','));

line('');
line('=== ATTENDANCE SESSIONS today ===');
$sessions = DB::table('attendance_sessions')
    ->where('attendance_date', $dateStr)
    ->select('user_id', 'check_in_at', 'check_out_at', 'late_minutes', 'early_leave_minutes')
    ->get();
line('sessions_count=' . count($sessions));
foreach ($sessions->take(10) as $s) {
    line('  user=' . $s->user_id . ' in=' . $s->check_in_at . ' out=' . $s->check_out_at . ' late=' . $s->late_minutes . ' early=' . $s->early_leave_minutes);
}

line('');
line('=== RAW LOGS today ===');
$raw = DB::table('raw_attendance_logs')
    ->whereBetween('punch_time', [Carbon::parse($dateStr)->startOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s'), Carbon::parse($dateStr)->endOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s')])
    ->select('user_id', 'punch_time', 'device_id')
    ->get();
line('raw_count=' . count($raw));
foreach ($raw->take(10) as $r) {
    line('  user=' . $r->user_id . ' punch=' . $r->punch_time . ' dev=' . $r->device_id);
}

line('');
line('=== LATEST ASSIGNMENTS + resolveTimes per assignment ===');
$repo = app(Modules\Shifts\Repositories\RotationAssignmentRepository::class);
$engine = app(Modules\Shifts\Services\RotationEngine::class);
$assignments = $repo->getLatestActiveAssignments();
line('assignments_count=' . count($assignments));

foreach ($assignments->take(30) as $a) {
    $isWork = $engine->isWorkDay($a->rotation, $a->rotationGroup, $date);
    if (! $isWork) continue;
    $times = $engine->resolveTimes($a);
    $rotName = $a->rotation->name;
    $ts = $a->rotation->timeSchedule;
    $live = $ts ? "live_ts={$ts->id}({$ts->in_time}-{$ts->out_time} multi=" . (int)$ts->is_multi_day . ")" : 'live_ts=NULL';
    line('emp=' . $a->employee_id . " rot={$rotName} {$live}");
    line('  resolved in=' . ($times['check_in'] ?? '-') . " out=" . ($times['check_out'] ?? '-') . " overnight=" . ($times['is_overnight'] ? 'Y' : 'N') . ' late=' . ($times['late_margin'] ?? '-') . ' early=' . ($times['early_margin'] ?? '-'));
    line('  windows in=' . ($times['in_ahead_margin'] ?? '-') . '..' . ($times['in_above_margin'] ?? '-') . ' out=' . ($times['out_ahead_margin'] ?? '-') . '..' . ($times['out_above_margin'] ?? '-'));
}
