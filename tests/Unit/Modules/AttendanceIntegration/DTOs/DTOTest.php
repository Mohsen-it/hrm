<?php

namespace Tests\Unit\Modules\AttendanceIntegration\DTOs;

use Modules\AttendanceIntegration\DTOs\DateRange;
use Modules\AttendanceIntegration\DTOs\DeviceConnectionResult;
use Modules\AttendanceIntegration\DTOs\DeviceInfo;
use Modules\AttendanceIntegration\DTOs\FingerprintTemplateData;
use Modules\AttendanceIntegration\DTOs\NormalizedPunch;
use Modules\AttendanceIntegration\DTOs\PunchType;
use Modules\AttendanceIntegration\DTOs\SyncResult;
use Modules\AttendanceIntegration\DTOs\UserData;
use Modules\AttendanceIntegration\DTOs\VerifyMethod;
use PHPUnit\Framework\TestCase;

class NormalizedPunchTest extends TestCase
{
    public function test_punch_type_enum_values(): void
    {
        $this->assertSame('check_in', PunchType::CheckIn->value);
        $this->assertSame('check_out', PunchType::CheckOut->value);
        $this->assertSame('break_in', PunchType::BreakIn->value);
        $this->assertSame('break_out', PunchType::BreakOut->value);
        $this->assertSame('unknown', PunchType::Unknown->value);
    }

    public function test_verify_method_enum_values(): void
    {
        $this->assertSame('fingerprint', VerifyMethod::Fingerprint->value);
        $this->assertSame('card', VerifyMethod::Card->value);
        $this->assertSame('password', VerifyMethod::Password->value);
        $this->assertSame('face', VerifyMethod::Face->value);
        $this->assertSame('unknown', VerifyMethod::Unknown->value);
    }

    public function test_normalized_punch_creation(): void
    {
        $now = new \DateTimeImmutable;
        $punch = new NormalizedPunch(
            deviceUserId: '123',
            timestamp: $now,
            punchType: PunchType::CheckIn,
            verifyMethod: VerifyMethod::Fingerprint,
        );

        $this->assertSame('123', $punch->deviceUserId);
        $this->assertSame($now, $punch->timestamp);
        $this->assertSame(PunchType::CheckIn, $punch->punchType);
        $this->assertSame(VerifyMethod::Fingerprint, $punch->verifyMethod);
        $this->assertNull($punch->deviceSerial);
        $this->assertNull($punch->uid);
        $this->assertSame(0, $punch->workCode);
    }

    public function test_normalized_punch_to_array(): void
    {
        $now = new \DateTimeImmutable('2026-01-15 08:30:00');
        $punch = new NormalizedPunch(
            deviceUserId: '456',
            timestamp: $now,
            punchType: PunchType::CheckOut,
            verifyMethod: VerifyMethod::Card,
            deviceSerial: 'SN123',
            uid: 10,
            workCode: 1,
            rawStatus: '1',
        );

        $array = $punch->toArray();

        $this->assertSame('456', $array['device_user_id']);
        $this->assertSame('2026-01-15 08:30:00', $array['timestamp']);
        $this->assertSame('check_out', $array['punch_type']);
        $this->assertSame('card', $array['verify_method']);
        $this->assertSame('SN123', $array['device_serial']);
        $this->assertSame(10, $array['uid']);
        $this->assertSame(1, $array['work_code']);
        $this->assertSame('1', $array['raw_status']);
    }

    public function test_device_info_from_array(): void
    {
        $info = DeviceInfo::fromArray([
            'serialnumber' => 'SN001',
            'firmware' => 'v6.0',
            'platform' => 'Linux',
            'device_name' => 'iClock680',
            'users_count' => 150,
            'templates_count' => 300,
            'attendance_count' => 5000,
        ]);

        $this->assertSame('SN001', $info->serialNumber);
        $this->assertSame('v6.0', $info->firmware);
        $this->assertSame('Linux', $info->platform);
        $this->assertSame('iClock680', $info->deviceName);
        $this->assertSame(150, $info->userCount);
        $this->assertSame(300, $info->fingerprintCount);
        $this->assertSame(5000, $info->attendanceCount);
    }

    public function test_device_info_from_array_with_alternative_keys(): void
    {
        $info = DeviceInfo::fromArray([
            'serial_number' => 'SN002',
            'user_count' => 50,
            'fingerprint_count' => 100,
            'attendance_log_count' => 2000,
        ]);

        $this->assertSame('SN002', $info->serialNumber);
        $this->assertSame(50, $info->userCount);
        $this->assertSame(100, $info->fingerprintCount);
        $this->assertSame(2000, $info->attendanceCount);
    }

    public function test_device_info_to_array(): void
    {
        $info = new DeviceInfo(
            serialNumber: 'SN003',
            firmware: 'v1.0',
            userCount: 10,
        );

        $array = $info->toArray();

        $this->assertSame('SN003', $array['serial_number']);
        $this->assertSame('v1.0', $array['firmware']);
        $this->assertSame(10, $array['user_count']);
    }

    public function test_date_range_creation(): void
    {
        $range = new DateRange('2026-01-01', '2026-01-31');
        $this->assertSame('2026-01-01', $range->from);
        $this->assertSame('2026-01-31', $range->to);
    }

    public function test_date_range_invalid_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DateRange('2026-01-31', '2026-01-01');
    }

    public function test_user_data_creation(): void
    {
        $user = new UserData(uid: 1, userId: 'EMP001', name: 'John');
        $this->assertSame(1, $user->uid);
        $this->assertSame('EMP001', $user->userId);
        $this->assertSame('John', $user->name);
    }

    public function test_user_data_from_array(): void
    {
        $user = UserData::fromArray(['uid' => 5, 'user_id' => 'U10', 'name' => 'Ahmed']);
        $this->assertSame(5, $user->uid);
        $this->assertSame('U10', $user->userId);
        $this->assertSame('Ahmed', $user->name);
    }

    public function test_device_connection_result_success(): void
    {
        $result = DeviceConnectionResult::success();
        $this->assertTrue($result->connected);
        $this->assertNull($result->error);
    }

    public function test_device_connection_result_failure(): void
    {
        $result = DeviceConnectionResult::failure('Connection timeout');
        $this->assertFalse($result->connected);
        $this->assertSame('Connection timeout', $result->error);
    }

    public function test_fingerprint_template_data(): void
    {
        $tpl = new FingerprintTemplateData(uid: 1, fingerId: 0, templateData: 'abc123');
        $this->assertSame(1, $tpl->uid);
        $this->assertSame(0, $tpl->fingerId);
        $this->assertSame('abc123', $tpl->templateData);
    }

    public function test_sync_result_empty(): void
    {
        $result = SyncResult::empty(1, 'Device1', 'SN001');
        $this->assertSame(1, $result->device_id);
        $this->assertSame('Device1', $result->device_name);
        $this->assertSame('SN001', $result->serial_number);
        $this->assertEmpty($result->steps);
        $this->assertEmpty($result->errors);
        $this->assertSame(0, $result->durationSeconds);
    }

    public function test_sync_result_to_array(): void
    {
        $result = new SyncResult(
            device_id: 1,
            device_name: 'Test',
            serial_number: 'SN1',
            steps: [['name' => 'info', 'status' => 'ok']],
            totals: ['users_matched' => 5],
            errors: [],
            durationSeconds: 1.5,
            startedAt: '2026-01-01 00:00:00',
            finishedAt: '2026-01-01 00:00:02',
        );

        $array = $result->toArray();
        $this->assertArrayHasKey('device_id', $array);
        $this->assertArrayHasKey('totals', $array);
        $this->assertArrayHasKey('duration_seconds', $array);
    }
}
