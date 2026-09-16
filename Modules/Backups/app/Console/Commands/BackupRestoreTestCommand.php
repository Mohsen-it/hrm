<?php

namespace Modules\Backups\Console\Commands;

use Illuminate\Console\Command;
use Modules\Backups\Repositories\BackupRunRepository;
use Modules\Backups\Services\BackupRestoreService;
use Throwable;

class BackupRestoreTestCommand extends Command
{
    protected $signature = 'backup:restore-test {id? : Backup run id (default: latest verified)}
                            {--reason= : Reason for the test}';

    protected $description = 'Restore a backup into an isolated test database and run health checks';

    public function handle(BackupRunRepository $runs, BackupRestoreService $restore): int
    {
        $run = $this->argument('id')
            ? $runs->findById((int) $this->argument('id'))
            : $restore->latestUsable();

        if (! $run) {
            $this->error('No usable (verified) backup found.');

            return self::FAILURE;
        }

        $this->info("Restore-testing backup #{$run->id} ({$run->file_name})…");

        try {
            $result = $restore->restoreTest($run, null, (string) ($this->option('reason') ?: 'manual restore-test'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($result['checks'] as $check) {
            $this->line(($check['ok'] ? '  ✓ ' : '  ✗ ').$check['name'].' — '.$check['detail']);
        }
        $this->info("Restore test passed in {$result['duration_s']}s (test db dropped).");

        return self::SUCCESS;
    }
}
