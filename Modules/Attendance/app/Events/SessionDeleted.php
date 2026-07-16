<?php

namespace Modules\Attendance\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Attendance\Models\AttendanceSession;

/**
 * SessionDeleted — fired after an `attendance_sessions` row is soft-deleted.
 *
 * Listeners use it to invalidate the attendance cache and to trigger a
 * daily-summary recalculation so the daily roll-up reflects the removal.
 */
class SessionDeleted
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * The original (still loaded) session is passed so listeners can read
     * the (user_id, attendance_date) pair even after the row is gone.
     */
    public function __construct(
        public AttendanceSession $session,
    ) {}
}
