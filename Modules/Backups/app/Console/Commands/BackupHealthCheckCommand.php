<?php

namespace Modules\Backups\Console\Commands;

use Illuminate\Console\Command;
use Modules\Backups\Services\BackupHealthService;
use Modules\Backups\Services\BackupService;

class BackupHealthCheckCommand extends Command
{
    protected $signature = 'backup:health-check';

    protected $description = 'Run post-restore style health checks (tables, perms, recent backup)';

    public function handle(BackupHealthService $health, BackupService $service): int
    {
        $probe = $service->healthProbe();
        $this->line('DB: '.$probe['database'].' — '.$probe['tables'].' tables, '.$probe['size_mb'].' MB, server '.$probe['server_version']);
        $this->line('mysqldump: '.($probe['mysqldump_exists'] ? 'found' : 'MISSING').' | mysql: '.($probe['mysql_exists'] ? 'found' : 'MISSING'));

        $result = $health->check();
        foreach ($result['checks'] as $check) {
            $this->line(($check['ok'] ? '  ✓ ' : '  ✗ ').$check['name'].' — '.$check['detail']);
        }

        if (! $result['ok']) {
            $this->error('Health check FAILED.');

            return self::FAILURE;
        }

        $this->info('Health check passed.');

        return self::SUCCESS;
    }
}
