<?php

namespace Modules\Backups\Console\Commands;

use Illuminate\Console\Command;
use Modules\Backups\Services\BackupService;
use Throwable;

/**
 * backup:create — manual or scheduled full backup.
 */
class BackupCreateCommand extends Command
{
    protected $signature = 'backup:create
                            {--manual : Mark this run as manual (default for CLI)}
                            {--period=daily|weekly|monthly|quarterly : Override retention classification for this run}
                            {--config= : Backup config id to attach}';

    protected $description = 'Create a full MySQL backup (mysqldump → gzip → encrypt → verify)';

    public function handle(BackupService $service): int
    {
        // Read live config from DB so schedule changes take effect immediately
        // without restarting the scheduler process.
        $dbConfig = \Modules\Backups\Models\BackupConfig::first();
        if ($dbConfig) {
            config(['backups.schedule.daily_time' => $dbConfig->scheduled_time ?? '02:00']);
            config(['backups.timezone' => $dbConfig->timezone ?? config('backups.timezone', 'Asia/Damascus')]);
            config(['backups.retention_daily' => $dbConfig->retention_daily ?? 14]);
            config(['backups.retention_weekly' => $dbConfig->retention_weekly ?? 12]);
            config(['backups.retention_monthly' => $dbConfig->retention_monthly ?? 12]);
            config(['backups.encryption_enabled' => (bool) $dbConfig->encryption_enabled]);
            config(['backups.verification_enabled' => (bool) $dbConfig->verification_enabled]);
        }

        $type = $this->option('manual') ? 'manual' : 'automatic';

        $this->info("Starting {$type} backup of hrmair...");

        try {
            $run = $service->createBackup([
                'type' => $type,
                'config_id' => $this->option('config') ? (int) $this->option('config') : null,
            ]);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Backup #{$run->id} completed: {$run->file_name} (".round(($run->file_size ?? 0) / 1024 / 1024, 1).' MB, '.$run->verification_status.')');

        return self::SUCCESS;
    }
}
