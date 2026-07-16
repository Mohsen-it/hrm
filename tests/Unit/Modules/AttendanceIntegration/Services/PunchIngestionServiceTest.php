<?php

namespace Tests\Unit\Modules\AttendanceIntegration\Services;

use Modules\Attendance\Services\AttendanceSessionService;
use Modules\Attendance\Services\RawAttendanceLogService;
use Modules\AttendanceIntegration\Contracts\AttendanceDeviceInterface;
use Modules\AttendanceIntegration\Contracts\DeviceRepositoryInterface;
use Modules\AttendanceIntegration\DTOs\NormalizedPunch;
use Modules\AttendanceIntegration\DTOs\PunchType;
use Modules\AttendanceIntegration\DTOs\VerifyMethod;
use Modules\AttendanceIntegration\Services\AuditLogger;
use Modules\AttendanceIntegration\Services\PunchIngestionService;
use Tests\TestCase;

class PunchIngestionServiceTest extends TestCase
{
    private PunchIngestionService $service;

    private $deviceRepository;

    private $sessionService;

    private $rawLogService;

    private $auditLogger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deviceRepository = $this->createMock(DeviceRepositoryInterface::class);
        $this->sessionService = $this->createMock(AttendanceSessionService::class);
        $this->rawLogService = $this->createMock(RawAttendanceLogService::class);
        $this->auditLogger = $this->createMock(AuditLogger::class);

        $this->service = new PunchIngestionService(
            $this->deviceRepository,
            $this->sessionService,
            $this->rawLogService,
            $this->auditLogger,
        );
    }

    public function test_ingest_returns_null_when_user_not_found(): void
    {
        $device = $this->createMock(AttendanceDeviceInterface::class);
        $device->method('getId')->willReturn(1);

        $punch = new NormalizedPunch(
            deviceUserId: 'NONEXISTENT',
            timestamp: new \DateTimeImmutable('2026-01-15 08:00:00'),
            punchType: PunchType::CheckIn,
            verifyMethod: VerifyMethod::Fingerprint,
        );

        $result = $this->service->ingest($device, $punch);

        $this->assertNull($result);
    }

    public function test_ingest_returns_null_for_empty_device_user_id(): void
    {
        $device = $this->createMock(AttendanceDeviceInterface::class);
        $device->method('getId')->willReturn(1);

        $punch = new NormalizedPunch(
            deviceUserId: '',
            timestamp: new \DateTimeImmutable('2026-01-15 08:00:00'),
            punchType: PunchType::CheckIn,
            verifyMethod: VerifyMethod::Fingerprint,
        );

        $result = $this->service->ingest($device, $punch);

        $this->assertNull($result);
    }

    public function test_ingest_returns_null_for_unknown_user_without_log_creation(): void
    {
        $device = $this->createMock(AttendanceDeviceInterface::class);
        $device->method('getId')->willReturn(5);

        $punch = new NormalizedPunch(
            deviceUserId: 'NOUSER',
            timestamp: new \DateTimeImmutable('2026-01-15 08:00:00'),
            punchType: PunchType::CheckIn,
            verifyMethod: VerifyMethod::Fingerprint,
        );

        $result = $this->service->ingest($device, $punch);

        $this->assertNull($result);
    }
}
