<?php

namespace Modules\Backups\Services;

use Illuminate\Support\Facades\Storage;
use Modules\Backups\Models\BackupRun;
use Modules\Backups\Repositories\BackupAuditRepository;
use Modules\Backups\Repositories\BackupRunRepository;
use Throwable;

/**
 * Retention enforcement (plan §17).
 *
 * - Keeps N daily / N weekly / N monthly completed backups.
 * - NEVER deletes the last verified backup.
 * - NEVER deletes a run currently under restore-test.
 * - Deletes remote copy first, then local file, then DB row.
 * - Every deletion is audited.
 */
class BackupRetentionService
{
    public function __construct(
        private BackupRunRepository $runs,
        private BackupAuditRepository $audit,
        private BackupNotificationService $notifications,
    ) {}

    /**
     * @return array{deleted: int, kept: int, skipped: int}
     */
    public function enforce(): array
    {
        $daily = (int) config('backups.retention_daily', 14);
        $weekly = (int) config('backups.retention_weekly', 12);
        $monthly = (int) config('backups.retention_monthly', 12);

        $deleted = 0;
        $kept = 0;
        $skipped = 0;

        foreach (['daily' => $daily, 'weekly' => $weekly, 'monthly' => $monthly] as $class => $keep) {
            $ordered = $this->runs->getCompletedOrdered($class);
            $excess = $ordered->slice($keep);

            foreach ($excess as $run) {
                $result = $this->deleteRun($run, "retention:{$class}");
                match ($result) {
                    'deleted' => $deleted++,
                    'kept' => $kept++,
                    default => $skipped++,
                };
            }

            $kept += min($ordered->count(), $keep);
        }

        $this->notifications->notifyRetention('enforced', compact('deleted', 'kept', 'skipped'));

        return compact('deleted', 'kept', 'skipped');
    }

    /**
     * Delete one run safely. Returns deleted|kept|skipped.
     */
    public function deleteRun(BackupRun $run, string $reason): string
    {
        // Rule 1: never delete the last verified backup.
        if ($run->isVerified() && $this->runs->countVerified() <= 1) {
            $this->audit->log('backup.delete_blocked', [
                'reason' => $reason,
                'cause' => 'last_verified',
            ], $run->id);

            return 'kept';
        }

        // Rule 2: never delete while a restore attempt is running on it.
        $activeRestore = $run->restoreAttempts()->whereIn('status', ['pending', 'running'])->exists();
        if ($activeRestore) {
            return 'skipped';
        }

        $disk = (string) config('backups.local_disk', 'backups');

        try {
            // Remote first, then local, then DB row (safe order).
            if ($run->remote_file_path && config('backups.remote_disk')) {
                try {
                    Storage::disk((string) config('backups.remote_disk'))->delete($run->file_name);
                } catch (Throwable $e) {
                    $this->audit->log('backup.remote_delete_failed', [
                        'error' => mb_substr($e->getMessage(), 0, 300),
                    ], $run->id);
                }
            }

            Storage::disk($disk)->delete($run->file_name);
            // Sidecar metadata is best-effort.
            $base = preg_replace('/\.sql\.gz(\.enc)?$/', '', $run->file_name);
            if ($base) {
                Storage::disk($disk)->delete($base.'.json');
            }

            $this->audit->log('backup.deleted', [
                'reason' => $reason,
                'file_name' => $run->file_name,
            ], $run->id);

            $this->runs->delete($run);

            return 'deleted';
        } catch (Throwable $e) {
            $this->audit->log('backup.delete_failed', [
                'error' => mb_substr($e->getMessage(), 0, 300),
            ], $run->id);

            return 'skipped';
        }
    }
}
