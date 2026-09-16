<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Running cleanup...\n";

// Delete audit logs for failed runs
DB::table('backup_audit_logs')
    ->whereIn('backup_run_id', [3, 4])
    ->delete();
echo "Cleaned audit logs\n";

// Delete restore attempts for failed runs
DB::table('backup_restore_attempts')
    ->whereIn('backup_run_id', [3, 4])
    ->delete();
echo "Cleaned restore attempts\n";

// Delete failed runs
DB::table('backup_runs')
    ->whereIn('id', [3, 4])
    ->delete();
echo "Deleted failed runs\n";

// Fix AUTO_INCREMENT
foreach (['backup_restore_attempts', 'backup_runs', 'backup_audit_logs'] as $t) {
    $max = DB::table($t)->max('id') ?? 0;
    DB::statement("ALTER TABLE `{$t}` AUTO_INCREMENT = {$max + 1}");
}
echo "Fixed AUTO_INCREMENT\n";

// Show result
echo "\nRemaining backups:\n";
foreach (DB::table('backup_runs')->orderByDesc('id')->get() as $r) {
    echo "  #{$r->id}: {$r->status} | {$r->file_name}\n";
}
echo "Done.\n";
