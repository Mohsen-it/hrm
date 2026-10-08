<?php

/** التحقق العميق من أعمدة البصمة المسائية وعَلَم نقصها */

use Carbon\Carbon;
use Modules\Attendance\Models\RawAttendanceLog;
use Modules\Attendance\Services\DailyReportService;
use Modules\Shifts\Repositories\RotationAssignmentRepository;
use Modules\Shifts\Services\RotationEngine;
use ReflectionClass;

$date = '2026-10-07';
$prevDate = '2026-10-06';
$svc = app(DailyReportService::class);
$report = $svc->build($date, '09:00', 1, [1, 2, 3, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18], null, null);
$rows = collect($report['rows']);

$engine = app(RotationEngine::class);
$assignRepo = app(RotationAssignmentRepository::class);
$refl = new ReflectionClass(DailyReportService::class);
$mThird = $refl->getMethod('thirdPunchWindow');
$mThird->setAccessible(true);
$mEvening = $refl->getMethod('isEveningPresence');
$mEvening->setAccessible(true);
$mThreshold = $refl->getMethod('eveningThreshold');
$mThreshold->setAccessible(true);
$mOvernight = $refl->getMethod('isAssignmentOvernight');
$mOvernight->setAccessible(true);

$assignFor = fn ($uid, $d) => $assignRepo->getAssignmentsForDate($d)->where('employee_id', $uid)->unique('employee_id')->first();

echo "== لكل موظف عليه عَلَم بصمة مسائية ناقصة: هل ما سجّله أمس يُعد مسائياً؟ ==\n";
foreach ($rows->where('has_missing_evening_punch', true) as $r) {
    $uid = $r['id'];
    $a = $assignFor($uid, $prevDate);
    $window = $mThird->invoke($svc, $a);
    $threshold = $mThreshold->invoke($svc, $a);
    $overnight = $mOvernight->invoke($svc, $a);
    $times = collect(RawAttendanceLog::query()->where('user_id', $uid)
        ->whereBetween('punch_time', [Carbon::parse($prevDate)->startOfDay(), Carbon::parse($prevDate)->endOfDay()])
        ->pluck('punch_time'))->map(fn ($t) => Carbon::parse($t)->format('H:i'))->sort()->values();
    echo "uid={$uid} {$r['name']}: overnight=".($overnight ? 'Y' : 'N').' window='.($window ? "{$window['start']}-{$window['end']}" : 'null')." threshold={$threshold} prevTimes=[".implode(',', $times->all()).'] → eveningDetected='.($times->contains(fn ($t) => $mEvening->invoke($svc, $t, $a)) ? 'YES' : 'NO').'\n';
}

echo "\n== لكل سطر له evening_punch غير فارغة: أعد الحساب وأقارن ==\n";
$errors = [];
foreach ($rows->filter(fn ($r) => ! empty($r['evening_punch'])) as $r) {
    $uid = $r['id'];
    $a = $assignFor($uid, $date);
    $times = collect(RawAttendanceLog::query()->where('user_id', $uid)
        ->whereBetween('punch_time', [Carbon::parse($date)->startOfDay(), Carbon::parse($date)->endOfDay()])
        ->pluck('punch_time'))->map(fn ($t) => Carbon::parse($t)->format('H:i'))->sort()->values();
    $window = $mThird->invoke($svc, $a);
    $threshold = $mThreshold->invoke($svc, $a);
    $ci = $r['check_in'];
    $coSameDay = (! $r['check_out_next_day'] && $r['check_out'] !== '') ? $r['check_out'] : null;
    $expected = $times->filter(fn ($t) => $t !== $ci && $t !== $coSameDay)
        ->filter(fn ($t) => $mEvening->invoke($svc, $t, $a))->last();
    if ($expected !== $r['evening_punch']) {
        $errors[] = "uid={$uid} row={$r['evening_punch']} recomputed=".($expected ?? 'null').' times=['.implode(',', $times->all()).']';
    }
}
echo $errors === [] ? 'كل القيم مطابقة ✓ ('.$rows->filter(fn ($r) => ! empty($r['evening_punch']))->count()." سطراً)\n" : implode("\n", $errors)."\n";

echo "\n== عينة من اللقطات الأساسية لأعمدة الوقت المتوقعة ==\n";
foreach ($rows->take(5) as $r) {
    echo "uid={$r['id']}: expected_ci={$r['expected_check_in']} expected_co={$r['expected_check_out']} overnight_co_next=".($r['expected_check_out_next_day'] ? 'Y' : 'N')."\n";
}
