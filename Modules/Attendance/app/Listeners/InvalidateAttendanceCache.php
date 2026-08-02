<?php

namespace Modules\Attendance\Listeners;

use Illuminate\Support\Facades\Cache;
use Modules\Attendance\Events\SessionCreated;
use Modules\Attendance\Events\SessionDeleted;
use Modules\Attendance\Events\SessionUpdated;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Services\AttendanceCacheService;
use Modules\Attendance\Services\DailyAttendanceSummaryService;

/**
 * InvalidateAttendanceCache — wipe the attendance cache on session changes.
 *
 * Bound to `SessionCreated`, `SessionUpdated`, and `SessionDeleted`. The
 * session service stamps the cache with a coarse `attendance:*` namespace
 * (see {@see AttendanceCacheService::TAG}), so the cheapest correct
 * invalidation is a full flush of the module.
 *
 * A future optimisation could invalidate only the per-user / per-date keys,
 * but a single flush keeps the listener simple and the operations rare
 * enough to be safe.
 */
class InvalidateAttendanceCache
{
    /**
     * Create a new listener instance.
     */
    public function __construct(
        private AttendanceCacheService $cache,
        private DailyAttendanceSummaryService $dailySummaryService,
    ) {}

    /**
     * Handle a `SessionCreated` event.
     */
    public function handleCreated(SessionCreated $event): void
    {
        $this->refreshAttendanceState($event->session);
    }

    /**
     * Handle a `SessionUpdated` event.
     */
    public function handleUpdated(SessionUpdated $event): void
    {
        $this->refreshAttendanceState($event->session);
    }

    /**
     * Handle a `SessionDeleted` event.
     */
    public function handleDeleted(SessionDeleted $event): void
    {
        $this->refreshAttendanceState($event->session);
    }

    /**
     * Convenience subscribe entry-point used when the listener is wired
     * manually (e.g. from the EventServiceProvider).
     *
     * @return array<string, string>
     */
    public function subscribe(): array
    {
        return [
            SessionCreated::class => 'handleCreated',
            SessionUpdated::class => 'handleUpdated',
            SessionDeleted::class => 'handleDeleted',
        ];
    }

    /**
     * Rebuild the one affected daily roll-up and invalidate the operational
     * dashboard immediately after a session changes.
     */
    private function refreshAttendanceState(AttendanceSession $session): void
    {
        $this->dailySummaryService->recalculateForUserAndDate(
            $session->user_id,
            $session->attendance_date->toDateString(),
        );

        $this->cache->flush();
        Cache::forget('dashboard:full_data:'.$session->attendance_date->toDateString());
    }
}
