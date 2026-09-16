<?php

namespace Modules\Backups\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Modules\Backups\Models\BackupRestoreAttempt;
use Modules\Backups\Models\BackupRun;
use Modules\Backups\Repositories\BackupAuditRepository;
use Modules\Backups\Repositories\BackupRunRepository;
use RuntimeException;
use Throwable;

/**
 * Test + production restore (plan §14, §15).
 *
 * HARD SAFETY RULES:
 * - Test restore ALWAYS targets an isolated `prefix_*` database, never hrmair.
 * - Production restore REQUIRES: verified backup + explicit reason +
 *   pre-restore emergency backup + test-restore pass + maintenance mode.
 * - HeidiSQL-style dumps are never imported over a non-empty production DB
 *   without the full guarded sequence.
 */
class BackupRestoreService
{
    public function __construct(
        private BackupRunRepository $runs,
        private BackupAuditRepository $audit,
        private BackupCryptoService $crypto,
        private BackupVerificationService $verification,
        private BackupNotificationService $notifications,
        private BackupService $backupService,
        private BackupHealthService $healthService,
    ) {}

    /**
     * Restore a backup into an isolated test database and run health checks.
     *
     * @return array{attempt_id: int, target: string, checks: array, duration_s: float}
     */
    public function restoreTest(BackupRun $run, ?int $initiatedBy = null, string $reason = 'scheduled test'): array
    {
        if (! $run->isCompleted()) {
            throw new RuntimeException('Only completed backups can be restore-tested.');
        }

        $lock = Cache::lock('backup-restore-test-lock', 7200);
        if (! $lock->acquire()) {
            throw new RuntimeException('Another restore test is already running.');
        }

        $started = microtime(true);

        $attempt = $this->audit->createRestoreAttempt([
            'backup_run_id' => $run->id,
            'restore_type' => 'test',
            'target_database' => (string) config('backups.restore_test_prefix', 'hrmair_restore_test_').date('Ymd_His'),
            'status' => 'running',
            'started_at' => now(),
            'initiated_by' => $initiatedBy ?? auth()->id(),
            'reason' => $reason,
        ]);

        $this->audit->log('restore_test.started', ['target' => $attempt->target_database], $run->id, $attempt->id);

        try {
            // 1. Re-verify checksum before touching any database.
            if (! $this->verification->verify($run->fresh())) {
                throw new RuntimeException('Checksum/verification failed before restore-test.');
            }

            // 2. Materialize plain SQL to temp (decrypt → decompress).
            $sqlFile = $this->materializeSql($run);

            try {
                // 3. Create isolated database.
                $this->statementWithoutDb("CREATE DATABASE IF NOT EXISTS `{$attempt->target_database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

                // 4. Import.
                MysqlDumpService::fromConfig()->importFile($attempt->target_database, $sqlFile);

                // 5. Health checks against the TEST database.
                $checks = $this->runHealthChecks($attempt->target_database);
                $failed = array_filter($checks, fn ($c) => ! $c['ok']);
                if ($failed !== []) {
                    $names = implode(', ', array_column($failed, 'name'));
                    throw new RuntimeException("Health checks failed on test restore: {$names}");
                }
            } finally {
                @unlink($sqlFile);
                // 6. Always drop the test database (record result first).
                try {
                    $this->statementWithoutDb("DROP DATABASE IF EXISTS `{$attempt->target_database}`");
                } catch (Throwable $e) {
                    $this->audit->log('restore_test.cleanup_failed', [
                        'error' => mb_substr($e->getMessage(), 0, 300),
                    ], $run->id, $attempt->id);
                }
            }

            $duration = round(microtime(true) - $started, 1);
            $this->audit->updateRestoreAttempt($attempt, ['status' => 'completed', 'completed_at' => now()]);
            $this->audit->log('restore_test.completed', ['duration_s' => $duration], $run->id, $attempt->id);
            $this->notifications->notifyRestoreTest('passed', ['run_id' => $run->id, 'duration_s' => $duration]);

            return ['attempt_id' => $attempt->id, 'target' => $attempt->target_database, 'checks' => $checks ?? [], 'duration_s' => $duration];
        } catch (Throwable $e) {
            // Record the failure — audit logging must never mask the original error.
            try {
                $this->audit->updateRestoreAttempt($attempt, [
                    'status' => 'failed',
                    'completed_at' => now(),
                    'error_message' => mb_substr($e->getMessage(), 0, 2000),
                ]);
            } catch (Throwable) {
                // Swallow — we still need to throw the original exception.
            }
            try {
                $this->audit->log('restore_test.failed', ['error' => mb_substr($e->getMessage(), 0, 500)], $run->id, $attempt->id);
            } catch (Throwable) {
                // Swallow.
            }
            try {
                $this->notifications->notifyRestoreTest('failed', ['run_id' => $run->id]);
            } catch (Throwable) {
                // Swallow.
            }

            throw new RuntimeException('Restore test failed: '.$e->getMessage(), 0, $e);
        } finally {
            $lock->release();
        }
    }

    /**
     * Guarded production restore. Returns the restore attempt.
     *
     * Preconditions enforced in code (not just docs):
     * verified backup, non-empty reason, --confirm flag handled by command,
     * emergency pre-restore backup, passing test-restore, maintenance mode.
     */
    public function restoreProduction(BackupRun $run, string $reason, ?int $initiatedBy = null, ?int $approvedBy = null): BackupRestoreAttempt
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('A reason is required for production restore.');
        }
        if (! $run->isCompleted() || ! $run->isVerified()) {
            throw new RuntimeException('Only verified backups can be restored to production.');
        }

        $database = $run->database_name;

        $attempt = $this->audit->createRestoreAttempt([
            'backup_run_id' => $run->id,
            'restore_type' => 'production',
            'target_database' => $database,
            'status' => 'running',
            'started_at' => now(),
            'initiated_by' => $initiatedBy ?? auth()->id(),
            'approved_by' => $approvedBy,
            'reason' => $reason,
        ]);

        $this->audit->log('restore_production.started', ['reason' => $reason], $run->id, $attempt->id);

        $wasInMaintenance = app()->isDownForMaintenance();

        try {
            // 1. Emergency backup of CURRENT production (rollback anchor).
            //
            // CRITICAL: the SQL import in step 4 rewrites every table, so it
            // wipes ALL rows created after the backup point — including this
            // pre-restore row and the restore attempt above. Snapshot
            // everything needed in memory and re-register it in step 6.
            $preBackup = $this->backupService->createBackup([
                'type' => 'manual',
                'initiated_by' => $initiatedBy ?? auth()->id(),
            ]);
            $preBackupSnapshot = $preBackup->getAttributes();
            unset($preBackupSnapshot['id'], $preBackupSnapshot['created_at'], $preBackupSnapshot['updated_at']);
            $preBackupFile = $preBackup->file_name;
            $this->audit->updateRestoreAttempt($attempt, ['pre_restore_backup_id' => $preBackup->id]);

            // 2. The candidate must pass an isolated test restore first.
            $testResult = $this->restoreTest($run->fresh(), $initiatedBy, "pre-production gate for run {$run->id}");

            // 3. Maintenance mode (blocks writes during the swap).
            if (! $wasInMaintenance) {
                \Artisan::call('down', ['--render' => 'errors::503', '--retry' => 60]);
            }

            // 4. Materialize + import into production.
            //    From here on, NO database row created after the backup
            //    point may be trusted — the import replaces them all.
            //    Use only in-memory scalars below.
            $runId = $run->id;
            $attemptStartedAt = $attempt->started_at;
            $sqlFile = $this->materializeSql($run->fresh());
            try {
                MysqlDumpService::fromConfig()->importFile($database, $sqlFile);
            } finally {
                @unlink($sqlFile);
            }

            // 5. Post-restore health gate. Uses the post-restore variant
            //    (no "recent backup" recency check — backup_runs was just
            //    rewound by design, so that check would false-fail).
            $health = $this->healthService->checkPostRestore();
            if (! $health['ok']) {
                throw new RuntimeException('Post-restore health check failed: '.implode('; ', $health['failures']));
            }

            // 6. Re-register the bookkeeping wiped by the import so the
            //    restore is visible, auditable, and the rollback anchor
            //    restorable via UI.
            //    6a. The restored source-run row was dumped mid-flight as
            //        'running' — mark it completed/verified from memory.
            $this->refreshRestoredRunRow($run);
            //    6b. Re-create the pre-restore (rollback anchor) run row.
            $newPreBackup = $this->ensurePreBackupRow($preBackupSnapshot, $preBackupFile);
            //    6c. Record the completed production attempt as a fresh row
            //        (the pre-import row was wiped by the import).
            $completed = $this->audit->createRestoreAttempt([
                'backup_run_id' => $runId,
                'restore_type' => 'production',
                'target_database' => $database,
                'status' => 'completed',
                'started_at' => $attemptStartedAt,
                'completed_at' => now(),
                'initiated_by' => $initiatedBy ?? auth()->id(),
                'approved_by' => $approvedBy,
                'reason' => $reason,
                'pre_restore_backup_id' => $newPreBackup?->id,
            ]);
            $this->audit->log('restore_production.completed', [
                'test_duration_s' => $testResult['duration_s'] ?? null,
                'pre_restore_backup_id' => $newPreBackup?->id,
                'note' => 'Bookkeeping re-registered after import (import wipes post-backup rows by design).',
            ], $runId, $completed->id);

            return $completed;
        } catch (Throwable $e) {
            // Record the failure — audit logging must never mask the original
            // error. Depending on WHEN the failure happened, the attempt /
            // pre-backup rows may still exist (pre-import failure) or have
            // been wiped (post-import failure): handle both.
            try {
                $newPreBackup = $this->ensurePreBackupRow($preBackupSnapshot ?? [], $preBackupFile ?? '');
                $stale = BackupRestoreAttempt::query()->find($attempt->id);
                if ($stale) {
                    $this->audit->updateRestoreAttempt($stale, [
                        'status' => 'failed',
                        'completed_at' => now(),
                        'error_message' => mb_substr($e->getMessage(), 0, 2000),
                    ]);
                    $attemptIdForLog = $stale->id;
                } else {
                    $recreated = $this->audit->createRestoreAttempt([
                        'backup_run_id' => $run->id,
                        'restore_type' => 'production',
                        'target_database' => $database,
                        'status' => 'failed',
                        'started_at' => $attempt->started_at,
                        'completed_at' => now(),
                        'initiated_by' => $initiatedBy ?? auth()->id(),
                        'approved_by' => $approvedBy,
                        'reason' => $reason,
                        'pre_restore_backup_id' => $newPreBackup?->id,
                        'error_message' => mb_substr($e->getMessage(), 0, 2000),
                    ]);
                    $attemptIdForLog = $recreated->id;
                }
                $this->audit->log('restore_production.failed', [
                    'error' => mb_substr($e->getMessage(), 0, 500),
                    'note' => 'Pre-restore backup retained for rollback.',
                ], $run->id, $attemptIdForLog);
            } catch (Throwable) {
                // Swallow.
            }

            throw new RuntimeException('Production restore failed (pre-restore backup retained): '.$e->getMessage(), 0, $e);
        } finally {
            if (! $wasInMaintenance && app()->isDownForMaintenance()) {
                \Artisan::call('up');
            }
        }
    }

    /**
     * Re-mark the restored source-run row as completed/verified.
     *
     * The row was dumped mid-flight as 'running'/'pending'; the completed
     * state is restored from the verified in-memory snapshot. Best-effort:
     * bookkeeping must never fail a successful restore.
     */
    private function refreshRestoredRunRow(BackupRun $run): void
    {
        try {
            $existing = BackupRun::query()->find($run->id);
            if (! $existing) {
                return;
            }
            $existing->forceFill([
                'status' => 'completed',
                'file_size' => $run->file_size,
                'checksum' => $run->checksum,
                'verification_status' => 'verified',
                'verification_message' => $run->verification_message,
                'completed_at' => $run->completed_at,
                'failed_at' => null,
                'error_code' => null,
                'error_message' => null,
            ])->save();
        } catch (Throwable) {
            // Swallow — bookkeeping only.
        }
    }

    /**
     * Re-create the pre-restore (rollback anchor) run row if it was wiped
     * by the import. Returns the existing or re-created row, or null when
     * the backup file itself is missing. Idempotent and best-effort.
     */
    private function ensurePreBackupRow(array $snapshot, string $fileName): ?BackupRun
    {
        try {
            if ($fileName === '') {
                return null;
            }
            $existing = BackupRun::query()->where('file_name', $fileName)->first();
            if ($existing) {
                return $existing;
            }
            $disk = (string) config('backups.local_disk', 'backups');
            if (! Storage::disk($disk)->exists($fileName)) {
                return null;
            }

            return BackupRun::query()->create($snapshot);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Decrypt + decompress the stored backup to a temp .sql file.
     *
     * @return string path to temp .sql (caller must unlink).
     */
    public function materializeSql(BackupRun $run): string
    {
        $disk = (string) config('backups.local_disk', 'backups');
        $stored = Storage::disk($disk)->path($run->file_name);

        if (! is_file($stored)) {
            throw new RuntimeException('Backup file missing on local disk.');
        }

        $working = $stored;
        $tempDec = null;

        if ($run->encrypted) {
            $tempDec = sys_get_temp_dir().DIRECTORY_SEPARATOR.'restore_'.uniqid().'.gz';
            $this->crypto->decrypt($stored, $tempDec);
            $working = $tempDec;
        }

        $tempSql = sys_get_temp_dir().DIRECTORY_SEPARATOR.'restore_'.uniqid().'.sql';

        try {
            $this->crypto->decompress($working, $tempSql);
        } finally {
            if ($tempDec) {
                @unlink($tempDec);
            }
        }

        return $tempSql;
    }

    /**
     * Run read-only health checks against $database (plan §16 subset).
     *
     * @return array<int, array{name: string, ok: bool, detail: string}>
     */
    public function runHealthChecks(string $database): array
    {
        $checks = [];
        $pdo = $this->pdoWithoutDb();

        $check = function (string $name, callable $fn) use (&$checks): void {
            try {
                $detail = (string) $fn();
                $checks[] = ['name' => $name, 'ok' => true, 'detail' => $detail];
            } catch (Throwable $e) {
                $checks[] = ['name' => $name, 'ok' => false, 'detail' => mb_substr($e->getMessage(), 0, 300)];
            }
        };

        $check('migrations table', function () use ($pdo, $database) {
            $n = $this->count($pdo, $database, 'migrations');
            if ($n === 0) {
                throw new RuntimeException('migrations table empty/missing');
            }

            return "{$n} migrations";
        });

        foreach (['users', 'companies', 'branches'] as $table) {
            $check("table {$table}", function () use ($pdo, $database, $table) {
                return $this->count($pdo, $database, $table).' rows';
            });
        }

        $check('arabic text', function () use ($pdo, $database) {
            $stmt = $pdo->query("SELECT COUNT(*) AS c FROM `{$database}`.`users` WHERE `name` REGEXP '[؀-ۿ]'");
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            return ((int) ($row['c'] ?? 0)).' arabic names';
        });

        $check('foreign keys present', function () use ($pdo, $database) {
            $stmt = $pdo->query('SELECT COUNT(*) AS c FROM information_schema.table_constraints WHERE constraint_schema = '.$pdo->quote($database)." AND constraint_type = 'FOREIGN KEY'");
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ((int) ($row['c'] ?? 0) === 0) {
                throw new RuntimeException('no foreign keys found');
            }

            return ((int) $row['c']).' FKs';
        });

        return $checks;
    }

    private function pdoWithoutDb(): \PDO
    {
        $connection = config('backups.database_connection', 'mysql');
        $cfg = config("database.connections.{$connection}", []);

        return new \PDO(
            'mysql:host='.($cfg['host'] ?? '127.0.0.1').';port='.($cfg['port'] ?? 3306).';charset=utf8mb4',
            $cfg['username'] ?? 'root',
            $cfg['password'] ?? null,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
    }

    private function statementWithoutDb(string $sql): void
    {
        $this->pdoWithoutDb()->exec($sql);
    }

    private function count(\PDO $pdo, string $database, string $table): int
    {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) AS c FROM `{$database}`.`{$table}`");
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            return (int) ($row['c'] ?? 0);
        } catch (Throwable $e) {
            throw new RuntimeException("table {$table} unreadable: ".mb_substr($e->getMessage(), 0, 200));
        }
    }

    /**
     * Selection guard used by commands: newest verified usable backup.
     */
    public function latestUsable(): ?BackupRun
    {
        return $this->runs->latestVerified();
    }
}
