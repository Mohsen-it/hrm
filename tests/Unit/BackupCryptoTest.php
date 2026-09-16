<?php

namespace Tests\Unit;

use Modules\Backups\Services\BackupCryptoService;
use Tests\TestCase;

/**
 * Unit: filename format, SHA-256, gzip + sodium round-trips (plan §22).
 */
class BackupCryptoTest extends TestCase
{
    private string $tmp;

    private BackupCryptoService $crypto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'backup_test_'.uniqid();
        mkdir($this->tmp, 0755, true);

        config(['backups.encryption_key' => base64_encode(random_bytes(32))]);
        $this->crypto = new BackupCryptoService;
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tmp.DIRECTORY_SEPARATOR.'*') ?: []);
        @rmdir($this->tmp);
        parent::tearDown();
    }

    public function test_base_name_format(): void
    {
        $at = new \DateTimeImmutable('2026-09-14 02:00:00');
        $this->assertSame('hrmair_2026-09-14_02-00-00', BackupCryptoService::baseName('hrmair', $at));
    }

    public function test_compress_decompress_roundtrip(): void
    {
        $src = $this->tmp.'/a.sql';
        $gz = $this->tmp.'/a.sql.gz';
        $out = $this->tmp.'/a.out.sql';
        // Arabic + binary-ish payload (mirrors --hex-blob + utf8mb4 needs).
        file_put_contents($src, "CREATE TABLE t (id INT);\nINSERT INTO t VALUES ('موظف اختبار', X'00FF');\n".str_repeat('x', 100000));

        $this->crypto->compress($src, $gz);
        $this->assertLessThan(filesize($src), filesize($gz));

        $this->crypto->decompress($gz, $out);
        $this->assertSame(file_get_contents($src), file_get_contents($out));
    }

    public function test_encrypt_decrypt_roundtrip(): void
    {
        $src = $this->tmp.'/b.gz';
        $enc = $this->tmp.'/b.gz.enc';
        $out = $this->tmp.'/b.out.gz';
        file_put_contents($src, random_bytes(50000));

        $this->crypto->encrypt($src, $enc);
        $this->assertNotEquals(file_get_contents($src), file_get_contents($enc));

        $this->crypto->decrypt($enc, $out);
        $this->assertSame(file_get_contents($src), file_get_contents($out));
    }

    public function test_decrypt_with_wrong_key_fails(): void
    {
        $src = $this->tmp.'/c.gz';
        $enc = $this->tmp.'/c.gz.enc';
        $out = $this->tmp.'/c.out.gz';
        file_put_contents($src, 'secret');

        $this->crypto->encrypt($src, $enc);

        config(['backups.encryption_key' => base64_encode(random_bytes(32))]);
        $other = new BackupCryptoService;

        $this->expectException(\RuntimeException::class);
        $other->decrypt($enc, $out);
    }

    public function test_sha256_matches_php_hash(): void
    {
        $src = $this->tmp.'/d.bin';
        file_put_contents($src, 'hello');
        $this->assertSame(hash('sha256', 'hello'), $this->crypto->sha256($src));
    }
}
