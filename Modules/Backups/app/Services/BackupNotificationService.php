<?php

namespace Modules\Backups\Services;

use Illuminate\Support\Facades\Log;
use Modules\Backups\Models\BackupRun;
use Modules\Backups\Repositories\BackupAuditRepository;

/**
 * Backup notifications (plan §20).
 *
 * v1: dedicated `backup` log channel + audit rows. Mail/webhook hooks are
 * opt-in via config and never block the backup itself.
 */
class BackupNotificationService
{
    public function __construct(private BackupAuditRepository $audit) {}

    public function notifySuccess(BackupRun $run): void
    {
        Log::channel('backup')->info('Backup completed', [
            'run_id' => $run->id,
            'file' => $run->file_name,
            'size' => $run->file_size,
            'checksum' => substr($run->checksum, 0, 16).'…',
        ]);

        $this->audit->log('notify.success', [
            'run_id' => $run->id,
            'file_name' => $run->file_name,
        ], $run->id);
    }

    public function notifyFailure(?BackupRun $run, string $code, string $message): void
    {
        Log::channel('backup')->error('Backup failed', [
            'run_id' => $run?->id,
            'code' => $code,
            'message' => mb_substr($message, 0, 500),
        ]);

        $this->audit->log('notify.failure', [
            'code' => $code,
        ], $run?->id);
    }

    public function notifyRestoreTest(string $status, array $context = []): void
    {
        Log::channel('backup')->info("Restore test {$status}", $context);
    }

    public function notifyRetention(string $message, array $context = []): void
    {
        Log::channel('backup')->info("Retention: {$message}", $context);
    }

    public function notifyHealth(string $message, array $context = [], string $level = 'warning'): void
    {
        Log::channel('backup')->{$level}("Health: {$message}", $context);
    }
}
