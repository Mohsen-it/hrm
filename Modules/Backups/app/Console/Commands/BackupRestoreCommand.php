<?php

namespace Modules\Backups\Console\Commands;

use Illuminate\Console\Command;
use Modules\Backups\Repositories\BackupRunRepository;
use Modules\Backups\Services\BackupRestoreService;
use Throwable;

/**
 * backup:restore — GUARDED production restore.
 *
 * Requires ALL of: verified backup, --reason, --confirm, and the
 * `restore-backups-production` permission is enforced at the HTTP layer.
 * Always creates an emergency pre-restore backup first.
 */
class BackupRestoreCommand extends Command
{
    protected $signature = 'backup:restore {id : Backup run id}
                            {--reason= : Why is this restore needed (required)}
                            {--confirm : Explicit confirmation flag (required)}';

    protected $description = 'Restore a VERIFIED backup to production (guarded, with pre-restore backup)';

    public function handle(BackupRunRepository $runs, BackupRestoreService $restore): int
    {
        if (! $this->option('confirm')) {
            $this->error('Refusing: pass --confirm to acknowledge a production restore.');

            return self::FAILURE;
        }

        $reason = trim((string) $this->option('reason'));
        if ($reason === '') {
            $this->error('Refusing: --reason is required.');

            return self::FAILURE;
        }

        $run = $runs->findById((int) $this->argument('id'));
        if (! $run) {
            $this->error('Backup not found.');

            return self::FAILURE;
        }

        if (! $run->isVerified()) {
            $this->error('Refusing: backup is not verified (status: '.$run->verification_status.').');

            return self::FAILURE;
        }

        // When --confirm is set, skip the interactive prompt entirely so the
        // command can be used non-interactively (scripts, CI, etc.).
        if (! $this->option('confirm') && $this->input->isInteractive() && ! $this->confirm("Restore backup #{$run->id} ({$run->file_name}) to PRODUCTION hrmair?")) {
            $this->line('Aborted.');

            return self::FAILURE;
        }

        try {
            $attempt = $restore->restoreProduction($run, $reason);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Production restore completed (attempt #{$attempt->id}). Pre-restore backup: #{$attempt->pre_restore_backup_id}.");

        return self::SUCCESS;
    }
}
