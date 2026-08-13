<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

function line(string $s): void { echo $s . PHP_EOL; }

line('=== RECENT UPDATES ===');
line('-- latest rotations updated_at --');
foreach (DB::table('att_rotations')->orderByDesc('updated_at')->limit(5)->get(['id', 'name', 'updated_at']) as $r) {
    line("  rot={$r->id} '{$r->name}' updated={$r->updated_at}");
}
line('-- latest time schedules updated_at --');
foreach (DB::table('att_time_schedules')->orderByDesc('updated_at')->limit(5)->get(['id', 'name', 'updated_at']) as $r) {
    line("  ts={$r->id} '{$r->name}' updated={$r->updated_at}");
}
line('-- latest assignments updated_at --');
foreach (DB::table('att_rotation_assignments')->orderByDesc('updated_at')->limit(5)->get(['id', 'employee_id', 'rotation_id', 'updated_at']) as $r) {
    line("  assign={$r->id} emp={$r->employee_id} rot={$r->rotation_id} updated={$r->updated_at}");
}

line('');
line('=== ABSENCE HISTORY: how many absent per day last 10 days (operational) ===');
$service = app(Modules\Shifts\Services\AbsenceCalculationService::class);
for ($i = 10; $i >= 0; $i--) {
    $d = Carbon::today()->subDays($i);
    $exp = $service->getExpectedEmployees($d)->count();
    $abs = $service->getAbsentEmployees($d)->count();
    line('  ' . $d->format('Y-m-d D') . " expected={$exp} absent={$abs} rate=" . ($exp > 0 ? round($abs / $exp * 100) : '-') . '%');
}

line('');
line('=== rot=2 grp=4 employee 10045: what schedule is applied today ===');
$repo = app(Modules\Shifts\Repositories\RotationAssignmentRepository::class);
$engine = app(Modules\Shifts\Services\RotationEngine::class);
$a = $repo->getLatestActiveAssignments()->firstWhere('employee_id', 10045);
if ($a) {
    line('assignment: rot=' . $a->rotation_id . ' grp=' . $a->rotation_group_id . ' start=' . $a->start_date . ' end=' . $a->end_date);
    line('isWorkDay today=' . ($engine->isWorkDay($a->rotation, $a->rotationGroup, Carbon::today()) ? 'WORK' : 'rest'));
    $times = $engine->resolveTimes($a);
    line('resolveTimes: in=' . ($times['check_in'] ?? '-') . ' out=' . ($times['check_out'] ?? '-') . ' overnight=' . ($times['is_overnight'] ? 'Y' : 'N') . ' windows in=' . ($times['in_ahead_margin'] ?? '-') . '..' . ($times['in_above_margin'] ?? '-') . ' out=' . ($times['out_ahead_margin'] ?? '-') . '..' . ($times['out_above_margin'] ?? '-'));
    $ts = $a->rotation->timeSchedule;
    line('live time schedule: id=' . $ts?->id . ' in=' . $ts?->in_time . ' out=' . $ts?->out_time . ' multi=' . ($ts?->is_multi_day ? 'Y' : 'N'));
    $snap = $a->snapshot_data;
    line('snapshot ts: ' . json_encode($snap['time_schedule'] ?? null));
} else {
    line('NO ASSIGNMENT');
}

line('');
line('=== Check-in window for 10045: was 08:10 punch classified? ===');
// simulate classify for an 08:10 punch today
$resolver = app(Modules\Shifts\Services\ScheduleResolverService::class);
$resolved = $resolver->resolve(10045, Carbon::today()->toDateString());
line('resolver contract: in=' . ($resolved['expected_check_in'] ?? '-') . ' out=' . ($resolved['expected_check_out'] ?? '-') . ' isWorkDay=' . ($resolved['is_work_day'] ? 'Y' : 'N') . ' status=' . $resolved['status']);
line('  in window: ' . ($resolved['in_ahead_margin'] ?? '-') . ' .. ' . ($resolved['in_above_margin'] ?? '-'));
line('  out window: ' . ($resolved['out_ahead_margin'] ?? '-') . ' .. ' . ($resolved['out_above_margin'] ?? '-'));
line('  grace=' . ($resolved['grace_minutes'] ?? '-') . ' early_margin=' . ($resolved['early_margin'] ?? '-'));
