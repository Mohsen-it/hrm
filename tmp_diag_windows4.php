<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

function line(string $s): void { echo $s . PHP_EOL; }

$repo = app(Modules\Shifts\Repositories\RotationAssignmentRepository::class);

line('=== Employees who punched 08:00 but window starts 09:30 ===');
$users = [10077, 10055, 10275, 10340];
foreach ($users as $uid) {
    $name = DB::table('users')->where('id', $uid)->value('name');
    $assign = $repo->getLatestActiveAssignments()->firstWhere('employee_id', $uid);
    line("user={$uid} {$name}");
    if ($assign) {
        $rot = $assign->rotation;
        $grp = $assign->rotationGroup;
        $snap = $assign->snapshot_data;
        line('  assign rot=' . $rot->id . " '{$rot->name}' grp={$grp?->id} '{$grp?->name}' start={$assign->start_date} end={$assign->end_date}");
        line('  att_rotations margins: in=' . $rot->in_ahead_margin . '..' . $rot->in_above_margin . ' out=' . $rot->out_ahead_margin . '..' . $rot->out_above_margin . ' grace=' . $rot->grace_minutes);
        line('  snapshot rotation margins: ' . json_encode([
            'in_ahead_margin' => $snap['rotation']['in_ahead_margin'] ?? null,
            'in_above_margin' => $snap['rotation']['in_above_margin'] ?? null,
            'out_ahead_margin' => $snap['rotation']['out_ahead_margin'] ?? null,
            'out_above_margin' => $snap['rotation']['out_above_margin'] ?? null,
        ]));
        $engine = app(Modules\Shifts\Services\RotationEngine::class);
        $times = $engine->resolveTimes($assign);
        line('  resolveTimes: in=' . ($times['check_in'] ?? '-') . ' out=' . ($times['check_out'] ?? '-') . ' overnight=' . ($times['is_overnight'] ? 'Y' : 'N'));
        line('  windows: in=' . ($times['in_ahead_margin'] ?? '-') . '..' . ($times['in_above_margin'] ?? '-') . ' out=' . ($times['out_ahead_margin'] ?? '-') . '..' . ($times['out_above_margin'] ?? '-'));
    } else {
        line('  NO ASSIGNMENT');
    }
    line('');
}

line('');
line('=== All rotations: att_rotations windows vs live time schedules ===');
$rots = DB::table('att_rotations')->get();
foreach ($rots as $rot) {
    $ts = DB::table('att_time_schedules')->where('id', $rot->time_schedule_id)->first();
    line("rot={$rot->id} '{$rot->name}'");
    line("  att_rotations: in={$rot->in_ahead_margin}..{$rot->in_above_margin} out={$rot->out_ahead_margin}..{$rot->out_above_margin}");
    if ($ts) {
        line("  time_schedule(id={$ts->id}): in_time={$ts->in_time} out_time={$ts->out_time} multi={$ts->is_multi_day} margins in={$ts->in_ahead_margin}/{$ts->in_above_margin} out={$ts->out_ahead_margin}/{$ts->out_above_margin}");
    } else {
        line('  time_schedule: NONE');
    }
    line('');
}
