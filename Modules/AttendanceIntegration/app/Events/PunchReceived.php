<?php

namespace Modules\AttendanceIntegration\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Attendance\Models\AttendanceSession;
use Modules\AttendanceIntegration\Contracts\AttendanceDeviceInterface;
use Modules\AttendanceIntegration\DTOs\NormalizedPunch;
use Modules\Users\Models\User;

class PunchReceived implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly ?AttendanceDeviceInterface $device,
        public readonly User $user,
        public readonly AttendanceSession $session,
        public readonly NormalizedPunch $punch,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('attendance.live'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'punch.received';
    }

    public function broadcastWith(): array
    {
        return [
            'device' => $this->device?->toArray() ?? ['id' => 0, 'name' => 'Unknown'],
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'employee_code' => $this->user->employee_code,
            ],
            'punch_type' => $this->punch->punchType->value,
            'punched_at' => $this->punch->timestamp->format(DATE_ATOM),
            'session_id' => $this->session->id,
            'status' => $this->session->status,
            'verify_method' => $this->punch->verifyMethod->value,
        ];
    }
}
