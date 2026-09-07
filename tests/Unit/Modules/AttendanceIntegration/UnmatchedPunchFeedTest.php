<?php

namespace Tests\Unit\Modules\AttendanceIntegration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Attendance\Models\RawAttendanceLog;
use Modules\Attendance\Services\AttendanceSessionService;
use Modules\Attendance\Services\RawAttendanceLogService;
use Modules\AttendanceIntegration\Contracts\AttendanceDeviceInterface;
use Modules\AttendanceIntegration\Contracts\DeviceRepositoryInterface;
use Modules\AttendanceIntegration\DTOs\NormalizedPunch;
use Modules\AttendanceIntegration\DTOs\PunchType;
use Modules\AttendanceIntegration\DTOs\VerifyMethod;
use Modules\AttendanceIntegration\Services\AuditLogger;
use Modules\AttendanceIntegration\Services\PunchIngestionService;
use Modules\AttendanceIntegration\Services\SchedulePunchClassifierService;
use Modules\Users\Models\User;
use Tests\TestCase;

/**
 * Unmatched-punch live feed suite.
 *
 * A punch that yields no session (Unknown / Break) must still appear on
 * live-scan instantly — clearly marked — without touching session or
 * absence logic in any way.
 */
class UnmatchedPunchFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_punch_appears_on_live_feed_without_session(): void
    {
        $user = $this->makeUser('EMP-U1');
        $service = $this->makeService(PunchType::Unknown);

        $result = $service->ingest($this->makeDevice(), $this->makePunch('EMP-U1'));

        $this->assertNull($result);
        $items = Cache::get('attendanceintegration:live_punches:recent', []);
        $this->assertCount(1, $items);
        $this->assertSame('unknown', $items[0]['punch_type']);
        $this->assertNull($items[0]['session_id']);
        $this->assertSame('unmatched', $items[0]['status']);
        $this->assertSame('EMP-U1', $items[0]['user']['employee_code']);
    }

    public function test_break_punch_appears_on_live_feed_without_session(): void
    {
        $user = $this->makeUser('EMP-U2');
        $this->assertTrue($user->exists);
        $service = $this->makeService(PunchType::BreakOut);

        $result = $service->ingest($this->makeDevice(), $this->makePunch('EMP-U2'));

        $this->assertNull($result);
        $items = Cache::get('attendanceintegration:live_punches:recent', []);
        $this->assertCount(1, $items);
        $this->assertSame('break_out', $items[0]['punch_type']);
    }

    public function test_unresolved_user_never_touches_live_feed(): void
    {
        $service = $this->makeService(PunchType::Unknown);

        $result = $service->ingest($this->makeDevice(), $this->makePunch('GHOST'));

        $this->assertNull($result);
        $this->assertSame([], Cache::get('attendanceintegration:live_punches:recent', []));
    }

    private function makeService(PunchType $classification): PunchIngestionService
    {
        $classifier = $this->createMock(SchedulePunchClassifierService::class);
        $classifier->method('classify')->willReturn($classification);

        // NOTE: checkIn/checkOut are stubbed by PHPUnit's automatic return
        // generation (non-nullable AttendanceSession). Unknown/Break
        // classifications never reach them (match default → null session).
        $sessionService = $this->createMock(AttendanceSessionService::class);

        $rawLog = $this->createMock(RawAttendanceLog::class);
        $rawLog->method('markProcessed')->willReturn(true);
        $rawLog->id = 999;
        $rawLogService = $this->createMock(RawAttendanceLogService::class);
        $rawLogService->method('createLog')->willReturn($rawLog);

        return new PunchIngestionService(
            $this->createMock(DeviceRepositoryInterface::class),
            $sessionService,
            $rawLogService,
            $this->createMock(AuditLogger::class),
            $classifier,
        );
    }

    private function makeDevice(): AttendanceDeviceInterface
    {
        $device = $this->createMock(AttendanceDeviceInterface::class);
        $device->method('getId')->willReturn(1);
        $device->method('getSerialNumber')->willReturn('TEST-001');
        $device->method('getIpAddress')->willReturn('127.0.0.1');
        $device->method('toArray')->willReturn(['id' => 1, 'name' => 'Test Device']);

        return $device;
    }

    private function makePunch(string $pin): NormalizedPunch
    {
        return new NormalizedPunch(
            deviceUserId: $pin,
            timestamp: new \DateTimeImmutable('2026-09-03 13:19:49'),
            punchType: PunchType::CheckIn,
            verifyMethod: VerifyMethod::Fingerprint,
        );
    }

    private function makeUser(string $employeeCode): User
    {
        return User::create([
            'employee_code' => $employeeCode,
            'name' => $employeeCode,
            'full_name_ar' => $employeeCode,
            'email' => strtolower($employeeCode).'@test.local',
            'password' => bcrypt('password'),
            'status' => 1,
            'is_active_employee' => true,
        ]);
    }
}
