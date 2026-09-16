<?php

namespace Modules\Backups\Console\Commands;

use Illuminate\Console\Command;
use Modules\Backups\Services\BackupRetentionService;

class BackupCleanupCommand extends Command
{
    protected $signature = 'backup:cleanup';

    protected $description = 'Enforce retention policy (never deletes the last verified backup)';

    public function handle(BackupRetentionService $retention): int
    {
        $result = $retention->enforce();
        $this->info("Retention: deleted {$result['deleted']}, kept {$result['kept']}, skipped {$result['skipped']}.");

        return self::SUCCESS;
    }
}
