<?php

namespace Modules\Backups\Services;

use RuntimeException;

/**
 * Streaming compression + authenticated encryption for large dumps.
 *
 * - Compression: gzip streaming (gzopen), never loads the whole dump.
 * - Encryption: libsodium secretstream (XChaCha20-Poly1305), chunked,
 *   authenticated. File layout: [24-byte header][stream chunks].
 *   Key: 32 bytes from BACKUP_ENCRYPTION_KEY (base64).
 * - Integrity: SHA-256 over the final stored file.
 */
class BackupCryptoService
{
    public const CHUNK_SIZE = 1024 * 1024; // 1 MiB

    /**
     * @return array{key: string}
     */
    public function resolveKey(): array
    {
        $raw = (string) config('backups.encryption_key', '');

        if ($raw === '') {
            throw new RuntimeException('BACKUP_ENCRYPTION_KEY is not set. Generate one: php -r "echo base64_encode(random_bytes(32));"');
        }

        $key = base64_decode($raw, true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new RuntimeException('BACKUP_ENCRYPTION_KEY must be base64 of exactly 32 bytes.');
        }

        if (! function_exists('sodium_crypto_secretstream_xchacha20poly1305_init_push')) {
            throw new RuntimeException('PHP sodium extension is required for backup encryption.');
        }

        return ['key' => $key];
    }

    public static function generateKey(): string
    {
        return base64_encode(random_bytes(SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES));
    }

    /**
     * gzip $source → $destination (streaming).
     */
    public function compress(string $source, string $destination): void
    {
        $in = fopen($source, 'rb');
        if ($in === false) {
            throw new RuntimeException("Cannot open for compression: {$source}");
        }

        $out = gzopen($destination, 'wb6');
        if ($out === false) {
            fclose($in);
            throw new RuntimeException("Cannot open gzip output: {$destination}");
        }

        try {
            while (! feof($in)) {
                $chunk = fread($in, self::CHUNK_SIZE);
                if ($chunk === false) {
                    throw new RuntimeException('Read failed during compression.');
                }
                if ($chunk !== '' && gzwrite($out, $chunk) === false) {
                    throw new RuntimeException('Write failed during compression.');
                }
            }
        } finally {
            fclose($in);
            gzclose($out);
        }
    }

    /**
     * gunzip $source → $destination (streaming).
     */
    public function decompress(string $source, string $destination): void
    {
        $in = gzopen($source, 'rb');
        if ($in === false) {
            throw new RuntimeException("Cannot open gzip input (corrupt?): {$source}");
        }

        $out = fopen($destination, 'wb');
        if ($out === false) {
            gzclose($in);
            throw new RuntimeException("Cannot open decompress output: {$destination}");
        }

        try {
            while (! gzeof($in)) {
                $chunk = gzread($in, self::CHUNK_SIZE);
                if ($chunk === false) {
                    throw new RuntimeException('Read failed during decompression (corrupt gzip?).');
                }
                if ($chunk !== '' && fwrite($out, $chunk) === false) {
                    throw new RuntimeException('Write failed during decompression.');
                }
            }
        } finally {
            gzclose($in);
            fclose($out);
        }
    }

    /**
     * Encrypt $source → $destination with secretstream (streaming).
     */
    public function encrypt(string $source, string $destination): void
    {
        ['key' => $key] = $this->resolveKey();

        $in = fopen($source, 'rb');
        if ($in === false) {
            throw new RuntimeException("Cannot open for encryption: {$source}");
        }

        $out = fopen($destination, 'wb');
        if ($out === false) {
            fclose($in);
            throw new RuntimeException("Cannot open encrypt output: {$destination}");
        }

        try {
            [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
            if (fwrite($out, $header) === false) {
                throw new RuntimeException('Failed writing encryption header.');
            }

            while (! feof($in)) {
                $chunk = fread($in, self::CHUNK_SIZE);
                if ($chunk === false) {
                    throw new RuntimeException('Read failed during encryption.');
                }
                if ($chunk === '' && feof($in)) {
                    break;
                }
                $tag = feof($in)
                    ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                    : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
                $cipher = sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $tag);
                if (fwrite($out, $cipher) === false) {
                    throw new RuntimeException('Write failed during encryption.');
                }
                if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    break;
                }
            }
        } finally {
            fclose($in);
            fclose($out);
            sodium_memzero($key);
        }
    }

    /**
     * Decrypt $source → $destination with secretstream (streaming).
     *
     * @throws RuntimeException on tamper / wrong key / truncation.
     */
    public function decrypt(string $source, string $destination): void
    {
        ['key' => $key] = $this->resolveKey();

        $in = fopen($source, 'rb');
        if ($in === false) {
            throw new RuntimeException("Cannot open for decryption: {$source}");
        }

        $header = fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
        if ($header === false || strlen($header) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES) {
            fclose($in);
            throw new RuntimeException('Encrypted file truncated (bad header).');
        }

        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
        if ($state === false) {
            fclose($in);
            throw new RuntimeException('Failed initializing decryption (wrong key?).');
        }

        $out = fopen($destination, 'wb');
        if ($out === false) {
            fclose($in);
            throw new RuntimeException("Cannot open decrypt output: {$destination}");
        }

        try {
            $overhead = SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;
            while (! feof($in)) {
                $chunk = fread($in, self::CHUNK_SIZE + $overhead);
                if ($chunk === false) {
                    throw new RuntimeException('Read failed during decryption.');
                }
                if ($chunk === '') {
                    break;
                }
                $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $chunk);
                if ($result === false) {
                    throw new RuntimeException('Decryption failed: tampered data or wrong key.');
                }
                [$plain, $tag] = $result;
                if (fwrite($out, $plain) === false) {
                    throw new RuntimeException('Write failed during decryption.');
                }
                if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    break;
                }
            }
        } finally {
            fclose($in);
            fclose($out);
            sodium_memzero($key);
        }
    }

    public function sha256(string $file): string
    {
        $hash = hash_file('sha256', $file);
        if ($hash === false) {
            throw new RuntimeException("Cannot hash file: {$file}");
        }

        return $hash;
    }

    /**
     * Build the timestamped base name: hrmair_YYYY-MM-DD_HH-mm-ss
     */
    public static function baseName(string $database, ?\DateTimeInterface $at = null): string
    {
        $at ??= now();
        $stamp = $at->format('Y-m-d_H-i-s');

        return "{$database}_{$stamp}";
    }
}
