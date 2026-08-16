<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Attendance\Services\DailyReportService;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Shifts\Services\RotationEngine;
use Illuminate\Support\Carbon;

$date = '2026-08-16';
$service = app(DailyReportService::class);
$result = $service->build($date, '08:30', 1);

$restWithSession = $result['rows']->filter(fn ($r) => $r['status'] === 'rest' && $r['check_in'] !== '');

echo "rest-with-session total: ".$restWithSession->count()."\n\n";

// For each, classify the session: overnight rotation? what time? does the engine say previous day was work?
$counts = ['morning_prev_workday' => 0, 'morning_prev_rest' => 0, 'other' => 0, 'admin' => 0];
$engine = app(RotationEngine::class);
foreach ($restWithSession as $r) {
    $sessions = AttendanceSession::onDate($date)->where('user_id', $r['id'])->orderBy('check_in_at')->get();
    $first = $sessions->first();
    $isOvernightRot = str_contains($r['rotation'] ?? '', 'دورية 1-3')
        || str_contains($r['rotation'] ?? '', 'دورية 3-9')
        || str_contains($r['rotation'] ?? '', 'دورية 7-21');
    $time = $first?->check_in_at?->format('H:i') ?? '??';
    $isMorning = $time >= '05:00' && $time <= '12:00';
    if ($isOvernightRot && $isMorning) $counts['morning_prev_workday']++;
    elseif ($isOvernightRot) $counts['other']++;
    else $counts['admin']++;
}
print_r($counts);

echo "\n=== all rest-with-session rows (rotation | time) ===\n";
foreach ($restWithSession as $r) {
    echo "  {$r['name']} | {$r['rotation']} | in={$r['check_in']}\n";
}
