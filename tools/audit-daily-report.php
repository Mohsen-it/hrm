<?php

/**
 * المستقل: مدقق مستقل للتقرير اليومي
 *
 * هذا السكربت يعيد بناء التقرير من DailyReportService (نفس ما يظهر للصفحة)،
 * ثم يعيد اشتقاق كل حقل حرج بشكل مستقل مباشرة من جداول قاعدة البيانات
 * ويقارنها حقلاً بحقلاً. أي اختلاف يُسجَّل كخطأ تدقيق.
 */

use Carbon\Carbon;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Models\RawAttendanceLog;
use Modules\Attendance\Services\DailyReportService;
use Modules\Holidays\Models\Holiday;
use Modules\Shifts\Models\ShiftException;
use Modules\Shifts\Repositories\RotationAssignmentRepository;
use Modules\Shifts\Services\AbsenceCalculationService;
use Modules\Users\Models\User;
use Modules\Vacations\Models\UserVacationRequest;

$date = '2026-10-07';
$cutoff = '09:00';
$branchId = 1;
$departmentIds = [1, 2, 3, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18];

/** @var DailyReportService $svc */
$svc = app(DailyReportService::class);
$report = $svc->build($date, $cutoff, $branchId, $departmentIds, null, null);
$rows = collect($report['rows']);
$stats = $report['stats'];

$errors = [];
$warnings = [];

/* ------------------------------------------------------------------
 | 1. التحقق من صحة الإحصائيات الداخلية
 * ------------------------------------------------------------------ */
$statusSum = ($stats['present'] ?? 0) + ($stats['late'] ?? 0) + ($stats['absent'] ?? 0)
    + ($stats['leave'] ?? 0) + ($stats['mission'] ?? 0) + ($stats['rest'] ?? 0)
    + ($stats['holiday'] ?? 0) + ($stats['awaiting'] ?? 0) + ($stats['unassigned'] ?? 0);
// absent excludes no-fingerprint employees
$noFpCount = $rows->where('has_no_fingerprint', true)->count();
$absentWithFp = $rows->where('status', 'absent')->where('has_no_fingerprint', false)->count();
if ($absentWithFp !== $stats['absent']) {
    $errors[] = "stats.absent({$stats['absent']}) != absent-with-fp rows({$absentWithFp})";
}
if ($noFpCount !== $stats['no_fingerprint']) {
    $errors[] = "stats.no_fingerprint({$stats['no_fingerprint']}) != no-fp rows({$noFpCount})";
}
if ($statusSum !== $stats['total']) {
    // statuses other than absent may include no-fp employees; check raw status sum
    $rawStatusSum = $rows->countBy('status');
    $sum = $rawStatusSum->except(['absent'])->sum() + $absentWithFp;
    if ($sum !== $stats['total']) {
        $errors[] = "stats.total({$stats['total']}) != recomputed status sum({$sum}); byStatus=".json_encode($rawStatusSum);
    }
}
if ($rows->where('has_incomplete_punch', true)->count() !== $stats['incomplete']) {
    $errors[] = 'stats.incomplete mismatch';
}
if ($rows->where('has_missing_evening_punch', true)->count() !== $stats['evening']) {
    $errors[] = 'stats.evening mismatch';
}
if ($rows->count() !== $stats['total']) {
    $errors[] = "rows count {$rows->count()} != stats.total {$stats['total']}";
}
echo "== stats ==\n".json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n";

/* ------------------------------------------------------------------
 | 2. التحقق من اكتمال قائمة الموظفين (roster) بشكل مستقل
 * ------------------------------------------------------------------ */
$expectedRoster = User::query()->employees()->active()
    ->where(fn ($q) => $q->whereNull('termination_date')->orWhere('termination_date', '>=', $date))
    ->where(fn ($q) => $q->whereNull('hire_date')->orWhere('hire_date', '<=', $date))
    ->where(fn ($q) => $q->whereNull('attendance_exemption_type')
        ->orWhereNull('attendance_exemption_from')
        ->orWhere('attendance_exemption_from', '>', $date)
        ->orWhere('attendance_exemption_to', '<', $date))
    ->where('branch_id', $branchId)
    ->whereIn('department_id', $departmentIds)
    ->orderBy('name')->pluck('id');
$rowIds = $rows->pluck('id')->sort()->values();
if ($expectedRoster->sort()->values()->toJson() !== $rowIds->toJson()) {
    $missing = $expectedRoster->diff($rowIds);
    $extra = $rowIds->diff($expectedRoster);
    $errors[] = 'roster mismatch: missing='.$missing->implode(',').' extra='.$extra->implode(',').' counts='.$expectedRoster->count().'/'.$rowIds->count();
} else {
    echo "roster OK: {$rowIds->count()} employees\n";
}

/* ------------------------------------------------------------------
 | 3. التحقق المستقل من كل سطر: الحالة والأوقات والتأخير
 * ------------------------------------------------------------------ */
$sessionsByUser = AttendanceSession::onDate($date)->whereIn('user_id', $rowIds)->orderBy('check_in_at')->get()->groupBy('user_id');
$rawByUser = RawAttendanceLog::query()
    ->whereIn('user_id', $rowIds)
    ->whereBetween('punch_time', [Carbon::parse($date)->startOfDay(), Carbon::parse($date)->endOfDay()])
    ->get(['user_id', 'punch_time'])
    ->groupBy('user_id');

$approvedVacations = UserVacationRequest::approved()->whereIn('user_id', $rowIds)
    ->overlapping($date, $date)->with('vacationType')->get()->keyBy('user_id');
$activeExceptions = ShiftException::active()->whereIn('employee_id', $rowIds)
    ->whereIn('exception_type', ['leave', 'mission', 'training', 'swap'])
    ->overlapping($date)->get()->groupBy('employee_id');

$expectedIds = app(AbsenceCalculationService::class)->getExpectedEmployees(Carbon::parse($date), $departmentIds)->flip();

// استثناءات الإعفاء من الحضور
$exempted = User::query()->whereIn('id', $rowIds)->whereNotNull('attendance_exemption_type')
    ->where('attendance_exemption_from', '<=', $date)->where(fn ($q) => $q->whereNull('attendance_exemption_to')->orWhere('attendance_exemption_to', '>=', $date))
    ->pluck('id');

foreach ($rows as $row) {
    $uid = $row['id'];
    $sess = $sessionsByUser->get($uid, collect());
    $main = $sess->firstWhere(fn ($s) => $s->check_in_at !== null);
    $vac = $approvedVacations->get($uid);
    $exc = $activeExceptions->get($uid, collect())->first();
    $rawPunches = $rawByUser->get($uid, collect());

    // التحقق من الدخول/الخروج في السطر مقابل جدول الجلسات
    if ($main) {
        $ci = $main->check_in_at?->format('H:i') ?? '';
        if ($row['check_in'] !== $ci) {
            $errors[] = "uid={$uid} check_in row='{$row['check_in']}' db='{$ci}'";
        }
        $co = $main->check_out_at ? $main->check_out_at->format('H:i') : '';
        if ($row['check_out'] !== $co && ! $row['check_out_next_day']) {
            // overnight: check_out may be next day; the row shows HH:mm of that day
            if ($row['check_out'] !== ($main->check_out_at?->format('H:i') ?? '')) {
                $errors[] = "uid={$uid} check_out row='{$row['check_out']}' db='".($main->check_out_at?->format('H:i') ?? '')."'";
            }
        }
    } else {
        if ($row['check_in'] !== '') {
            $errors[] = "uid={$uid} row has check_in '{$row['check_in']}' but no session check-in";
        }
    }

    // التحقق من الحالة وفق قواعد مستقلة
    $hasCheckIn = $main !== null;
    $hasRaw = $rawPunches->isNotEmpty();
    $expected = $expectedIds->has($uid);
    $isMissionType = $exc?->exception_type === 'mission' || ($vac && isMissionType($vac));
    $onLeaveType = $vac !== null || in_array($exc?->exception_type, ['leave', 'training', 'swap'], true);

    $expectedStatus = null;
    if ($expected && ! $hasCheckIn && $isMissionType) {
        $expectedStatus = 'mission';
    } elseif ($expected && ! $hasCheckIn && $onLeaveType) {
        $expectedStatus = 'leave';
    } elseif (! $hasCheckIn && ! $hasRaw) {
        // قد تكون rest/holiday/awaiting/absent/unassigned
        if ($row['status'] === 'present' || $row['status'] === 'late') {
            $errors[] = "uid={$uid} status '{$row['status']}' but no session and no raw punch";
        }
    }
    if ($expectedStatus !== null && $row['status'] !== $expectedStatus) {
        $errors[] = "uid={$uid} status row='{$row['status']}' expected '{$expectedStatus}' exc=".($exc?->exception_type).' vac='.($vac?->vacation_type_id);
    }

    // late_minutes = الفرق بين وقت الدخول وعتبة التأخر
    if ($row['status'] === 'late' && $main) {
        $threshold = $cutoff;
        $deadline = app(AbsenceCalculationService::class)->arrivalDeadline(
            Carbon::parse($date),
            app(RotationAssignmentRepository::class)->getAssignmentsForDate($date)->where('employee_id', $uid)->first()
        )?->format('H:i');
        if ($deadline !== null && $deadline > $threshold) {
            $threshold = $deadline;
        }
        $expectedLate = (int) Carbon::parse($date.' '.$threshold)->diffInMinutes($main->check_in_at, true);
        if ((int) $row['late_minutes'] !== $expectedLate) {
            $errors[] = "uid={$uid} late_minutes row={$row['late_minutes']} expected={$expectedLate} threshold={$threshold}";
        }
    }

    // حاضر: تأكد أن check_in <= lateThreshold منطقياً (وإلا يجب أن تكون متأخر)
    if ($row['status'] === 'present' && $main) {
        if ($main->check_in_at->format('H:i') > $cutoff) {
            // قد تكون العتبة الشخصية أكبر (دوران 10:00) — تحقق
            $deadline = app(AbsenceCalculationService::class)->arrivalDeadline(
                Carbon::parse($date),
                app(RotationAssignmentRepository::class)->getAssignmentsForDate($date)->where('employee_id', $uid)->first()
            )?->format('H:i');
            if ($deadline === null || $main->check_in_at->format('H:i') > $deadline) {
                $errors[] = "uid={$uid} present but check_in {$main->check_in_at->format('H:i')} > cutoff {$cutoff} and deadline ".($deadline ?? 'null');
            }
        }
    }
}

function isMissionType($vac): bool
{
    $t = $vac->vacationType;
    if (! $t) {
        return false;
    }
    $s = mb_strtolower(($t->code ?? '').' '.($t->name_ar ?? '').' '.($t->name_en ?? ''));

    return str_contains($s, 'مهم') || str_contains($s, 'mission') || str_contains($s, 'travel');
}

echo "\n== row checks done ==\n";

/* ------------------------------------------------------------------
 | 4. تقرير موجز للحالات الخاصة
 * ------------------------------------------------------------------ */
foreach (['late', 'absent', 'leave', 'mission', 'awaiting', 'holiday', 'unassigned'] as $st) {
    $subset = $rows->where('status', $st);
    echo "\n-- $st ({$subset->count()}): ";
    foreach ($subset->take(10) as $r) {
        echo "#{$r['id']} {$r['name']}[{$r['check_in']}/{$r['check_out']}]; ";
    }
}

echo "\n\n== ERRORS ==\n";
echo $errors === [] ? "none\n" : implode("\n", array_slice($errors, 0, 100))."\n(".count($errors)." total)\n";
echo "\n== WARNINGS ==\n";
echo $warnings === [] ? "none\n" : implode("\n", $warnings)."\n";
