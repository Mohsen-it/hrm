<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Carbon\Carbon;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Models\RawAttendanceLog;
use Modules\AttendanceIntegration\DTOs\PunchType;
use Modules\AttendanceIntegration\Services\SchedulePunchClassifierService;
use Modules\Shifts\Services\ScheduleResolverService;

$resolver = app(ScheduleResolverService::class);
$classifier = app(SchedulePunchClassifierService::class);

// Sessions on 08-16 (rest-day phantoms) — check-in within the previous duty's departure window
$sessions = AttendanceSession::whereBetween('check_in_at', ['2026-08-15 00:00:00', '2026-08-16 23:59:59'])
    ->orderBy('check_in_at')->get();

$phantomOpen = 0;
$phantomClosed = 0;
$openWorkDay = 0;
$details = [];

foreach ($sessions as $s) {
    if (! $s->user_id) {
        continue;
    }
    $date = $s->check_in_at->toDateString();
    $res = $resolver->resolve($s->user_id, $date);
    $open = $s->check_out_at === null;

    if (! $res['is_work_day']) {
        // Rest-day session: is its check-in inside the previous duty's departure window?
        $prev = $resolver->resolve($s->user_id, Carbon::parse($date)->subDay()->toDateString());
        $ahead = $prev['next_day_out_ahead_margin'] ?? null;
        $above = $prev['next_day_out_above_margin'] ?? null;
        $inDepartureWindow = false;
        if ($prev['is_work_day'] && $ahead && $above) {
            $start = Carbon::parse($date.' '.substr($ahead, 0, 5));
            $end = Carbon::parse($date.' '.substr($above, 0, 5));
            $inDepartureWindow = $s->check_in_at->between($start, $end);
        }
        // What does the fixed classifier say about the opening punch?
        $class = $classifier->classify($s->user_id, $s->check_in_at, PunchType::CheckIn);

        if ($inDepartureWindow || $class !== PunchType::CheckIn) {
            $phantomOpen += $open ? 1 : 0;
            $phantomClosed += ! $open ? 1 : 0;
            $details[] = 'PHANTOM'.($open ? ' OPEN' : ' CLOSED')." in={$s->check_in_at} out=".($s->check_out_at ?? 'OPEN')." raw={$s->raw_log_id} class={$class->value} user={$s->user_id}";
        }
    } elseif ($open) {
        // Open work-day session: does the employee have a processed rest-day punch that should have closed it?
        $nextDay = Carbon::parse($date)->addDay()->toDateString();
        $resNext = $resolver->resolve($s->user_id, $nextDay);
        if (! $resNext['is_work_day']) {
            $nextPunch = RawAttendanceLog::where('user_id', $s->user_id)
                ->where('punch_time', '>=', $s->check_in_at)
                ->where('punch_time', '<', $nextDay.' 23:59:59')
                ->orderBy('punch_time')->first();
            if ($nextPunch) {
                $class = $classifier->classify($s->user_id, Carbon::parse($nextPunch->punch_time), PunchType::CheckIn);
                if ($class === PunchType::CheckOut) {
                    $openWorkDay++;
                    $details[] = 'OPEN-WORKDAY closeable in='.$s->check_in_at.' nextPunch='.$nextPunch->punch_time.' (raw '.$nextPunch->id.') user='.$s->user_id;
                }
            }
        }
    }
}

echo "phantom OPEN: {$phantomOpen}\n";
echo "phantom CLOSED: {$phantomClosed}\n";
echo "open work-day closeable via next punch: {$openWorkDay}\n";
echo '--- sample ---'.PHP_EOL;
foreach (array_slice($details, 0, 30) as $d) {
    echo "  $d\n";
}
