<?php

/**
 * READ-ONLY audit — scans the real database for hidden absence cases and
 * correctness issues in the smart-absence calculation, mirroring the exact
 * rules of AbsenceCalculationService so any mismatch is a genuine finding.
 *
 * Usage: php _audit_hidden_absences.php [daysBack]   (default 14)
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Models\RawAttendanceLog;
use Modules\Holidays\Models\Holiday;
use Modules\Shifts\Repositories\RotationAssignmentRepository;
use Modules\Shifts\Services\AbsenceCalculationService;
use Modules\Shifts\Services\RotationEngine;
use Modules\Vacations\Models\UserVacationRequest;

function line(string $s): void
{
    echo $s.PHP_EOL;
}

$daysBack = isset($argv[1]) ? max(1, (int) $argv[1]) : 14;
$service = app(AbsenceCalculationService::class);
$engine = app(RotationEngine::class);
$repo = app(RotationAssignmentRepository::class);

$from = Carbon::today()->subDays($daysBack - 1)->startOfDay();
$to = Carbon::today();
$timezone = config('app.timezone');

line('AUDIT RANGE: '.$from->toDateString().' .. '.$to->toDateString().'  (now='.Carbon::now()->format('Y-m-d H:i:s').' '.$timezone.')');
line('');

$hidden = [];        // employee_id => ['days' => [date => reason]]
$falseAbsent = [];   // employee_id => ['days' => [date => detail]]
$unassigned = [];
$multiAssign = [];

// ---- Roster-level scans (once) -------------------------------------------
$allActive = DB::table('users')
    ->whereNull('deleted_at')
    ->where('status', 1)
    ->where('is_active_employee', true)
    ->where(fn ($q) => $q->whereNull('termination_date')->orWhere('termination_date', '>=', $from->toDateString()))
    ->get(['id', 'name', 'employee_code', 'department_id', 'branch_id']);

$latest = $repo->getLatestActiveAssignments();
$assignedIds = $latest->pluck('employee_id')->flip();
$unassigned = $allActive->filter(fn ($u) => ! $assignedIds->has($u->id));

// Employees with more than one currently-open assignment row.
$multiAssign = DB::table('att_rotation_assignments')
    ->whereNull('end_date')
    ->orWhere('end_date', '>=', $to->toDateString())
    ->selectRaw('employee_id, COUNT(*) as c')
    ->groupBy('employee_id')
    ->havingRaw('COUNT(*) > 1')
    ->orderByDesc('c')
    ->get()
    ->map(function ($r) use ($allActive) {
        $u = $allActive->firstWhere('id', $r->employee_id);
        $r->name = $u?->name;
        $r->employee_code = $u?->employee_code;

        return $r;
    });

// ---- Per-day scan ---------------------------------------------------------
$current = $from->copy();
while ($current->lte($to)) {
    $day = $current->toDateString();
    $expected = $service->getExpectedEmployees($current);
    $absent = $service->getAbsentEmployees($current);
    $breakdown = $service->getDailyStatusBreakdown($current);

    // Reconciliation invariant.
    $sum = $breakdown['present'] + $breakdown['absent'] + $breakdown['on_vacation']
        + $breakdown['on_exception'] + $breakdown['incomplete'] + $breakdown['holiday']
        + $breakdown['awaiting_arrival'];
    $ok = ($expected->count() === $sum) && ($breakdown['absent'] === $absent->count());
    line(sprintf('%-10s expected=%-4d sum=%-4d absent=%-4d %s', $day, $expected->count(), $sum, $absent->count(), $ok ? 'OK' : '*** RECONCILIATION FAIL ***'));

    if (! $ok) {
        line('  breakdown='.json_encode($breakdown, JSON_UNESCAPED_UNICODE));
    }

    // Bulk data for the day (same rules as the service).
    $sessionsDay = AttendanceSession::onDate($day)->get(['user_id', 'check_in_at']);
    $utcFrom = Carbon::parse($day)->startOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
    $utcTo = Carbon::parse($day)->endOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
    $rawDay = RawAttendanceLog::query()
        ->whereBetween('punch_time', [$utcFrom, $utcTo])
        ->get(['user_id', 'punch_time', 'punch_type', 'device_id', 'deleted_at']);

    $vacDay = UserVacationRequest::where('status', UserVacationRequest::STATUS_APPROVED)
        ->whereIn('user_id', $expected->toArray())
        ->whereDate('start_date', '<=', $day)
        ->whereDate('end_date', '>=', $day)
        ->pluck('user_id')->flip();

    $excDay = DB::table('att_shift_exceptions')
        ->whereIn('employee_id', $expected->toArray())
        ->whereIn('exception_type', ['leave', 'mission', 'swap', 'training'])
        ->where('status', 'active')
        ->whereDate('from_date', '<=', $day)
        ->whereDate('to_date', '>=', $day)
        ->pluck('employee_id')->flip();

    $holidays = Holiday::active()->get();
    $usersMeta = DB::table('users')
        ->whereIn('id', $expected->toArray())
        ->get(['id', 'branch_id', 'department_id'])
        ->keyBy('id');
    $assignments = $latest->keyBy('employee_id');
    $now = Carbon::now();

    foreach ($expected as $employeeId) {
        if ($absent->contains($employeeId)) {
            // False-absent scan: in the absent list but has physical presence.
            $hasSession = $sessionsDay->contains(fn ($s) => (int) $s->user_id === $employeeId);
            $hasRaw = $rawDay->whereNull('deleted_at')->contains(fn ($r) => (int) $r->user_id === $employeeId);
            if ($hasSession || $hasRaw) {
                $detail = [];
                $s = $sessionsDay->firstWhere('user_id', $employeeId);
                if ($s) {
                    $detail[] = 'session_in='.($s->check_in_at ?? 'NULL');
                }
                $raw = $rawDay->whereNull('deleted_at')->filter(fn ($r) => (int) $r->user_id === $employeeId);
                foreach ($raw as $r) {
                    $detail[] = 'raw='.substr($r->punch_time, 0, 16).'(dev'.$r->device_id.','.$r->punch_type.')';
                }
                $falseAbsent[$employeeId]['days'][$day] = implode(' | ', $detail);
            }

            continue;
        }

        // Hidden-absence scan: expected, NOT in absent list.
        $hasPunch = $sessionsDay->contains(fn ($s) => (int) $s->user_id === $employeeId)
            || $rawDay->whereNull('deleted_at')->contains(fn ($r) => (int) $r->user_id === $employeeId);
        if ($hasPunch) {
            continue;
        }
        if ($vacDay->has($employeeId) || $excDay->has($employeeId)) {
            continue;
        }

        $assignment = $assignments->get($employeeId);
        // Holiday coverage (replicates hasApplicableHoliday).
        $isHoliday = false;
        if ($assignment && ! (bool) $assignment->rotation?->work_on_holidays) {
            $emp = $usersMeta->get($employeeId);
            foreach ($holidays as $h) {
                $dur = max(1, (int) $h->duration_days);
                $anchor = $h->is_recurring
                    ? Carbon::createFromDate((int) $current->year, (int) $h->recurring_month, (int) $h->recurring_day)
                    : $h->date?->copy()->startOfDay();
                if ($anchor && Carbon::parse($day)->betweenIncluded($anchor, $anchor->copy()->addDays($dur - 1))) {
                    if ($h->applies_to_all
                        || in_array((int) $emp?->branch_id, $h->applies_to_branches ?? [], true)
                        || in_array((int) $emp?->department_id, $h->applies_to_departments ?? [], true)) {
                        $isHoliday = true;
                        break;
                    }
                }
            }
        }
        if ($isHoliday) {
            continue;
        }

        // Deadline (awaiting-arrival rule).
        $deadlinePassed = true;
        $expectedIn = null;
        if ($assignment) {
            $times = $engine->resolveTimes($assignment);
            $expectedIn = $times['check_in'] ?? null;
            $grace = (int) ($assignment->rotation?->grace_minutes ?: $times['late_margin'] ?: 0);
            $deadline = $expectedIn
                ? $current->copy()->setTimeFromTimeString($expectedIn)->addMinutes($grace)
                : $current->copy()->endOfDay();
            $deadlinePassed = $now->greaterThan($deadline);
        }

        if ($deadlinePassed) {
            $reason = 'expected, no punch, no coverage, deadline passed';
            if (! $expectedIn) {
                $reason .= ' [no time schedule -> EOD deadline]';
            }
            $hidden[$employeeId]['days'][$day] = $reason;
        }
    }

    $current->addDay();
}

// ---- Reporting ------------------------------------------------------------
line('');
line('==================================================================');
line('HIDDEN ABSENCES (expected, no punch, no coverage, deadline passed,');
line('but NOT in the absent list)');
line('==================================================================');
if ($hidden === []) {
    line('  NONE');
} else {
    $names = DB::table('users')->whereIn('id', array_keys($hidden))->get(['id', 'name', 'employee_code'])->keyBy('id');
    foreach ($hidden as $id => $info) {
        $u = $names->get($id);
        line(sprintf('  [%s] %s (%s)', $id, $u?->name, $u?->employee_code));
        foreach ($info['days'] as $d => $reason) {
            line('      '.$d.': '.$reason);
        }
    }
}

line('');
line('==================================================================');
line('FALSE ABSENTS (in the absent list but with a session/raw punch that');
line('day) — potential timezone / session-attribution bug');
line('==================================================================');
if ($falseAbsent === []) {
    line('  NONE');
} else {
    $names = DB::table('users')->whereIn('id', array_keys($falseAbsent))->get(['id', 'name', 'employee_code'])->keyBy('id');
    foreach ($falseAbsent as $id => $info) {
        $u = $names->get($id);
        line(sprintf('  [%s] %s (%s)', $id, $u?->name, $u?->employee_code));
        foreach ($info['days'] as $d => $detail) {
            line('      '.$d.': '.$detail);
        }
    }
}

line('');
line('==================================================================');
line('ACTIVE EMPLOYEES WITH NO ROTATION ASSIGNMENT (invisible to the');
line('report — never expected, never absent)');
line('==================================================================');
line('  count='.$unassigned->count());
foreach ($unassigned->take(40) as $u) {
    line(sprintf('    [%s] %s (%s) dept=%s', $u->id, $u->name, $u->employee_code, $u->department_id));
}

line('');
line('==================================================================');
line('EMPLOYEES WITH MULTIPLE CONCURRENT OPEN ASSIGNMENTS');
line('==================================================================');
if ($multiAssign->isEmpty()) {
    line('  NONE');
} else {
    foreach ($multiAssign as $m) {
        line(sprintf('    [%s] %s (%s) open_rows=%s', $m->employee_id, $m->name, $m->employee_code, $m->c));
    }
}

line('');
line('==================================================================');
line('SESSIONS WITH NULL check_in_at on the audited days (would wrongly');
line('count as present in smart absence)');
line('==================================================================');
$nullSessions = AttendanceSession::whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])
    ->whereNull('check_in_at')
    ->count();
line('  count='.$nullSessions);

line('');
line('SUMMARY: hidden='.count($hidden).' falseAbsent='.count($falseAbsent)
    .' unassigned='.$unassigned->count().' multiAssign='.$multiAssign->count()
    .' nullCheckInSessions='.$nullSessions);
