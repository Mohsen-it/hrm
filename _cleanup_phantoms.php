<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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

$from = '2026-08-13 00:00:00';
$to = '2026-08-16 23:59:59';

$sessions = AttendanceSession::whereBetween('check_in_at', [$from, $to])
    ->orderBy('check_in_at')->get();

$toDelete = [];
$toClose = [];

foreach ($sessions as $s) {
    if (! $s->user_id || $s->check_out_at !== null) {
        continue;
    }
    $date = $s->check_in_at->toDateString();
    $res = $resolver->resolve($s->user_id, $date);

    if (! $res['is_work_day']) {
        $class = $classifier->classify($s->user_id, $s->check_in_at, PunchType::CheckIn);
        if ($class !== PunchType::CheckIn) {
            $toDelete[] = $s;
        }
        continue;
    }

    // Open work-day session: is there a processed rest-day punch that should have closed it?
    $nextDay = Carbon::parse($date)->addDay()->toDateString();
    $resNext = $resolver->resolve($s->user_id, $nextDay);
    if ($resNext['is_work_day']) {
        continue;
    }
    $nextPunch = RawAttendanceLog::where('user_id', $s->user_id)
        ->where('punch_time', '>', $s->check_in_at)
        ->where('punch_time', '<', $nextDay.' 23:59:59')
        ->orderBy('punch_time')->first();
    if (! $nextPunch) {
        continue;
    }
    $class = $classifier->classify($s->user_id, Carbon::parse($nextPunch->punch_time), PunchType::CheckIn);
    if ($class === PunchType::CheckOut) {
        $toClose[] = [$s, $nextPunch];
    }
}

echo 'DRY-RUN='.($dryRun ? 'yes' : 'NO (APPLYING)').PHP_EOL;
echo 'phantom sessions to DELETE: '.count($toDelete).PHP_EOL;
echo 'open work-day sessions to CLOSE: '.count($toClose).PHP_EOL;

if ($dryRun) {
    foreach (array_slice($toDelete, 0, 10) as $s) {
        echo '  DELETE sess='.$s->id.' user='.$s->user_id.' in='.$s->check_in_at.PHP_EOL;
    }
    foreach (array_slice($toClose, 0, 8) as [$s, $p]) {
        echo '  CLOSE sess='.$s->id.' user='.$s->user_id.' in='.$s->check_in_at.' at punch '.$p->punch_time.' (raw '.$p->id.')'.PHP_EOL;
    }
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
        echo '  summary recalc failed for '.$userId.' '.$date.': '.$e->getMessage().PHP_EOL;
    }
}

echo 'APPLIED. deleted='.count($toDelete).' closed='.count($toClose).' summaries recalculated='.count($affected).PHP_EOL;
