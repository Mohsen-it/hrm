<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
use Illuminate\Contracts\Console\Kernel;
use Modules\Shifts\Models\Rotation;
use Modules\Shifts\Models\RotationAssignment;

$stale = 0;
$total = 0;
$noSnap = 0;
$rows = RotationAssignment::query()->get();
foreach ($rows as $a) {
    $rot = Rotation::find($a->rotation_id);
    if (! $rot) {
        continue;
    }
    $total++;
    $snap = $a->snapshot_data;
    $snapTs = $snap['time_schedule']['id'] ?? null;
    $snapMulti = $snap['time_schedule']['is_multi_day'] ?? null;
    $snapOut = $snap['time_schedule']['out_time'] ?? null;
    if ($snapTs === null) {
        $noSnap++;

        continue;
    }
    $live = $rot->timeSchedule;
    $liveMulti = $live ? (bool) $live->is_multi_day : null;
    $liveOut = $live ? (string) $live->out_time : null;
    if ((bool) $snapMulti !== (bool) $liveMulti || (string) $snapOut !== (string) $liveOut) {
        $stale++;
        if ($stale <= 10) {
            echo "  STALE: assign={$a->id} rot={$a->rotation_id} snapMulti=".var_export($snapMulti, true)." snapOut=$snapOut | liveMulti=".var_export($liveMulti, true)." liveOut=$liveOut\n";
        }
    }
}
echo "total={$total} stale={$stale} no_snapshot={$noSnap}\n";
