<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Modules\Users\Models\User;
use Modules\Shifts\Models\RotationAssignment;
use Modules\Shifts\Models\Rotation;
$u = User::where('name', 'أحمد عبد القادر صالح')->first();
$a = RotationAssignment::where('employee_id', $u->id)->orderByDesc('start_date')->first();
echo "assign={$a->id} rot={$a->rotation_id} start={$a->start_date}\n";
$snap = $a->snapshot_data;
echo "snapshot time_schedule: ".json_encode($snap['time_schedule'] ?? null, JSON_UNESCAPED_UNICODE)."\n";
echo "snapshot rotation out windows: ".($snap['rotation']['out_ahead_margin'] ?? '-').' -> '.($snap['rotation']['out_above_margin'] ?? '-')."\n";
$rot = Rotation::find($a->rotation_id);
$live = $rot->timeSchedule;
echo "LIVE rotation {$rot->id} ts_id={$rot->time_schedule_id} schedule: in={$live->in_time} out={$live->out_time} multi=".var_export((bool)$live->is_multi_day, true)."\n";
