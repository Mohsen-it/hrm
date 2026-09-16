<?php

namespace Modules\Backups\Services;

use Illuminate\Support\Facades\Storage;
use Modules\Backups\Models\BackupRun;
use Modules\Backups\Repositories\BackupAuditRepository;
use Modules\Backups\Repositories\BackupRunRepository;
use Throwable;

/**
 * Backup verification (plan §6 BackupVerificationService).
 *
 * A backup is trusted ONLY after: file exists + size>0 + SHA-256 matches +
 * decrypt probe + gzip probe + readable SQL head. Full import testing is
 * done by backup:restore-test on an isolated database.
 */
class BackupVerificationService
{
    public function __construct(
        private BackupRunRepository $runs,
        private BackupAuditRepository $audit,
        private BackupCryptoService $crypto,
    ) {}

    public function verify(BackupRun $run): bool
    {
        try {
            $disk = (string) config('backups.local_disk', 'backups');
            $full = Storage::disk($disk)->path($run->file_name);

            if (! is_file($full)) {
                return $this->fail($run, 'File missing on local disk.');
            }
            if (filesize($full) === 0) {
                return $this->fail($run, 'File is empty.');
            }

            $actual = $this->crypto->sha256($full);
            if (! hash_equals(strtolower($run->checksum), strtolower($actual))) {
                return $this->fail($run, 'SHA-256 mismatch (corrupt or tampered).');
            }

            // Probe decrypt + decompress of the first bytes without
            // materializing the whole 500MB dump.
            $this->probeIntegrity($full, $run->encrypted);

            $this->runs->update($run, [
                'verification_status' => 'verified',
                'verification_message' => 'SHA-256 OK, decrypt+gzip probe OK.',
            ]);
            $this->audit->log('backup.verified', ['file_name' => $run->file_name], $run->id);

            return true;
        } catch (Throwable $e) {
            return $this->fail($run, mb_substr($e->getMessage(), 0, 1000));
        }
    }

    /**
     * Decrypt (if needed) the head of the file, gunzip the head, and assert
     * it looks like a mysqldump (contains SQL keywords / dump header).
     *
     * @throws \RuntimeException
     */
    private function probeIntegrity(string $fullPath, bool $encrypted): void
    {
        $working = $fullPath;
        $tempDec = null;
        $tempSql = null;

        try {
            if ($encrypted) {
                // Decrypt only the first ~2MiB of ciphertext: secretstream
                // chunks are independent enough for a head probe when we cut
                // at a chunk boundary. Simpler + robust: stream-decrypt the
                // whole file to temp would cost 500MB I/O on every verify.
                // Compromise: full decrypt to temp but delete immediately —
                // I/O cost is acceptable for a daily job and is exact.
                $tempDec = sys_get_temp_dir().DIRECTORY_SEPARATOR.'verify_'.uniqid().'.gz';
                $this->crypto->decrypt($fullPath, $tempDec);
                $working = $tempDec;
            }

            $in = gzopen($working, 'rb');
            if ($in === false) {
                throw new \RuntimeException('Cannot gunzip backup (corrupt gzip?).');
            }
            $head = '';
            for ($i = 0; $i < 8; $i++) {
                $chunk = gzread($in, 65536);
                if ($chunk === false) {
                    gzclose($in);
                    throw new \RuntimeException('Gzip read failed (corrupt?).');
                }
                $head .= $chunk;
                if (gzeof($in)) {
                    break;
                }
            }
            gzclose($in);

            if ($head === '') {
                throw new \RuntimeException('Decompressed content is empty.');
            }
            $probe = strtolower(substr($head, 0, 200000));
            $looksSql = str_contains($probe, 'create table')
                || str_contains($probe, 'insert into')
                || str_contains($probe, 'mysqldump')
                || str_contains($probe, 'drop table');
            if (! $looksSql) {
                throw new \RuntimeException('Decompressed content is not recognizable SQL.');
            }
        } finally {
            if ($tempDec) {
                @unlink($tempDec);
            }
            if ($tempSql) {
                @unlink($tempSql);
            }
        }
    }

    private function fail(BackupRun $run, string $message): bool
    {
        $this->runs->update($run, [
            'verification_status' => 'failed',
            'verification_message' => $message,
        ]);
        $this->audit->log('backup.verification_failed', ['message' => $message], $run->id);

        return false;
    }
}
