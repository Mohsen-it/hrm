<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Modules\Attendance\Models\DailyAttendanceSummary;
use Modules\Attendance\Services\AbsenceCalculationService;
use Modules\Attendance\Services\DailyReportService;

$date = '2026-08-16';
$day = Carbon::parse($date)->startOfDay();
$service = app(DailyReportService::class);
$result = $service->build($date, '08:30', 1);

$restWithSession = $result['rows']->filter(fn ($r) => $r['status'] === 'rest' && $r['check_in'] !== '');
$ids = $restWithSession->pluck('id');

// What do daily summaries say for these employees?
$summaries = DailyAttendanceSummary::whereIn('user_id', $ids)
    ->whereDate('summary_date', $date)
    ->get()->keyBy('user_id');

echo "=== REST-with-session vs daily_attendance_summaries ($date) ===\n";
$counts = ['present' => 0, 'absent' => 0, 'late' => 0, 'incomplete' => 0, 'rest' => 0, 'no_summary' => 0, 'other' => 0];
foreach ($restWithSession as $r) {
    $s = $summaries->get($r['id']);
    if (! $s) {
        $counts['no_summary']++;
    } else {
        $st = $s->status;
        if (isset($counts[$st])) {
            $counts[$st]++;
        } else {
            $counts['other']++;
        }
    }
}
print_r($counts);

echo "\n=== Sample: report status vs summary status ===\n";
foreach ($restWithSession->take(12) as $r) {
    $s = $summaries->get($r['id']);
    echo "  {$r['name']} | report=rest in={$r['check_in']} | summary=".($s ? $s->status.' ('.$s->status_label.')' : 'NONE')."\n";
}

// Also: check employees the summaries say are 'present' but the report does not classify as present/late
echo "\n=== Employees with summary=present on $date but report says otherwise ===\n";
$allIds = $result['rows']->pluck('id');
$allSum = DailyAttendanceSummary::whereIn('user_id', $allIds)->whereDate('summary_date', $date)->get()->keyBy('user_id');
$mismatch = 0;
foreach ($result['rows'] as $r) {
    $s = $allSum->get($r['id']);
    if (! $s) {
        continue;
    }
    if ($s->status === 'present' && ! in_array($r['status'], ['present', 'late'], true)) {
        $mismatch++;
        if ($mismatch <= 12) {
            echo "  {$r['name']} | report={$r['status']} ({$r['check_in']}) | summary=present\n";
        }
    }
}
echo "total mismatches (summary=present, report!=present/late): $mismatch\n";

echo "\n=== Expected set check: are rest-with-session employees expected? ===\n";
$absence = app(AbsenceCalculationService::class);
$expected = $absence->getExpectedEmployees($day, 1)->flip();
$expectedCount = $restWithSession->filter(fn ($r) => $expected->has($r['id']))->count();
echo "rest-with-session who ARE in expected set: $expectedCount / ".$restWithSession->count()."\n";
