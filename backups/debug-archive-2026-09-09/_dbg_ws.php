<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
use Illuminate\Contracts\Console\Kernel;
use Modules\Shifts\Models\RotationAssignment;
use Modules\Users\Models\User;

$u = User::where('name', 'وسيم احمد زيداني')->first();
echo 'user '.$u->id.' '.$u->name.PHP_EOL;
$a = RotationAssignment::where('employee_id', $u->id)->orderByDesc('start_date')->first();
if ($a) {
    echo 'assignment id='.$a->id.' rotation_id='.$a->rotation_id.' group_id='.$a->rotation_group_id.' start='.$a->start_date.' end='.($a->end_date ?? 'NULL').PHP_EOL;
    echo 'snapshot='.json_encode($a->snapshot_data, JSON_UNESCAPED_UNICODE).PHP_EOL;
} else {
    echo 'NO ASSIGNMENT'.PHP_EOL;
}
