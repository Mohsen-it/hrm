<?php

namespace Tests\Unit\Modules\AttendanceIntegration\Drivers\ZKTeco;

use Modules\AttendanceIntegration\Drivers\ZKTeco\ZKTecoPunchNormalizer;
use Modules\AttendanceIntegration\DTOs\PunchType;
use Modules\AttendanceIntegration\DTOs\VerifyMethod;
use PHPUnit\Framework\TestCase;

class ZKTecoPunchNormalizerTest extends TestCase
{
    private ZKTecoPunchNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new ZKTecoPunchNormalizer;
    }

    public function test_get_driver_name(): void
    {
        $this->assertSame('zkteco', $this->normalizer->getDriverName());
    }

    public function test_normalize_check_in_by_explicit_type(): void
    {
        $punch = $this->normalizer->normalize([
            'user_id' => 'EMP001',
            'timestamp' => '2026-01-15 08:00:00',
            'punch_type' => 'check_in',
        ]);

        $this->assertSame('EMP001', $punch->deviceUserId);
        $this->assertSame(PunchType::CheckIn, $punch->punchType);
        $this->assertSame('2026-01-15 08:00:00', $punch->timestamp->format('Y-m-d H:i:s'));
    }

    public function test_normalize_check_out_by_explicit_type(): void
    {
        $punch = $this->normalizer->normalize([
            'user_id' => 'EMP002',
            'timestamp' => '2026-01-15 17:00:00',
            'punch_type' => 'check_out',
        ]);

        $this->assertSame(PunchType::CheckOut, $punch->punchType);
    }

    public function test_normalize_check_in_by_status_zero(): void
    {
        $punch = $this->normalizer->normalize([
            'user_id' => 'EMP003',
            'timestamp' => '2026-01-15 08:00:00',
            'status' => 0,
        ]);

        $this->assertSame(PunchType::CheckIn, $punch->punchType);
    }

    public function test_normalize_check_out_by_status_one(): void
    {
        $punch = $this->normalizer->normalize([
            'user_id' => 'EMP004',
            'timestamp' => '2026-01-15 17:00:00',
            'status' => 1,
        ]);

        $this->assertSame(PunchType::CheckOut, $punch->punchType);
    }

    public function test_normalize_break_out_by_status_two(): void
    {
        $punch = $this->normalizer->normalize([
            'user_id' => 'EMP005',
            'timestamp' => '2026-01-15 12:00:00',
            'status' => 2,
        ]);

        $this->assertSame(PunchType::BreakOut, $punch->punchType);
    }

    public function test_normalize_break_in_by_status_three(): void
    {
        $punch = $this->normalizer->normalize([
            'user_id' => 'EMP006',
            'timestamp' => '2026-01-15 13:00:00',
            'status' => 3,
        ]);

        $this->assertSame(PunchType::BreakIn, $punch->punchType);
    }

    public function test_normalize_unknown_type_returns_unknown(): void
    {
        $punch = $this->normalizer->normalize([
            'user_id' => 'EMP007',
            'timestamp' => '2026-01-15 08:00:00',
            'status' => 99,
        ]);

        $this->assertSame(PunchType::Unknown, $punch->punchType);
    }

    public function test_normalize_fingerprint_verify_by_default(): void
    {
        $punch = $this->normalizer->normalize([
            'user_id' => 'EMP008',
            'timestamp' => '2026-01-15 08:00:00',
        ]);

        $this->assertSame(VerifyMethod::Fingerprint, $punch->verifyMethod);
    }

    public function test_normalize_card_verify_by_status(): void
    {
        $punch = $this->normalizer->normalize([
            'user_id' => 'EMP009',
            'timestamp' => '2026-01-15 08:00:00',
            'punch' => 2,
        ]);

        $this->assertSame(VerifyMethod::Card, $punch->verifyMethod);
    }

    public function test_normalize_password_verify_by_status(): void
    {
        $punch = $this->normalizer->normalize([
            'user_id' => 'EMP010',
            'timestamp' => '2026-01-15 08:00:00',
            'punch' => 4,
        ]);

        $this->assertSame(VerifyMethod::Password, $punch->verifyMethod);
    }

    public function test_normalize_preserves_serial(): void
    {
        $punch = $this->normalizer->normalize([
            'user_id' => 'EMP011',
            'timestamp' => '2026-01-15 08:00:00',
            'SN' => 'SERIAL123',
        ]);

        $this->assertSame('SERIAL123', $punch->deviceSerial);
    }

    public function test_normalize_preserves_uid(): void
    {
        $punch = $this->normalizer->normalize([
            'user_id' => 'EMP012',
            'timestamp' => '2026-01-15 08:00:00',
            'uid' => 42,
        ]);

        $this->assertSame(42, $punch->uid);
    }

    public function test_normalize_preserves_work_code(): void
    {
        $punch = $this->normalizer->normalize([
            'user_id' => 'EMP013',
            'timestamp' => '2026-01-15 08:00:00',
            'work_code' => 5,
        ]);

        $this->assertSame(5, $punch->workCode);
    }

    public function test_normalize_falls_back_to_now_on_missing_timestamp(): void
    {
        $before = new \DateTimeImmutable('-1 second');
        $punch = $this->normalizer->normalize([
            'user_id' => 'EMP014',
        ]);
        $after = new \DateTimeImmutable('+1 second');

        $this->assertGreaterThanOrEqual($before->getTimestamp(), $punch->timestamp->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $punch->timestamp->getTimestamp());
    }

    public function test_normalize_falls_back_to_now_on_invalid_timestamp(): void
    {
        $punch = $this->normalizer->normalize([
            'user_id' => 'EMP015',
            'timestamp' => 'not-a-date',
        ]);

        $this->assertNotNull($punch->timestamp);
    }

    public function test_normalize_preserves_raw_data(): void
    {
        $raw = [
            'user_id' => 'EMP016',
            'timestamp' => '2026-01-15 08:00:00',
            'status' => 0,
            'custom_field' => 'value',
        ];

        $punch = $this->normalizer->normalize($raw);

        $this->assertSame($raw, $punch->rawData);
    }

    public function test_normalize_via_static_method(): void
    {
        $punch = ZKTecoPunchNormalizer::normalizeStatic([
            'user_id' => 'EMP017',
            'timestamp' => '2026-01-15 09:00:00',
            'punch_type' => 'check_in',
        ]);

        $this->assertSame('EMP017', $punch->deviceUserId);
        $this->assertSame(PunchType::CheckIn, $punch->punchType);
    }
}
