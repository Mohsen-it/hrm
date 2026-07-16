<?php

namespace Modules\Attendance\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Attendance\Models\AttendanceSession;

/**
 * SessionCreated — fired after a brand-new `attendance_sessions` row has been
 * persisted by `AttendanceSessionService::checkIn` or by the raw-log pipeline.
 *
 * Listeners use it to invalidate the attendance cache and to schedule a
 * daily-summary recalculation for the affected (user, date) pair.
 */
class SessionCreated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public AttendanceSession $session,
    ) {}
}
