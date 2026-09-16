<?php

namespace Modules\Backups\Console\Commands;

use Illuminate\Console\Command;
use Modules\Backups\Repositories\BackupRunRepository;

class BackupStatusCommand extends Command
{
    protected $signature = 'backup:status';

    protected $description = 'Show backup health summary (last success/failure/test)';

    public function handle(BackupRunRepository $runs): int
    {
        $latest = $runs->query()->latest()->first();
        $success = $runs->latestSuccessful();
        $verified = $runs->latestVerified();
        $failed = $runs->query()->where('status', 'failed')->latest()->first();

        $this->table(['Metric', 'Value'], [
            ['Total runs', $runs->query()->count()],
            ['Latest run', $latest ? "#{$latest->id} {$latest->status} @ {$latest->created_at}" : 'none'],
            ['Last success', $success ? "#{$success->id} @ {$success->created_at}" : 'none'],
            ['Last verified', $verified ? "#{$verified->id} @ {$verified->created_at}" : 'none'],
            ['Last failure', $failed ? "#{$failed->id} {$failed->error_code} @ {$failed->created_at}" : 'none'],
        ]);

        if (! $success || $success->created_at->lt(now()->subHours(26))) {
            $this->warn('No successful backup within 26h — investigate immediately.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
