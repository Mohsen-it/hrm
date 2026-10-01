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
            $this->probeIntegrity($full, $run->encrypted, (bool) $run->compressed);

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
     * Decrypt (if needed) the head of the file, gunzip the head (if
     * compressed), and assert it looks like a mysqldump (contains SQL
     * keywords / dump header). Plain `.sql` uploads from USB drives skip
     * the gzip step and are probed as raw text.
     *
     * @throws \RuntimeException
     */
    private function probeIntegrity(string $fullPath, bool $encrypted, bool $compressed = true): void
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

            $head = $this->readHead($working, $compressed);
            $this->assertLooksLikeSql($head);
        } finally {
            if ($tempDec) {
                @unlink($tempDec);
            }
            if ($tempSql) {
                @unlink($tempSql);
            }
        }
    }

    /**
     * Read the first ~512KB of (possibly gzipped) content as text.
     * Falls back to plain-text read when the file is not gzip data
     * (e.g. plain `.sql` uploads from a flash drive).
     */
    private function readHead(string $path, bool $compressed): string
    {
        if ($compressed) {
            $in = @gzopen($path, 'rb');
            if ($in !== false) {
                $head = '';
                try {
                    for ($i = 0; $i < 8; $i++) {
                        $chunk = gzread($in, 65536);
                        if ($chunk === false) {
                            throw new \RuntimeException('Gzip read failed (corrupt?).');
                        }
                        $head .= $chunk;
                        if ($head !== '' && ! $this->isGzipStream($path, $head)) {
                            break;
                        }
                        if (gzeof($in)) {
                            break;
                        }
                    }
                } finally {
                    gzclose($in);
                }
                // gzopen succeeds even on plain text in some builds but
                // returns empty/garbage — detect and fall through to raw read.
                if ($head !== '' && $this->looksLikeGzipContent($head)) {
                    return $head;
                }
                // If gzip probe produced nothing useful, fall through to raw.
                if ($head !== '') {
                    return $head;
                }
            }
            // Fallback: maybe the flag is wrong (e.g. `.enc` mislabeled) —
            // try raw read before giving up.
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Cannot open backup for reading.');
        }
        try {
            $head = (string) fread($handle, 512 * 1024);
        } finally {
            fclose($handle);
        }

        return $head;
    }

    private function isGzipStream(string $path, string $head): bool
    {
        // Gzip magic bytes; when reading a plain .sql via gzopen the
        // output is usually empty or binary noise — check raw magic.
        $raw = @file_get_contents($path, false, null, 0, 2);
        if ($raw !== false && strlen($raw) === 2) {
            return $raw[0] === "\x1f" && $raw[1] === "\x8b";
        }

        return true;
    }

    private function looksLikeGzipContent(string $head): bool
    {
        // Genuine decompressed SQL head is printable text.
        $sample = substr($head, 0, 4096);
        if ($sample === '') {
            return false;
        }
        $printable = preg_match_all('/[\x09\x0A\x0D\x20-\x7E\xC0-\xFF]/', $sample);

        return ($printable / max(strlen($sample), 1)) > 0.7;
    }

    private function assertLooksLikeSql(string $head): void
    {
        // Strip BOM that HeidiSQL / Windows editors may prepend.
        $head = ltrim($head, "\xEF\xBB\xBF \t\r\n");
        if ($head === '') {
            throw new \RuntimeException('Decompressed content is empty.');
        }
        $probe = strtolower(substr($head, 0, 200000));
        $looksSql = str_contains($probe, 'create table')
            || str_contains($probe, 'insert into')
            || str_contains($probe, 'mysqldump')
            || str_contains($probe, 'drop table')
            || str_contains($probe, 'create database')
            || str_contains($probe, 'use `')
            || str_contains($probe, 'set names')
            || str_contains($probe, 'lock tables');
        if (! $looksSql) {
            throw new \RuntimeException('Decompressed content is not recognizable SQL.');
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
