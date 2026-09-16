<?php

namespace Modules\Backups\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\Backups\Models\BackupAuditLog;
use Modules\Backups\Models\BackupRestoreAttempt;
use Throwable;

class BackupAuditRepository
{
    /**
     * Write an audit row. Metadata must NEVER contain passwords or keys.
     *
     * This method is designed to NEVER throw — audit logging must never
     * mask the original error. If the primary INSERT fails (e.g. FK
     * constraint on restore_attempt_id because the referenced row was
     * cascade-deleted), we retry with the FK set to null.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        string $event,
        array $metadata = [],
        ?int $backupRunId = null,
        ?int $restoreAttemptId = null,
        ?int $userId = null,
        ?string $ip = null
    ): ?BackupAuditLog {
        $userId = $userId ?? auth()->id();
        try {
            $ip = $ip ?? request()->ip();
        } catch (Throwable) {
            $ip = $ip ?? '127.0.0.1';
        }
        $createdAt = now()->toDateTimeString();

        $payload = [
            'backup_run_id' => $backupRunId,
            'restore_attempt_id' => $restoreAttemptId,
            'event' => $event,
            'metadata' => $metadata,
            'user_id' => $userId,
            'ip_address' => $ip,
            'created_at' => $createdAt,
        ];

        try {
            return BackupAuditLog::create($payload);
        } catch (Throwable $e) {
            // If the FK on restore_attempt_id is the problem, retry
            // without it so the audit row is never lost.
            if ($restoreAttemptId !== null && str_contains($e->getMessage(), 'restore_attempt_id')) {
                try {
                    $payload['restore_attempt_id'] = null;

                    return BackupAuditLog::create($payload);
                } catch (Throwable) {
                    try {
                        $raw = $payload;
                        $raw['metadata'] = json_encode($raw['metadata'] ?? null);
                        DB::table('backup_audit_logs')->insert($raw);
                    } catch (Throwable) {
                        // Audit logging must never throw — swallow.
                    }
                }
            }

            // Also retry if backup_run_id FK is broken.
            if ($backupRunId !== null && str_contains($e->getMessage(), 'backup_run_id')) {
                try {
                    $payload['backup_run_id'] = null;

                    return BackupAuditLog::create($payload);
                } catch (Throwable) {
                    try {
                        $raw = $payload;
                        $raw['metadata'] = json_encode($raw['metadata'] ?? null);
                        DB::table('backup_audit_logs')->insert($raw);
                    } catch (Throwable) {
                        // Audit logging must never throw — swallow.
                    }
                }
            }

            return null;
        }
    }

    /**
     * Create a restore attempt.
     *
     * @param  array<string, mixed>  $data
     */
    public function createRestoreAttempt(array $data): BackupRestoreAttempt
    {
        foreach (['started_at', 'completed_at'] as $ts) {
            if (isset($data[$ts]) && $data[$ts] instanceof \Illuminate\Support\Carbon) {
                $data[$ts] = $data[$ts]->toDateTimeString();
            }
        }

        return BackupRestoreAttempt::create($data);
    }

    public function updateRestoreAttempt(BackupRestoreAttempt $attempt, array $data): BackupRestoreAttempt
    {
        $attempt->update($data);

        $reloaded = BackupRestoreAttempt::find($attempt->id);

        return $reloaded ?? $attempt;
    }
}
