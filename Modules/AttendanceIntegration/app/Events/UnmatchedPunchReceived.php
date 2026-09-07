<?php

namespace Modules\AttendanceIntegration\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\AttendanceIntegration\Contracts\AttendanceDeviceInterface;
use Modules\AttendanceIntegration\DTOs\NormalizedPunch;
use Modules\AttendanceIntegration\DTOs\PunchType;
use Modules\Users\Models\User;

/**
 * Fired for every resolved-user punch that produced NO session
 * (Unknown / Break punches). Feeds the live-scan cache so the page shows
 * reality instead of silently dropping legitimate punches.
 *
 * Deliberately NOT broadcast: no frontend subscribes to sockets today, and
 * broadcasting would only add queue load (see BROADCAST_CONNECTION=log).
 */
class UnmatchedPunchReceived
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly ?AttendanceDeviceInterface $device,
        public readonly User $user,
        public readonly NormalizedPunch $punch,
        public readonly ?PunchType $classifiedPunchType = null,
    ) {}
}
