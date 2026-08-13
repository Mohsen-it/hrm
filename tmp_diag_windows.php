<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

function line(string $s): void { echo $s . PHP_EOL; }

line('NOW=' . Carbon::now()->format('Y-m-d H:i:s'));

line('');
line('=== Today punches: employee -> classification used ===');
// Take today's 10 raw punches, show what the resolver returns for each employee
$utcFrom = Carbon::today()->startOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
$utcTo = Carbon::today()->endOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
$punches = DB::table('raw_attendance_logs')
    ->whereBetween('punch_time', [$utcFrom, $utcTo])
    ->orderBy('punch_time')
    ->limit(12)
    ->get(['id', 'user_id', 'device_id', 'punch_time', 'punch_type']);

$resolver = app(Modules\Shifts\Services\ScheduleResolverService::class);
$punchWindow = app(Modules\Attendance\Services\PunchWindowService::class);
$repo = app(Modules\Shifts\Repositories\RotationAssignmentRepository::class);
$engine = app(Modules\Shifts\Services\RotationEngine::class);

foreach ($punches as $p) {
    $at = new DateTimeImmutable($p->punch_time);
    $class = $punchWindow->classify($p->user_id, $at, false);
    $resolved = $resolver->resolve($p->user_id, $at->format('Y-m-d'));
    $assign = $repo->getLatestActiveAssignments()->firstWhere('employee_id', $p->user_id);
    $tsLive = $assign?->rotation?->timeSchedule;
    $snapTs = $assign?->snapshot_data['time_schedule'] ?? null;

    line('punch id=' . $p->id . ' user=' . $p->user_id . ' dev=' . $p->device_id . ' at=' . $p->punch_time . ' stored_type=' . $p->punch_type);
    line('  classified=' . json_encode($class));
    line('  resolver: work=' . ($resolved['is_work_day'] ? 'Y' : 'N') . ' in_win=' . ($resolved['in_ahead_margin'] ?? '-') . '..' . ($resolved['in_above_margin'] ?? '-') . ' out_win=' . ($resolved['out_ahead_margin'] ?? '-') . '..' . ($resolved['out_above_margin'] ?? '-'));
    if ($assign) {
        $rot = $assign->rotation;
        line('  rot=' . $rot->id . " '{$rot->name}' ts_id=" . ($rot->time_schedule_id ?? 'NULL'));
        line('    att_rotations margins: in=' . $rot->in_ahead_margin . '..' . $rot->in_above_margin . ' out=' . $rot->out_ahead_margin . '..' . $rot->out_above_margin);
        line('    live ts: ' . ($tsLive ? "in={$tsLive->in_time} out={$tsLive->out_time} multi=" . ($tsLive->is_multi_day ? 'Y' : 'N') . " margins(in)={$tsLive->in_ahead_margin}/{$tsLive->in_above_margin} margins(out)={$tsLive->out_ahead_margin}/{$tsLive->out_above_margin}" : 'NULL'));
        line('    snapshot ts: ' . json_encode($snapTs));
    }
    line('');
}
