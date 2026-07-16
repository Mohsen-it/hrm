<?php

namespace Tests\Unit\Modules\AttendanceIntegration\Events;

use Modules\Attendance\Models\AttendanceSession;
use Modules\AttendanceIntegration\Contracts\AttendanceDeviceInterface;
use Modules\AttendanceIntegration\DTOs\NormalizedPunch;
use Modules\AttendanceIntegration\DTOs\PunchType;
use Modules\AttendanceIntegration\DTOs\VerifyMethod;
use Modules\AttendanceIntegration\Events\PunchReceived;
use Modules\Users\Models\User;
use Tests\TestCase;

class PunchReceivedBroadcastTest extends TestCase
{
    public function test_punch_received_implements_should_broadcast(): void
    {
        $this->assertTrue(
            in_array(
                'Illuminate\Contracts\Broadcasting\ShouldBroadcast',
                class_implements(PunchReceived::class),
                true
            )
        );
    }

    public function test_punch_received_broadcasts_on_private_channel(): void
    {
        $device = $this->createMock(AttendanceDeviceInterface::class);
        $device->method('toArray')->willReturn(['id' => 1, 'name' => 'Test']);

        $user = $this->createMock(User::class);

        $session = $this->getMockBuilder(AttendanceSession::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $session->id = 1;
        $session->status = 'open';

        $punch = new NormalizedPunch(
            deviceUserId: 'EMP001',
            timestamp: new \DateTimeImmutable('2026-07-15 08:00:00'),
            punchType: PunchType::CheckIn,
            verifyMethod: VerifyMethod::Fingerprint,
        );

        $event = new PunchReceived($device, $user, $session, $punch);

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertSame('private-attendance.live', $channels[0]->name);
    }

    public function test_punch_received_broadcast_as_name(): void
    {
        $device = $this->createMock(AttendanceDeviceInterface::class);

        $user = $this->createMock(User::class);

        $session = $this->createMock(AttendanceSession::class);

        $punch = new NormalizedPunch(
            deviceUserId: 'EMP001',
            timestamp: new \DateTimeImmutable,
            punchType: PunchType::CheckIn,
            verifyMethod: VerifyMethod::Fingerprint,
        );

        $event = new PunchReceived($device, $user, $session, $punch);

        $this->assertSame('punch.received', $event->broadcastAs());
    }

    public function test_punch_received_broadcast_with_returns_array(): void
    {
        $device = $this->createMock(AttendanceDeviceInterface::class);
        $device->method('toArray')->willReturn(['id' => 1, 'name' => 'Main Gate']);

        $user = new User;
        $user->id = 42;
        $user->name = 'Ahmed';
        $user->employee_code = 'EMP001';

        $session = new AttendanceSession;
        $session->id = 100;
        $session->status = 'present';

        $punch = new NormalizedPunch(
            deviceUserId: 'EMP001',
            timestamp: new \DateTimeImmutable('2026-07-15 08:00:00'),
            punchType: PunchType::CheckIn,
            verifyMethod: VerifyMethod::Fingerprint,
        );

        $event = new PunchReceived($device, $user, $session, $punch);

        $payload = $event->broadcastWith();

        $this->assertIsArray($payload);
        $this->assertSame('check_in', $payload['punch_type']);
        $this->assertSame('present', $payload['status']);
        $this->assertSame('fingerprint', $payload['verify_method']);
        $this->assertSame(100, $payload['session_id']);
        $this->assertSame('Ahmed', $payload['user']['name']);
    }
}
