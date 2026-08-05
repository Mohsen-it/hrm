<?php

namespace Modules\Vacations\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Shifts\Services\ScheduleResolverService;
use Modules\Vacations\Models\AttendanceJustificationRequest;
use Modules\Vacations\Repositories\AttendanceJustificationRequestRepository;

class AttendanceJustificationService
{
    public function __construct(private AttendanceJustificationRequestRepository $repository, private ScheduleResolverService $scheduleResolver) {}

    /** Get the HR queue. */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->paginate($filters, $perPage);
    }

    /** Resolve the employee's actual rotation window and store an auditable result. */
    public function create(array $data): AttendanceJustificationRequest
    {
        return $this->persist($data);
    }

    /** Find a request for editing. */
    public function find(int $id): ?AttendanceJustificationRequest
    {
        return $this->repository->find($id);
    }

    /** Recalculate schedule-derived values before saving an edit. */
    public function update(AttendanceJustificationRequest $request, array $data): AttendanceJustificationRequest
    {
        return $this->persist($data, $request);
    }

    /** Remove an incorrect justification without erasing the audit record. */
    public function delete(AttendanceJustificationRequest $request): bool
    {
        return $this->repository->delete($request);
    }

    /** Export rows using the same filters as the visible queue. */
    public function export(array $filters): Collection
    {
        return $this->repository->export($filters);
    }

    /** Resolve the rotation-owned check-in and check-out windows for the form. */
    public function resolveSchedule(int $userId, string $attendanceDate): array
    {
        return $this->scheduleResolver->resolve($userId, $attendanceDate);
    }

    /** Resolve the schedule and create or update a persisted request. */
    private function persist(array $data, ?AttendanceJustificationRequest $request = null): AttendanceJustificationRequest
    {
        $schedule = $this->scheduleResolver->resolve((int) $data['user_id'], $data['attendance_date']);
        $arrival = $data['arrival_time'] ?? null;
        $expected = $schedule['expected_check_in'] ?? null;
        $grace = max(0, (int) ($schedule['grace_minutes'] ?? 0));
        // The configured entry-window end is the authoritative lateness
        // threshold. Scheduled start + grace is retained only for rotations
        // that have no entry window configured.
        $entryWindowEnd = $schedule['in_above_margin'] ?? null;

        if (($data['late_arrival'] ?? false) && $arrival && (! $schedule['is_work_day'] || (! $expected && ! $entryWindowEnd))) {
            throw ValidationException::withMessages(['late_arrival' => 'لا يمكن حساب التأخير إلا ليوم عمل له وقت دخول ووقت وصول.']);
        }

        $deadline = null;
        $lateMinutes = 0;
        if ($entryWindowEnd) {
            $deadlineAt = CarbonImmutable::parse($data['attendance_date'].' '.substr((string) $entryWindowEnd, 0, 8));
            $deadline = $deadlineAt->format('H:i:s');
            if ($arrival) {
                $lateMinutes = max(0, $deadlineAt->diffInMinutes(CarbonImmutable::parse($data['attendance_date'].' '.$arrival), false));
            }
        } elseif ($expected) {
            $expectedAt = CarbonImmutable::parse($data['attendance_date'].' '.$expected);
            $deadlineAt = $expectedAt->addMinutes($grace);
            $deadline = $deadlineAt->format('H:i:s');
            if ($arrival) {
                $lateMinutes = max(0, $deadlineAt->diffInMinutes(CarbonImmutable::parse($data['attendance_date'].' '.$arrival), false));
            }
        }

        $payload = [
            'user_id' => $data['user_id'], 'attendance_date' => $data['attendance_date'], 'arrival_time' => $arrival,
            'missing_check_in' => (bool) ($data['missing_check_in'] ?? false), 'missing_check_out' => (bool) ($data['missing_check_out'] ?? false),
            'late_arrival' => (bool) ($data['late_arrival'] ?? false), 'late_minutes' => $lateMinutes, 'expected_check_in' => $expected,
            'check_in_deadline' => $deadline, 'grace_minutes' => $grace, 'rotation_id' => $schedule['rotation_id'], 'rotation_group_id' => $schedule['rotation_group_id'],
            'reason' => $data['reason'] ?? null, 'schedule_snapshot' => $schedule, 'requested_at' => now(),
        ];

        return $request ? $this->repository->update($request, $payload) : $this->repository->create($payload)->fresh('user:id,name,employee_code');
    }
}
