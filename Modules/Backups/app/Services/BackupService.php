<?php

namespace Modules\Backups\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Backups\Models\BackupRun;
use Modules\Backups\Repositories\BackupAuditRepository;
use Modules\Backups\Repositories\BackupRunRepository;
use RuntimeException;
use Throwable;

/**
 * Orchestrates full backup creation (plan §6 BackupService).
 *
 * Pipeline: mysqldump → gzip → [encrypt] → SHA-256 → local disk
 *           → [remote disk] → verification → audit → notification.
 *
 * A backup row is created FIRST (status=running) so failures are tracked.
 * Temp .sql/.gz files live in the OS temp dir and are always removed.
 */
class BackupService
{
    public const LOCK_KEY = 'backup-run-lock';

    public const LOCK_TTL = 3600;

    public function __construct(
        private BackupRunRepository $runs,
        private BackupAuditRepository $audit,
        private BackupCryptoService $crypto,
        private BackupVerificationService $verification,
        private BackupNotificationService $notifications,
    ) {}

    /**
     * Create a full backup.
     *
     * @param  array{type?: string, config_id?: int|null, initiated_by?: int|null}  $options
     *
     * @throws RuntimeException when a backup is already running or on failure.
     */
    public function createBackup(array $options = []): BackupRun
    {
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_TTL);
        if (! $lock->acquire()) {
            throw new RuntimeException('Another backup is already running (lock active).');
        }

        $database = (string) config('backups.database_name', 'hrmair');
        $type = $options['type'] ?? 'manual';
        $run = null;
        $tempSql = null;
        $tempGz = null;

        try {
            $this->assertFreeSpace();

            $engine = MysqlDumpService::fromConfig();
            $engine->assertBinariesExist();

            $base = BackupCryptoService::baseName($database);
            $encrypt = (bool) config('backups.encryption_enabled', true);

            $finalName = $base.'.sql.gz'.($encrypt ? '.enc' : '');
            $localDisk = (string) config('backups.local_disk', 'backups');

            $run = $this->runs->create([
                'backup_config_id' => $options['config_id'] ?? null,
                'type' => $type,
                'status' => 'running',
                'database_driver' => 'mysql',
                'database_name' => $database,
                'database_server_version' => $engine->serverVersion($database),
                'file_path' => $localDisk.'/'.$finalName,
                'file_name' => $finalName,
                'checksum_algorithm' => 'sha256',
                'checksum' => 'pending',
                'compressed' => true,
                'encrypted' => $encrypt,
                'verification_status' => 'pending',
                'started_at' => now(),
                'initiated_by' => $options['initiated_by'] ?? auth()->id(),
            ]);

            $this->audit->log('backup.started', ['type' => $type, 'database' => $database], $run->id);

            // 1. Dump (streaming, no memory load).
            $tempSql = sys_get_temp_dir().DIRECTORY_SEPARATOR.$base.'_'.uniqid().'.sql';
            $engine->dumpToFile($database, $tempSql);

            // 2. Compress (streaming).
            $tempGz = sys_get_temp_dir().DIRECTORY_SEPARATOR.$base.'_'.uniqid().'.sql.gz';
            $this->crypto->compress($tempSql, $tempGz);
            @unlink($tempSql);
            $tempSql = null;

            // 3. Encrypt (streaming) or store gz directly.
            $tempFinal = sys_get_temp_dir().DIRECTORY_SEPARATOR.$finalName.'_'.uniqid().'.tmp';
            if ($encrypt) {
                $this->crypto->encrypt($tempGz, $tempFinal);
            } else {
                if (! copy($tempGz, $tempFinal)) {
                    throw new RuntimeException('Failed staging final backup file.');
                }
            }
            @unlink($tempGz);
            $tempGz = null;

            // 4. Checksum + store on local disk.
            $checksum = $this->crypto->sha256($tempFinal);
            $size = filesize($tempFinal);

            $stream = fopen($tempFinal, 'rb');
            if ($stream === false) {
                throw new RuntimeException('Cannot open staged backup for storage.');
            }
            if (! Storage::disk($localDisk)->put($finalName, $stream)) {
                throw new RuntimeException('Failed writing backup to local storage.');
            }
            if (is_resource($stream)) {
                fclose($stream);
            }
            @unlink($tempFinal);

            // 5. Remote copy (only after local verified write).
            $remoteDisk = config('backups.remote_disk');
            $remotePath = null;
            if ($remoteDisk) {
                try {
                    $localFull = Storage::disk($localDisk)->path($finalName);
                    $remoteStream = fopen($localFull, 'rb');
                    if ($remoteStream !== false) {
                        Storage::disk($remoteDisk)->put($finalName, $remoteStream);
                        if (is_resource($remoteStream)) {
                            fclose($remoteStream);
                        }
                        if (! Storage::disk($remoteDisk)->exists($finalName)) {
                            throw new RuntimeException('Remote backup upload completed without a readable remote object.');
                        }

                        $remoteSize = Storage::disk($remoteDisk)->size($finalName);
                        if ((int) $remoteSize !== (int) $size) {
                            throw new RuntimeException('Remote backup size does not match local backup size.');
                        }

                        if (method_exists(Storage::disk($remoteDisk), 'checksum')) {
                            $remoteChecksum = Storage::disk($remoteDisk)->checksum($finalName);
                            if ($remoteChecksum && ! hash_equals(strtolower($checksum), strtolower($remoteChecksum))) {
                                throw new RuntimeException('Remote backup checksum does not match local checksum.');
                            }
                        }

                        $remotePath = $remoteDisk.'/'.$finalName;
                    }
                } catch (Throwable $e) {
                    // Remote failure must not fail the local backup,
                    // but it is recorded + alerted (plan §20).
                    $this->audit->log('backup.remote_upload_failed', [
                        'error' => mb_substr($e->getMessage(), 0, 500),
                    ], $run->id);
                    $this->notifications->notifyHealth('remote upload failed', ['run_id' => $run->id]);
                }
            }

            $run = $this->runs->update($run, [
                'status' => 'completed',
                'file_size' => $size,
                'checksum' => $checksum,
                'remote_file_path' => $remotePath,
                'completed_at' => now(),
            ]);

            // 6. Verification (checksum + gzip/sodium round-trip probe).
            if ((bool) config('backups.verification_enabled', true)) {
                if (! $this->verification->verify($run)) {
                    throw new RuntimeException('Backup verification failed. The backup was not accepted.');
                }
                $run = $run->fresh();
            }

            // Write the sidecar only after verification so it cannot claim a
            // pending/unknown state for a backup that is already complete.
            $metadata = $this->buildMetadata($run);
            if (! Storage::disk($localDisk)->put($base.'.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                throw new RuntimeException('Failed writing backup metadata.');
            }

            $this->audit->log('backup.completed', [
                'file_name' => $run->file_name,
                'file_size' => $run->file_size,
                'verification' => $run->verification_status,
            ], $run->id);

            $this->notifications->notifySuccess($run);

            return $run->fresh();
        } catch (Throwable $e) {
            @unlink($tempSql ?? '');
            @unlink($tempGz ?? '');

            if ($run) {
                try {
                    $this->runs->update($run, [
                        'status' => 'failed',
                        'failed_at' => now(),
                        'error_code' => $this->errorCode($e),
                        'error_message' => mb_substr($e->getMessage(), 0, 2000),
                    ]);
                } catch (Throwable) {
                    // Swallow — must not mask the original error.
                }
                try {
                    $this->audit->log('backup.failed', [
                        'error' => mb_substr($e->getMessage(), 0, 500),
                    ], $run->id);
                } catch (Throwable) {
                    // Swallow.
                }
                try {
                    $this->notifications->notifyFailure($run, $this->errorCode($e), $e->getMessage());
                } catch (Throwable) {
                    // Swallow.
                }
            }

            throw new RuntimeException('Backup failed: '.$e->getMessage(), 0, $e);
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array<string, mixed> non-sensitive metadata sidecar.
     */
    public function buildMetadata(BackupRun $run): array
    {
        return [
            'database' => $run->database_name,
            'driver' => $run->database_driver,
            'mysql_version' => $run->database_server_version,
            'created_at' => $run->created_at?->toIso8601String(),
            'compressed' => $run->compressed,
            'encrypted' => $run->encrypted,
            'checksum_algorithm' => $run->checksum_algorithm,
            'checksum' => $run->checksum,
            'verification_status' => $run->verification_status,
        ];
    }

    private function assertFreeSpace(): void
    {
        $minMb = (int) config('backups.min_free_space_mb', 20480);
        try {
            $root = Storage::disk((string) config('backups.local_disk', 'backups'))->path('');
            if (! is_dir($root)) {
                @mkdir($root, 0755, true);
            }
            $free = @disk_free_space($root);
            if ($free !== false && $free < $minMb * 1024 * 1024) {
                throw new RuntimeException('Low disk space for backups: '.round($free / 1024 / 1024).'MB free, need '.$minMb.'MB.');
            }
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable) {
            // If the space probe itself fails, continue — the dump will
            // surface real write errors with a clearer message.
        }
    }

    private function errorCode(Throwable $e): string
    {
        $message = strtolower($e->getMessage());

        return match (true) {
            str_contains($message, 'mysqldump') => 'DUMP_FAILED',
            str_contains($message, 'disk space') => 'LOW_DISK_SPACE',
            str_contains($message, 'encryption_key') => 'ENCRYPTION_KEY_MISSING',
            str_contains($message, 'already running') => 'OVERLAPPING_RUN',
            default => 'BACKUP_FAILED',
        };
    }

    /**
     * Quick read-only health probe used by backup:health-check.
     *
     * @return array<string, mixed>
     */
    public function healthProbe(): array
    {
        $engine = MysqlDumpService::fromConfig();
        $database = (string) config('backups.database_name', 'hrmair');

        $tables = 0;
        $sizeMb = 0.0;
        try {
            $row = DB::selectOne('SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = ?', [$database]);
            $tables = (int) ($row->c ?? 0);
            $size = DB::selectOne('SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS mb FROM information_schema.tables WHERE table_schema = ?', [$database]);
            $sizeMb = (float) ($size->mb ?? 0);
        } catch (Throwable) {
            // Probe must never throw.
        }

        return [
            'database' => $database,
            'tables' => $tables,
            'size_mb' => $sizeMb,
            'mysqldump_exists' => is_file(config('backups.mysqldump_path')),
            'mysql_exists' => is_file(config('backups.mysql_path')),
            'server_version' => $engine->serverVersion($database),
            'latest_verified_id' => $this->runs->latestVerified()?->id,
        ];
    }
}
