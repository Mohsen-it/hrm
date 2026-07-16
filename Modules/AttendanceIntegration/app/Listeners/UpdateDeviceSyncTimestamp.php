<?php

namespace Modules\AttendanceIntegration\Listeners;

use Modules\AttendanceIntegration\Contracts\DeviceRepositoryInterface;
use Modules\AttendanceIntegration\Events\DeviceSyncCompleted;

class UpdateDeviceSyncTimestamp
{
    public function __construct(
        private DeviceRepositoryInterface $deviceRepository,
    ) {}

    public function handle(DeviceSyncCompleted $event): void
    {
        $this->deviceRepository->updateSyncTimestamp($event->device);
    }
}
