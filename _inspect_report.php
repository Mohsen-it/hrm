<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Modules\Attendance\Services\DailyReportService;

$service = app(DailyReportService::class);

foreach (['2026-08-16', '2026-08-15', '2026-08-14'] as $date) {
    $result = $service->build($date, '08:30', 1);
    echo "===== DATE $date =====\n";
    echo 'STATS: '.json_encode($result['stats'], JSON_UNESCAPED_UNICODE)."\n";
    echo 'TOTAL ROWS: '.$result['rows']->count()."\n";
    $byStatus = $result['rows']->groupBy('status');
    foreach ($byStatus as $status => $group) {
        echo "--- $status ({$group->count()}) ---\n";
        foreach ($group->take(4) as $row) {
            echo "  {$row['name']} | {$row['department_name']} | {$row['rotation']} | in={$row['check_in']} out={$row['check_out']} | exp_in={$row['expected_check_in']} exp_out={$row['expected_check_out']} | {$row['notes']}\n";
        }
    }
}
