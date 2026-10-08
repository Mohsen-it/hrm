<?php

/** تدقيق نهائي: rest/leave/mission/late-month-counts */

use Carbon\Carbon;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Services\DailyReportService;
use Modules\Shifts\Models\ShiftException;
use Modules\Shifts\Repositories\RotationAssignmentRepository;
use Modules\Shifts\Services\AbsenceCalculationService;
use Modules\Shifts\Services\RotationEngine;
use Modules\Vacations\Models\UserVacationRequest;

$date = '2026-10-07';
$monthFrom = '2026-10-01';
$svc = app(DailyReportService::class);
$report = $svc->build($date, '09:00', 1, [1, 2, 3, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18], null, null);
$rows = collect($report['rows']);
$assignRepo = app(RotationAssignmentRepository::class);
$engine = app(RotationEngine::class);
$errors = [];

echo "== عينة عشوائية من rest: هل فعلاً لديهم إسناد وهل يومهم راحة؟ ==\n";
$restRows = $rows->where('status', 'rest')->shuffle()->take(10);
foreach ($restRows as $r) {
    $a = $assignRepo->getAssignmentsForDate($date)->where('employee_id', $r['id'])->unique('employee_id')->first();
    $wd = $a && $a->rotation && $a->rotationGroup ? $engine->isWorkDay($a->rotation, $a->rotationGroup, $date) : null;
    $ok = $a !== null && $wd === false;
    echo ($ok ? 'OK  ' : 'BAD ')."uid={$r['id']} {$r['name']}: assignment=".($a ? 'y' : 'N!').' workDay='.var_export($wd, true)."\n";
    if (! $ok) {
        $errors[] = "uid={$r['id']}";
    }
}

echo "\n== كل سطر leave: تأكد من إجازة معتمدة تغطي اليوم ==\n";
foreach ($rows->where('status', 'leave') as $r) {
    $v = UserVacationRequest::approved()->where('user_id', $r['id'])->overlapping($date, $date)->exists();
    $e = ShiftException::active()->where('employee_id', $r['id'])->whereIn('exception_type', ['leave', 'training', 'swap'])->overlapping($date)->exists();
    echo ($v || $e ? 'OK  ' : 'BAD ')."uid={$r['id']} {$r['name']}: vacation=".($v ? 'y' : 'n').' exception='.($e ? 'y' : 'n').'\n';
    if (! ($v || $e)) {
        $errors[] = "leave no-source uid={$r['id']}";
    }
}

echo "\n== mission ==\n";
foreach ($rows->where('status', 'mission') as $r) {
    $e = ShiftException::active()->where('employee_id', $r['id'])->where('exception_type', 'mission')->overlapping($date)->exists();
    $v = UserVacationRequest::approved()->where('user_id', $r['id'])->overlapping($date, $date)->with('vacationType')->first();
    $isM = $v && (str_contains(mb_strtolower(($v->vacationType->code ?? '').($v->vacationType->name_ar ?? '').($v->vacationType->name_en ?? '')), 'مهم') || str_contains(mb_strtolower(($v->vacationType->code ?? '').($v->vacationType->name_ar ?? '').($v->vacationType->name_en ?? '')), 'mission'));
    echo ($e || $isM ? 'OK  ' : 'BAD ')."uid={$r['id']} {$r['name']}\n";
}

echo "\n== تحقق مستقل من عدد أيام التأخر بالشهر ==\n";
foreach ($rows->where('status', 'late') as $r) {
    $sessions = AttendanceSession::betweenDates($monthFrom, $date)->where('user_id', $r['id'])->whereNotNull('check_in_at')->get();
    $lateDays = [];
    foreach ($sessions as $s) {
        $d = $s->attendance_date?->toDateString();
        if (! $d) {
            continue;
        }
        $thr = '09:00';
        $a = $assignRepo->getAssignmentsForDate($d)->where('employee_id', $r['id'])->unique('employee_id')->first();
        $deadline = app(AbsenceCalculationService::class)->arrivalDeadline(Carbon::parse($d), $a)?->format('H:i');
        if ($deadline !== null && $deadline > $thr) {
            $thr = $deadline;
        }
        if ($s->check_in_at->format('H:i') > $thr) {
            $lateDays[$d] = true;
        }
    }
    $n = count($lateDays);
    preg_match('/عدد مرات التأخر خلال الشهر: ‏?([٠-٩0-9]+)/u', $r['notes'], $m);
    $noteN = $m ? (int) strtr($m[1], ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']) : null;
    echo ($n === $noteN ? 'OK  ' : 'BAD ')."uid={$r['id']} {$r['name']}: recomputed={$n} note=".($noteN ?? '-')."\n";
    if ($n !== $noteN) {
        $errors[] = "uid={$r['id']} lateMonth {$n} vs note {$noteN}";
    }
}

echo "\n== تحقق مستقل من عدد أيام الغياب بالشهر (الغائب الوحيد) ==\n";
foreach ($rows->where('status', 'absent') as $r) {
    echo "uid={$r['id']} note={$r['notes']}\n";
}

echo "\n== ERRORS ==\n".($errors === [] ? "none\n" : implode("\n", $errors)."\n");
