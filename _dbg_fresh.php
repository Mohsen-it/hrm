<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Modules\Shifts\Models\RotationAssignment;
// find a rotation-2 assignment with a fresh (multi-day) snapshot
$rows = RotationAssignment::query()->where('rotation_id', 2)->orderByDesc('id')->take(3)->get();
foreach ($rows as $a) {
    echo "assign={$a->id} start={$a->start_date}\n";
    echo "  snapshot time_schedule: ".json_encode($a->snapshot_data['time_schedule'] ?? null, JSON_UNESCAPED_UNICODE)."\n";
}
// also check the DTO/format used by rotation 4
$rows4 = RotationAssignment::query()->where('rotation_id', 4)->orderByDesc('id')->take(2)->get();
foreach ($rows4 as $a) {
    echo "assign4={$a->id} start={$a->start_date}\n";
    echo "  snapshot time_schedule: ".json_encode($a->snapshot_data['time_schedule'] ?? null, JSON_UNESCAPED_UNICODE)."\n";
}
