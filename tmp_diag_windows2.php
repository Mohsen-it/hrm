<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

function line(string $s): void { echo $s . PHP_EOL; }

line('NOW=' . Carbon::now()->format('Y-m-d H:i:s'));

$punchWindow = app(Modules\Attendance\Services\PunchWindowService::class);
$resolver = app(Modules\Shifts\Services\ScheduleResolverService::class);
$repo = app(Modules\Shifts\Repositories\RotationAssignmentRepository::class);

// Test classification of yesterday's EVENING punches (check-outs)
$testPunches = [
    ['user' => 10275, 'at' => '2026-08-11 21:02:45', 'desc' => 'doutry 3-9 evening out'],
    ['user' => 10312, 'at' => '2026-08-11 21:21:11', 'desc' => 'doutry 1-3 evening out'],
    ['user' => 14186, 'at' => '2026-08-11 21:23:21', 'desc' => 'syrian 1-3 evening out'],
    ['user' => 10300, 'at' => '2026-08-11 21:55:26', 'desc' => 'doutry 1-3 evening out'],
    ['user' => 10275, 'at' => '2026-08-12 08:10:24', 'desc' => 'doutry 3-9 morning IN'],
];

foreach ($testPunches as $tp) {
    $at = new DateTimeImmutable($tp['at']);
    $class = $punchWindow->classify($tp['user'], $at, false);
    $resolved = $resolver->resolve($tp['user'], $at->format('Y-m-d'));
    line($tp['desc'] . " user={$tp['user']} at={$tp['at']}");
    line('  -> classified=' . json_encode($class));
    line('  -> windows in=' . ($resolved['in_ahead_margin'] ?? '-') . '..' . ($resolved['in_above_margin'] ?? '-') . ' out=' . ($resolved['out_ahead_margin'] ?? '-') . '..' . ($resolved['out_above_margin'] ?? '-'));
    line('');
}

// Now simulate full processing of those raw logs as the pipeline would
line('=== SIMULATE processRawLog for a few raw logs ===');
$svc = app(Modules\Attendance\Services\AttendanceSessionService::class);
$rawLogs = DB::table('raw_attendance_logs')
    ->whereBetween('punch_time', ['2026-08-11 20:30:00', '2026-08-11 23:59:59'])
    ->orderBy('punch_time')
    ->limit(10)
    ->get();
foreach ($rawLogs as $rl) {
    $log = Modules\Attendance\Models\RawAttendanceLog::find($rl->id);
    if (! $log) continue;
    $session = $svc->processRawLog($log);
    line('raw id=' . $rl->id . ' user=' . $rl->user_id . ' at=' . $rl->punch_time . ' stored=' . $rl->punch_type . ' -> session=' . ($session ? 'id=' . $session->id . ' check_out=' . ($session->check_out_at ? 'SET' : 'null') . ' status=' . $session->status : 'NONE'));
}
