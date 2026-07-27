<?php

namespace Modules\Attendance\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Models\RawAttendanceLog;
use Modules\Users\Models\User;

/**
 * Attendance:ResolveRawLogUsers — resolve user_id for raw logs that have NULL user_id.
 *
 * This command scans all raw attendance logs with NULL user_id, extracts the
 * device_user_id from raw_data, and attempts to match it against users.employee_code.
 */
class ResolveRawLogUsersCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'attendance:resolve-raw-log-users
                            {--limit=1000 : Maximum number of records to process per run}
                            {--dry-run : Preview changes without applying them}';

    /**
     * The console command description.
     */
    protected $description = 'Resolve user_id for raw attendance logs that have NULL user_id by matching device_user_id to users.employee_code';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        $this->info('Starting to resolve user_id for raw attendance logs...');

        $logs = RawAttendanceLog::query()
            ->whereNull('user_id')
            ->whereNotNull('device_user_id')
            ->limit($limit)
            ->get();

        if ($logs->isEmpty()) {
            $this->info('No raw logs with NULL user_id found.');
            return self::SUCCESS;
        }

        $this->info("Found {$logs->count()} logs to process.");

        $resolved = 0;
        $unresolved = 0;
        $errors = 0;

        foreach ($logs as $log) {
            try {
                $deviceUserId = trim((string) $log->device_user_id);

                if ($deviceUserId === '') {
                    $unresolved++;
                    continue;
                }

                $user = User::query()
                    ->whereRaw('LOWER(employee_code) = LOWER(?)', [$deviceUserId])
                    ->first();

                if (! $user) {
                    $unresolved++;
                    $this->line("  ⚠ No user found for device_user_id: {$deviceUserId}");
                    continue;
                }

                if (! $dryRun) {
                    $log->update(['user_id' => $user->id]);
                }

                $resolved++;
                $this->line("  ✓ Log #{$log->id} → user #{$user->id} ({$user->employee_code})");
            } catch (\Throwable $e) {
                $errors++;
                $this->error("   Error processing log #{$log->id}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info('Summary:');
        $this->line("  Resolved: {$resolved}");
        $this->line("  Unresolved: {$unresolved}");
        $this->line("  Errors: {$errors}");

        if ($dryRun) {
            $this->warn('DRY RUN - No changes were applied.');
        } else {
            $this->info('All changes applied successfully.');
        }

        return self::SUCCESS;
    }
}
