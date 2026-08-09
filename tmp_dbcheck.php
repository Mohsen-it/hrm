<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

echo '=== tables with create/update times ==='.PHP_EOL;
$tables = DB::select("SELECT table_name, create_time, update_time FROM information_schema.tables WHERE table_schema='hrmair' ORDER BY create_time DESC LIMIT 15");
foreach ($tables as $t) {
    echo $t->TABLE_NAME.' | created='.($t->CREATE_TIME ?? '-').' | updated='.($t->UPDATE_TIME ?? '-').PHP_EOL;
}

echo PHP_EOL.'=== row counts for key tables ==='.PHP_EOL;
foreach (['users', 'companies', 'attendance_sessions', 'raw_attendance_logs', 'daily_attendance_summaries', 'att_rotations', 'att_rotation_assignments', 'att_rotation_groups', 'migrations', 'permissions', 'roles'] as $table) {
    try {
        echo $table.': '.DB::table($table)->count().PHP_EOL;
    } catch (Throwable $e) {
        echo $table.': ERROR '.$e->getMessage().PHP_EOL;
    }
}

echo PHP_EOL.'=== migrations batches ==='.PHP_EOL;
foreach (DB::table('migrations')->select('batch', DB::raw('count(*) as c'))->groupBy('batch')->orderBy('batch')->get() as $b) {
    echo 'batch '.$b->batch.': '.$b->c.' migrations'.PHP_EOL;
}
