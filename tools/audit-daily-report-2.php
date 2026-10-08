<?php

/** تدقيق عميق: الملاحظات، البصمة المسائية، نقص الخروج، بصمة دون جلسة */

use Carbon\Carbon;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Models\RawAttendanceLog;
use Modules\Attendance\Services\DailyReportService;
use Modules\Shifts\Models\ShiftException;
use Modules\Shifts\Repositories\RotationAssignmentRepository;
use Modules\Shifts\Services\AbsenceCalculationService;
use Modules\Vacations\Models\UserVacationRequest;

$date = '2026-10-07';
$prevDate = '2026-10-06';
$cutoff = '09:00';
$branchId = 1;
$departmentIds = [1, 2, 3, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18];

$svc = app(DailyReportService::class);
$report = $svc->build($date, $cutoff, $branchId, $departmentIds, null, null);
$rows = collect($report['rows']);
$errors = [];

$prevSessions = AttendanceSession::onDate($prevDate)->whereIn('user_id', $rows->pluck('id'))->orderBy('check_in_at')->get()->groupBy('user_id');
$prevRaw = RawAttendanceLog::query()
    ->whereIn('user_id', $rows->pluck('id'))
    ->whereBetween('punch_time', [Carbon::parse($prevDate)->startOfDay(), Carbon::parse($prevDate)->endOfDay()])
    ->get(['user_id', 'punch_time'])->groupBy('user_id');
$assignRepo = app(RotationAssignmentRepository::class);
$prevAssignments = $assignRepo->getAssignmentsForDate($prevDate)->keyBy('employee_id');
$absSvc = app(AbsenceCalculationService::class);
$prevExpected = $absSvc->getExpectedEmployees(Carbon::parse($prevDate), $departmentIds)->flip();
$todaySessions = AttendanceSession::onDate($date)->whereIn('user_id', $rows->pluck('id'))->get()->groupBy('user_id');
$todayRaw = RawAttendanceLog::query()
    ->whereIn('user_id', $rows->pluck('id'))
    ->whereBetween('punch_time', [Carbon::parse($date)->startOfDay(), Carbon::parse($date)->endOfDay()])
    ->get(['user_id', 'punch_time'])->groupBy('user_id');

echo "== البصمة الناقصة (incomplete=4) ==\n";
foreach ($rows->where('has_incomplete_punch', true) as $r) {
    $uid = $r['id'];
    $ps = $prevSessions->get($uid, collect());
    $main = $ps->firstWhere(fn ($s) => $s->check_out_at !== null) ?? $ps->firstWhere(fn ($s) => $s->check_in_at !== null);
    $co = $main?->check_out_at ? $main->check_out_at->format('Y-m-d H:i') : 'NULL';
    echo "uid={$uid} {$r['name']}: prevMain check_in=".($main?->check_in_at?->format('H:i') ?? '-')." check_out={$co} prevExpected=".($prevExpected->has($uid) ? 'yes' : 'no')." prev_check_in_col={$r['prev_check_in']} prev_check_out_col={$r['prev_check_out']}\n";
    // التحقق: يجب عدم وجود خروج مكتمل من قلب الجلسة أمس؛ إن وُجد فالعَلَم مشبوه
    if ($main && $main->check_out_at !== null && ! str_contains((string) $main->notes, 'أغلق تلقائياً') && $main->check_out_at->toDateString() === $prevDate) {
        $errors[] = "uid={$uid} flagged incomplete but has genuine checkout {$main->check_out_at} on {$prevDate}";
    }
}

echo "\n== بصمة مسائية ناقصة (evening=3) ==\n";
foreach ($rows->where('has_missing_evening_punch', true) as $r) {
    $uid = $r['id'];
    $pr = collect($prevRaw->get($uid, collect()))->map(fn ($x) => Carbon::parse($x->punch_time)->format('H:i'))->sort()->values();
    echo "uid={$uid} {$r['name']}: prevRawTimes=[".implode(',', $pr->all()).'] prevExpected='.($prevExpected->has($uid) ? 'yes' : 'no')."\n";
}

echo "\n== أعمدة البصمة المسائية (evening_punch non-empty) ==\n";
foreach ($rows->filter(fn ($r) => ! empty($r['evening_punch']))->take(20) as $r) {
    $uid = $r['id'];
    $today = collect($todayRaw->get($uid, collect()))->map(fn ($x) => Carbon::parse($x->punch_time)->format('H:i'))->sort()->values();
    echo "uid={$uid} {$r['name']}: eveningCol={$r['evening_punch']} todayRaw=[".implode(',', $today->all())."] check_in={$r['check_in']} check_out={$r['check_out']} next_day=".($r['check_out_next_day'] ? 'Y' : 'N')."\n";
}

echo "\n== بصمة دون جلسة ==\n";
foreach ($rows->filter(fn ($r) => str_contains((string) $r['notes'], 'بصمة مسجلة دون جلسة'))->take(20) as $r) {
    $uid = $r['id'];
    $today = collect($todayRaw->get($uid, collect()))->map(fn ($x) => Carbon::parse($x->punch_time)->format('H:i'))->sort()->values();
    $hasSession = $todaySessions->get($uid, collect())->contains(fn ($s) => $s->check_in_at !== null);
    echo "uid={$uid} {$r['name']}: status={$r['status']} raw=[".implode(',', $today->all()).'] sessionToday='.($hasSession ? 'yes' : 'no')."\n";
    if ($hasSession) {
        $errors[] = "uid={$uid} has session today but note says raw-without-session";
    }
    if ($today->isEmpty()) {
        $errors[] = "uid={$uid} note raw-without-session but no raw punches today";
    }
}

echo "\n== غير مسجلين بالبصمة ==\n";
echo $rows->where('has_no_fingerprint', true)->count()."\n";

echo "\n== التأخر (3) — تفاصيل ==\n";
foreach ($rows->where('status', 'late') as $r) {
    echo "uid={$r['id']} {$r['name']}: check_in={$r['check_in']} late_minutes={$r['late_minutes']} expected_ci={$r['expected_check_in']} notes={$r['notes']}\n";
}

echo "\n== الغائب الوحيد ==\n";
foreach ($rows->where('status', 'absent') as $r) {
    $uid = $r['id'];
    $today = collect($todayRaw->get($uid, collect()));
    $hasSession = $todaySessions->get($uid, collect())->contains(fn ($s) => $s->check_in_at !== null);
    $vac = UserVacationRequest::approved()->where('user_id', $uid)->overlapping($date, $date)->exists();
    $exc = ShiftException::active()->where('employee_id', $uid)->whereIn('exception_type', ['leave', 'mission', 'training', 'swap'])->overlapping($date)->exists();
    echo "uid={$uid} {$r['name']}: sessionToday=".($hasSession ? 'yes' : 'no')." rawToday={$today->count()} vac={$vac} exc={$exc} expected=".(app(AbsenceCalculationService::class)->getExpectedEmployees(Carbon::parse($date), $departmentIds)->flip()->has($uid) ? 'yes' : 'no')." notes={$r['notes']}\n";
}

echo "\n== ERRORS ==\n".($errors === [] ? "none\n" : implode("\n", $errors)."\n");
