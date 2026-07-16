<?php

namespace Modules\Attendance\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Modules\Attendance\Models\GroupSchedule;
use Modules\Attendance\Repositories\GroupScheduleRepository;

/**
 * GroupScheduleService — manages group schedules (shift assignments for groups).
 */
class GroupScheduleService
{
    public function __construct(
        private GroupScheduleRepository $scheduleRepository,
    ) {}

    public function createGroupSchedule(array $data): GroupSchedule
    {
        $hasOverlap = $this->scheduleRepository->hasOverlap(
            $data['group_id'],
            $data['start_date'],
            $data['end_date']
        );

        if ($hasOverlap) {
            throw new InvalidArgumentException('يوجد تداخل مع جدول آخر لنفس الفئة.');
        }

        return $this->scheduleRepository->create($data);
    }

    public function updateGroupSchedule(GroupSchedule $schedule, array $data): GroupSchedule
    {
        $startDate = $data['start_date'] ?? $schedule->start_date;
        $endDate = $data['end_date'] ?? $schedule->end_date;

        $hasOverlap = $this->scheduleRepository->hasOverlap(
            $schedule->group_id,
            $startDate,
            $endDate,
            $schedule->id
        );

        if ($hasOverlap) {
            throw new InvalidArgumentException('يوجد تداخل مع جدول آخر لنفس الفئة.');
        }

        return $this->scheduleRepository->update($schedule, $data);
    }

    public function deleteGroupSchedule(GroupSchedule $schedule): bool
    {
        if ($schedule->end_date->toDateString() >= now()->toDateString()) {
            throw new InvalidArgumentException('لا يمكن حذف الجدول لكونه في فترة حالية أو مستقبلية.');
        }

        return $this->scheduleRepository->delete($schedule);
    }

    public function getActiveScheduleForGroup(int $groupId, string $date): ?GroupSchedule
    {
        return $this->scheduleRepository->getActiveForGroup($groupId, $date);
    }

    public function getSchedulesForGroup(int $groupId): Collection
    {
        return $this->scheduleRepository->getByGroup($groupId);
    }

    public function getAllSchedules(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->scheduleRepository->getAll($filters, $perPage);
    }

    public function getScheduleWithDetails(int $scheduleId): GroupSchedule
    {
        $schedule = $this->scheduleRepository->findById($scheduleId);

        if (! $schedule) {
            throw new InvalidArgumentException('الجدول غير موجود.');
        }

        return $schedule;
    }
}
