<?php

namespace Modules\AttendanceIntegration\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\AttendanceIntegration\Contracts\AttendanceDeviceInterface;
use Modules\AttendanceIntegration\DTOs\SyncResult;

class DeviceSyncCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly AttendanceDeviceInterface $device,
        public readonly SyncResult $result,
    ) {}
}
