<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Carbon\Carbon;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Models\RawAttendanceLog;
use Modules\Attendance\Services\AttendanceSessionService;
use Modules\Attendance\Services\DailyAttendanceSummaryService;
use Modules\AttendanceIntegration\DTOs\PunchType;
use Modules\AttendanceIntegration\Services\SchedulePunchClassifierService;
use Modules\Shifts\Services\ScheduleResolverService;

$dryRun = in_array('--apply', $_SERVER['argv'] ?? [], true) === false;
$resolver = app(ScheduleResolverService::class);
$classifier = app(SchedulePunchClassifierService::class);
$summaryService = app(DailyAttendanceSummaryService::class);
$sessionService = app(AttendanceSessionService::class);

$sessions = AttendanceSession::whereBetween('check_in_at', ['2026-08-13 00:00:00', '2026-08-16 23:59:59'])
    ->whereNull('check_out_at')->orderBy('check_in_at')->get();

$toDelete = [];
$toClose = [];

foreach ($sessions as $s) {
    if (! $s->user_id) {
        continue;
    }
    $date = $s->check_in_at->toDateString();
    $res = $resolver->resolve($s->user_id, $date);
    if (! $res['is_work_day']) {
        continue; // rest-day phantoms already handled
    }

    $class = $classifier->classify($s->user_id, $s->check_in_at, PunchType::CheckIn);
    if ($class->value !== 'check_in') {
        // A check-out punch that was wrongly opened as a session.
        $toDelete[] = $s;
        continue;
    }

    // First subsequent punch that classifies as check-out closes the session.
    $punches = RawAttendanceLog::where('user_id', $s->user_id)
        ->where('punch_time', '>', $s->check_in_at)
        ->where('punch_time', '<', Carbon::parse($date)->addDays(2)->toDateString().' 23:59:59')
        ->orderBy('punch_time')->get();
    foreach ($punches as $p) {
        $pc = $classifier->classify($s->user_id, Carbon::parse($p->punch_time), PunchType::CheckIn);
        if ($pc->value === 'check_out') {
            $toClose[] = [$s, $p];
            break;
        }
    }
}

echo 'DRY-RUN='.($dryRun ? 'yes' : 'NO (APPLYING)').PHP_EOL;
echo 'work-day phantom sessions (opened by check-out punch) to DELETE: '.count($toDelete).PHP_EOL;
echo 'open check-in sessions to CLOSE: '.count($toClose).PHP_EOL;

if ($dryRun) {
    exit(0);
}

$affected = [];
foreach ($toDelete as $s) {
    $date = $s->check_in_at->toDateString();
    $affected[$s->user_id.'|'.$date] = [$s->user_id, $date];
    $s->delete();
}
foreach ($toClose as [$s, $p]) {
    try {
        $sessionService->closeSession($s, Carbon::parse($p->punch_time), [
            'raw_log_id' => $p->id,
            'source' => $p->source ?? 'device',
            'device_id' => $p->device_id,
        ]);
        $date = $s->check_in_at->toDateString();
        $affected[$s->user_id.'|'.$date] = [$s->user_id, $date];
    } catch (\Throwable $e) {
        echo '  close failed sess='.$s->id.': '.$e->getMessage().PHP_EOL;
    }
}
foreach ($affected as [$userId, $date]) {
    try {
        $summaryService->recalculateForUserAndDate($userId, $date);
    } catch (\Throwable $e) {
        echo '  summary recalc failed '.$userId.' '.$date.': '.$e->getMessage().PHP_EOL;
    }
}
echo 'APPLIED. deleted='.count($toDelete).' closed='.count($toClose).PHP_EOL;
