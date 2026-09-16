<?php

namespace Modules\Backups\Console\Commands;

use Illuminate\Console\Command;
use Modules\Backups\Repositories\BackupRunRepository;
use Modules\Backups\Services\BackupVerificationService;

class BackupVerifyCommand extends Command
{
    protected $signature = 'backup:verify {id : Backup run id}';

    protected $description = 'Verify a backup (checksum + decrypt + gzip probe)';

    public function handle(BackupRunRepository $runs, BackupVerificationService $verification): int
    {
        $run = $runs->findById((int) $this->argument('id'));
        if (! $run) {
            $this->error('Backup not found.');

            return self::FAILURE;
        }

        $ok = $verification->verify($run);
        $this->info($ok ? 'VERIFIED' : 'FAILED: '.$run->fresh()->verification_message);

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
