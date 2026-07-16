<?php

namespace Modules\Attendance\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Attendance\Models\AttendanceSession;

/**
 * SessionUpdated — fired after an `attendance_sessions` row is updated
 * (status / timing columns changed, check-out recorded, manual notes added).
 *
 * Listeners use it to invalidate the attendance cache and to trigger a
 * daily-summary recalculation when the change affects the totals.
 */
class SessionUpdated
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
