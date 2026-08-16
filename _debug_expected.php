<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Carbon;
use Modules\Users\Models\User;
use Modules\Shifts\Repositories\RotationAssignmentRepository;
use Modules\Shifts\Services\AbsenceCalculationService;
use Modules\Shifts\Services\RotationEngine;
use Modules\Attendance\Models\AttendanceSession;

$date = '2026-08-16';
$day = Carbon::parse($date)->startOfDay();

$names = ['أحمد عبد القادر صالح', 'اسراء علاء حج علي'];
foreach ($names as $name) {
    $user = User::where('name', $name)->first();
    if (!$user) { echo "USER NOT FOUND: $name\n"; continue; }
    echo "===== $name (id={$user->id}) =====\n";

    $repo = app(RotationAssignmentRepository::class);
    $latest = $repo->getLatestActiveAssignments()->firstWhere('employee_id', $user->id);
    if ($latest) {
        $rotation = $latest->rotation;
        $group = $latest->rotationGroup;
        echo "  latest active assignment: rotation={$rotation->id} name={$rotation->name} group={$group->id} name={$group->name}\n";
        echo "  rotation->is_active={$rotation->is_active} start={$latest->start_date} end={$latest->end_date}\n";
        $engine = app(RotationEngine::class);
        $isWork = $engine->isWorkDay($rotation, $group, $day);
        echo "  isWorkDay(2026-08-16) = " . var_export($isWork, true) . "\n";
        // try other dates in the week
        foreach (['2026-08-13','2026-08-14','2026-08-15','2026-08-16','2026-08-17'] as $d) {
            echo "    isWorkDay($d) = " . var_export($engine->isWorkDay($rotation, $group, Carbon::parse($d)), true) . "\n";
        }
    } else {
        echo "  NO latest active assignment!\n";
    }

    $assignments = $repo->getAssignmentsForDate($date)->where('employee_id', $user->id);
    echo "  getAssignmentsForDate($date) count=" . $assignments->count() . "\n";
    foreach ($assignments as $a) {
        echo "    assignment id={$a->id} rotation_id={$a->rotation_id} group_id={$a->rotation_group_id} start={$a->start_date} end={$a->end_date}\n";
    }

    // sessions on that date
    $s = AttendanceSession::where('user_id', $user->id)->whereDate('check_in_at', $date)->get();
    echo "  sessions on $date: " . $s->count() . "\n";
    foreach ($s->take(3) as $ss) {
        echo "    session id={$ss->id} in={$ss->check_in_at} out=" . ($ss->check_out_at ?? 'NULL') . " duty={$ss->duty_date}\n";
    }
    echo "\n";
}
