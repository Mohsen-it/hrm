<?php

use Modules\Attendance\Services\DailyReportService;

$rows = DB::table('raw_attendance_logs')->where('user_id', 16586)->whereBetween('punch_time', ['2026-10-06 00:00:00', '2026-10-07 00:00:00'])->orderBy('punch_time')->pluck('punch_time');
foreach ($rows as $r) {
    echo $r.PHP_EOL;
}
echo '--- row in page:'.PHP_EOL;
$report = app(DailyReportService::class)->build('2026-10-07', '09:00', 1, [1, 2, 3, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18], null, null);
$row = collect($report['rows'])->firstWhere('id', 16586);
echo 'status='.$row['status'].' check_in='.$row['check_in'].' evening='.$row['evening_punch'].' has_evening_missing='.var_export($row['has_missing_evening_punch'], true).' notes='.$row['notes'].PHP_EOL;
