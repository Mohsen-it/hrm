<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Attendance\Services\DailyReportService;
use Modules\Attendance\Services\AbsenceCalculationService;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Models\RawAttendanceLog;
use Modules\Shifts\Repositories\RotationAssignmentRepository;
use Modules\Users\Models\User;
use Illuminate\Support\Carbon;

$date = '2026-08-16';
$service = app(DailyReportService::class);
$result = $service->build($date, '08:30', 1);
$rows = $result['rows'];

// 1) Rest but has a session today
echo "=== 1) REST but has check-in session (2026-08-16) ===\n";
$restWithSession = $rows->filter(fn ($r) => $r['status'] === 'rest' && $r['check_in'] !== '');
echo "count: ".$restWithSession->count()."\n";
foreach ($restWithSession->take(15) as $r) {
    echo "  {$r['name']} | {$r['rotation']} | in={$r['check_in']}\n";
}

// 2) Absent but has raw punch today
echo "\n=== 2) ABSENT but has raw punch today ===\n";
$absentIds = $rows->where('status', 'absent')->pluck('id');
$bounds = (function () use ($date) {
    $day = Carbon::parse($date)->startOfDay();
    $tz = config('app.timezone', 'Asia/Damascus');
    $start = Carbon::parse($day->toDateString().' 00:00:00', $tz)->utc();
    $end = Carbon::parse($day->toDateString().' 23:59:59', $tz)->utc();
    return [$start, $end];
})();
$rawPunchIds = RawAttendanceLog::whereIn('user_id', $absentIds)
    ->whereBetween('punch_time', $bounds)
    ->distinct()->pluck('user_id');
echo "absent with raw punch: ".$rawPunchIds->count()."\n";
foreach ($rows->where('status', 'absent')->whereIn('id', $rawPunchIds)->take(10) as $r) {
    echo "  {$r['name']} | {$r['rotation']}\n";
}

// 3) Incomplete: verify yesterday sessions actually have no checkout
echo "\n=== 3) INCOMPLETE verification (yesterday 08-15 sessions) ===\n";
$incompleteRows = $rows->where('status', 'incomplete');
foreach ($incompleteRows->take(10) as $r) {
    $sessions = AttendanceSession::onDate('2026-08-15')->where('user_id', $r['id'])->orderBy('check_in_at')->get();
    $outs = $sessions->filter(fn ($s) => $s->check_out_at !== null)->count();
    $opens = $sessions->filter(fn ($s) => $s->check_out_at === null)->count();
    echo "  {$r['name']} | sessions=".$sessions->count()." closed=$outs open=$opens | rot={$r['rotation']}\n";
}

// 4) Present employees with no session at all
echo "\n=== 4) PRESENT but zero sessions today ===\n";
$presentNoSession = $rows->filter(fn ($r) => $r['status'] === 'present' && $r['check_in'] === '');
echo "count: ".$presentNoSession->count()."\n";
foreach ($presentNoSession->take(10) as $r) {
    echo "  {$r['name']} | {$r['rotation']} | expected=".($r['expected'] ? 'Y' : 'N')."\n";
}
