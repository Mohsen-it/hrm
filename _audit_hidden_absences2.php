<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Models\RawAttendanceLog;

function line(string $s): void
{
    echo $s.PHP_EOL;
}

$from = Carbon::today()->subDays(20)->startOfDay();
$to = Carbon::today();

line('=== 1) SESSIONS WITH NULL check_in_at in range (count by day + sample) ===');
$nullSessions = AttendanceSession::whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])
    ->whereNull('check_in_at')
    ->orderByDesc('attendance_date')
    ->get(['id', 'user_id', 'attendance_date', 'check_in_at', 'check_out_at', 'created_at']);
line('total='.$nullSessions->count());
foreach ($nullSessions->take(25) as $s) {
    $name = DB::table('users')->where('id', $s->user_id)->value('name');
    $code = DB::table('users')->where('id', $s->user_id)->value('employee_code');
    line(sprintf('  session=%s user=%s %s(%s) date=%s out=%s created=%s', $s->id, $s->user_id, $name, $code, $s->attendance_date, $s->check_out_at, $s->created_at));
}

// For each NULL-check-in session, does the employee have a raw punch that day?
line('');
line('=== 1b) NULL check_in sessions WITHOUT any raw punch that day ===');
$noRaw = [];
foreach ($nullSessions as $s) {
    $day = $s->attendance_date->toDateString();
    $utcFrom = Carbon::parse($day)->startOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
    $utcTo = Carbon::parse($day)->endOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
    $raw = RawAttendanceLog::query()
        ->where('user_id', $s->user_id)
        ->whereBetween('punch_time', [$utcFrom, $utcTo])
        ->exists();
    if (! $raw) {
        $noRaw[] = ['user_id' => $s->user_id, 'date' => $day, 'session_id' => $s->id];
    }
}
line('count='.count($noRaw));
foreach (array_slice($noRaw, 0, 30) as $r) {
    $name = DB::table('users')->where('id', $r['user_id'])->value('name');
    $code = DB::table('users')->where('id', $r['user_id'])->value('employee_code');
    line(sprintf('  user=%s %s(%s) date=%s (session %s — NO raw punch, counted PRESENT today!)', $r['user_id'], $name, $code, $r['date'], $r['session_id']));
}

line('');
line('=== 2) MULTI-OPEN-ASSIGNMENT EMPLOYEES — all open rows ===');
$multi = DB::table('att_rotation_assignments')
    ->whereNull('end_date')
    ->orWhere('end_date', '>=', $to->toDateString())
    ->selectRaw('employee_id, COUNT(*) as c')
    ->groupBy('employee_id')
    ->havingRaw('COUNT(*) > 1')
    ->pluck('employee_id');
$rows = DB::table('att_rotation_assignments')
    ->whereIn('employee_id', $multi)
    ->where(function ($q) use ($to) {
        $q->whereNull('end_date')->orWhere('end_date', '>=', $to->toDateString());
    })
    ->orderBy('employee_id')
    ->orderByDesc('start_date')
    ->get(['id', 'employee_id', 'rotation_id', 'rotation_group_id', 'start_date', 'end_date', 'created_at', 'updated_at']);
foreach ($rows->groupBy('employee_id') as $eid => $rs) {
    $name = DB::table('users')->where('id', $eid)->value('name');
    $code = DB::table('users')->where('id', $eid)->value('employee_code');
    line(sprintf('[%s] %s (%s)', $eid, $name, $code));
    foreach ($rs as $r) {
        $rot = DB::table('att_rotations')->where('id', $r->rotation_id)->value('name');
        line(sprintf('    row=%s rot=%s(%s) grp=%s start=%s end=%s updated=%s', $r->id, $r->rotation_id, $rot, $r->rotation_group_id, $r->start_date, $r->end_date ?? 'OPEN', $r->updated_at));
    }
}

line('');
line('=== 3) UNASSIGNED ACTIVE EMPLOYEES WHO ACTUALLY PUNCH (working but invisible) ===');
$unassignedIds = DB::table('users')
    ->whereNull('deleted_at')->where('status', 1)->where('is_active_employee', true)
    ->where(fn ($q) => $q->whereNull('termination_date')->orWhere('termination_date', '>=', $from->toDateString()))
    ->pluck('id')
    ->diff(
        DB::table('att_rotation_assignments')
            ->where(function ($q) use ($to) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $to->toDateString());
            })
            ->pluck('employee_id')
    );

$utcFrom = Carbon::parse($from->toDateString())->startOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
$utcTo = Carbon::parse($to->toDateString())->endOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
$punchers = DB::table('raw_attendance_logs')
    ->whereIn('user_id', $unassignedIds)
    ->whereBetween('punch_time', [$utcFrom, $utcTo])
    ->whereNull('deleted_at')
    ->selectRaw('user_id, COUNT(*) as punches, MIN(punch_time) as first_punch, MAX(punch_time) as last_punch')
    ->groupBy('user_id')
    ->orderByDesc('punches')
    ->get();

line('unassigned_total='.$unassignedIds->count().'  with_punches_in_range='.$punchers->count());
foreach ($punchers->take(40) as $p) {
    $name = DB::table('users')->where('id', $p->user_id)->value('name');
    $code = DB::table('users')->where('id', $p->user_id)->value('employee_code');
    line(sprintf('  [%s] %s (%s) punches=%s first=%s last=%s', $p->user_id, $name, $code, $p->punches, substr($p->first_punch, 0, 16), substr($p->last_punch, 0, 16)));
}

line('');
line('=== 4) UNASSIGNED SAMPLE — dept/branch/company distribution ===');
$dist = DB::table('users')
    ->whereIn('id', $unassignedIds)
    ->selectRaw('department_id, COUNT(*) as c')
    ->groupBy('department_id')
    ->orderByDesc('c')
    ->get();
foreach ($dist->take(15) as $d) {
    $dept = DB::table('departments')->where('id', $d->department_id)->value('department_name');
    line(sprintf('  dept=%s (%s): %s', $d->department_id, $dept, $d->c));
}
