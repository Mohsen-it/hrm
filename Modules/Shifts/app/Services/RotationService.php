<?php

namespace Modules\Shifts\Services;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Shifts\Models\Rotation;
use Modules\Shifts\Models\RotationAssignment;
use Modules\Shifts\Models\RotationGroup;
use Modules\Shifts\Repositories\RotationAssignmentRepository;
use Modules\Shifts\Repositories\RotationGroupRepository;
use Modules\Shifts\Repositories\RotationRepository;
use Modules\Shifts\Services\Traits\ResolvesCompanyId;
use Modules\Users\Models\User;

class RotationService
{
    use ResolvesCompanyId;

    public function __construct(
        private RotationRepository $rotationRepository,
        private RotationGroupRepository $groupRepository,
        private RotationAssignmentRepository $assignmentRepository,
        private RotationEngine $rotationEngine,
    ) {}

    /**
     * The maximum number of non-overlapping groups a pattern can tile.
     */
    private function maxGroupsForPattern(int $cycleLength, int $workDaysCount): int
    {
        if ($workDaysCount < 1) {
            return 1;
        }

        return max(1, intdiv($cycleLength, $workDaysCount));
    }

    /**
     * Get all rotations with filters and pagination.
     */
    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->rotationRepository->getAll($filters, $perPage);
    }

    /**
     * Get a simple list of all rotations, optionally scoped to a company.
     */
    public function getAllList(?int $companyId = null)
    {
        return $this->rotationRepository->getAllList($companyId);
    }

    /**
     * Find a rotation by ID.
     */
    public function getById(int $id): ?Rotation
    {
        return $this->rotationRepository->findById($id);
    }

    /**
     * Create a new rotation with groups.
     */
    public function create(array $data): Rotation
    {
        return DB::transaction(function () use ($data) {
            if (empty($data['company_id'])) {
                $data['company_id'] = $this->resolveCompanyId();
            }

            $pattern = $data['pattern'] ?? [];
            $data['cycle_length'] = count($pattern);
            $data['work_days_count'] = array_sum($pattern);
            $data['rest_days_count'] = $data['cycle_length'] - $data['work_days_count'];

            if ($data['work_days_count'] < 1) {
                throw ValidationException::withMessages([
                    'pattern' => [__('shifts.rotation_pattern_requires_work_day')],
                ]);
            }

            $numberOfGroups = $data['number_of_groups'] ?? 1;
            $maxGroups = $this->maxGroupsForPattern($data['cycle_length'], $data['work_days_count']);

            if ($numberOfGroups > $maxGroups) {
                throw ValidationException::withMessages([
                    'number_of_groups' => [__('shifts.rotation_too_many_groups', ['max' => $maxGroups])],
                ]);
            }

            $rotation = $this->rotationRepository->create($data);

            $this->createGroups($rotation, $numberOfGroups, $data['groups'] ?? []);

            return $rotation->fresh(['groups']);
        });
    }

    /**
     * Update an existing rotation.
     */
    public function update(int $id, array $data): Rotation
    {
        return DB::transaction(function () use ($id, $data) {
            $rotation = $this->rotationRepository->findById($id);

            if (! $rotation) {
                throw ValidationException::withMessages([
                    'id' => [__('shifts.rotation_not_found')],
                ]);
            }

            if (isset($data['pattern'])) {
                $pattern = $data['pattern'];
                $data['cycle_length'] = count($pattern);
                $data['work_days_count'] = array_sum($pattern);
                $data['rest_days_count'] = $data['cycle_length'] - $data['work_days_count'];

                if ($data['work_days_count'] < 1) {
                    throw ValidationException::withMessages([
                        'pattern' => [__('shifts.rotation_pattern_requires_work_day')],
                    ]);
                }

                // Rebalance group indices sequentially so the engine's
                // `group_index * work_days_count` offset stays correct after a
                // pattern change (previously indices kept stale values).
                // Only runs when the pattern actually changes so a plain name/
                // settings edit never alters existing schedules. Wrapped in a
                // transaction so the rotation update and the rebalance are atomic.
                $patternChanged = $pattern !== ($rotation->pattern ?? []);

                if ($patternChanged) {
                    $rotation->groups()->orderBy('group_index')->get()
                        ->values()
                        ->each(function (RotationGroup $group, int $i): void {
                            $group->update(['group_index' => $i]);
                        });
                }
            }

            $rotation = $this->rotationRepository->update($rotation, $data);

            return $rotation;
        });
    }

    /**
     * Delete a rotation.
     */
    public function delete(int $id): bool
    {
        $rotation = $this->rotationRepository->findById($id);

        if (! $rotation) {
            throw ValidationException::withMessages([
                'id' => [__('shifts.rotation_not_found')],
            ]);
        }

        if ($this->rotationRepository->hasActiveAssignments($id)) {
            throw ValidationException::withMessages([
                'id' => [__('shifts.rotation_has_active_assignments')],
            ]);
        }

        return $this->rotationRepository->delete($rotation);
    }

    /**
     * Add a group to a rotation.
     */
    public function addGroup(int $rotationId, array $data): RotationGroup
    {
        $rotation = $this->rotationRepository->findById($rotationId);

        if (! $rotation) {
            throw ValidationException::withMessages([
                'rotation_id' => [__('shifts.rotation_not_found')],
            ]);
        }

        $maxGroups = $this->maxGroupsForPattern($rotation->cycle_length, $rotation->work_days_count);

        if ($rotation->groups()->count() + 1 > $maxGroups) {
            throw ValidationException::withMessages([
                'name' => [__('shifts.rotation_too_many_groups', ['max' => $maxGroups])],
            ]);
        }

        $maxIndex = $this->groupRepository->getMaxGroupIndex($rotationId);

        $data['rotation_id'] = $rotationId;
        $data['group_index'] = $data['group_index'] ?? ($maxIndex + 1);

        $group = $this->groupRepository->create($data);

        $rotation->update(['number_of_groups' => $rotation->groups()->count()]);

        return $group;
    }

    /**
     * Update a rotation group.
     */
    public function updateGroup(int $groupId, array $data): RotationGroup
    {
        $group = $this->groupRepository->findById($groupId);

        if (! $group) {
            throw ValidationException::withMessages([
                'group_id' => [__('shifts.rotation_group_not_found')],
            ]);
        }

        return $this->groupRepository->update($group, $data);
    }

    /**
     * Delete a rotation group.
     */
    public function deleteGroup(int $groupId): bool
    {
        $group = $this->groupRepository->findById($groupId);

        if (! $group) {
            throw ValidationException::withMessages([
                'group_id' => [__('shifts.rotation_group_not_found')],
            ]);
        }

        if ($this->groupRepository->hasActiveAssignments($groupId)) {
            throw ValidationException::withMessages([
                'group_id' => [__('shifts.rotation_group_has_active_assignments')],
            ]);
        }

        $rotation = $group->rotation;
        $result = $this->groupRepository->delete($group);

        $rotation->update(['number_of_groups' => $rotation->groups()->count()]);

        return $result;
    }

    /**
     * Assign an employee to a rotation group.
     */
    public function assignEmployee(int $employeeId, int $rotationId, int $groupId, string $startDate, ?string $endDate = null): RotationAssignment
    {
        return DB::transaction(function () use ($employeeId, $rotationId, $groupId, $startDate, $endDate) {
            User::query()->whereKey($employeeId)->lockForUpdate()->first();

            $this->validateAssignment($employeeId, $rotationId, $startDate, $endDate);

            return $this->createAssignment($employeeId, $rotationId, $groupId, $startDate, $endDate);
        });
    }

    /**
     * Assign multiple employees only when none has a conflicting assignment.
     *
     * @param  array<int, int|string>  $employeeIds
     * @return array<int, RotationAssignment>
     */
    public function assignEmployees(
        array $employeeIds,
        int $rotationId,
        int $groupId,
        string $startDate,
        ?string $endDate = null,
    ): array {
        $employeeIds = array_values(array_unique(array_map('intval', $employeeIds)));
        sort($employeeIds);

        return DB::transaction(function () use ($employeeIds, $rotationId, $groupId, $startDate, $endDate): array {
            foreach ($employeeIds as $employeeId) {
                User::query()->whereKey($employeeId)->lockForUpdate()->first();
            }

            foreach ($employeeIds as $employeeId) {
                $this->validateAssignment($employeeId, $rotationId, $startDate, $endDate);
            }

            return array_map(
                fn (int $employeeId) => $this->createAssignment($employeeId, $rotationId, $groupId, $startDate, $endDate),
                $employeeIds,
            );
        });
    }

    /**
     * Transfer an employee to a different rotation group.
     */
    public function transferEmployee(int $employeeId, int $newRotationId, int $newGroupId, string $effectiveDate): RotationAssignment
    {
        return DB::transaction(function () use ($employeeId, $newRotationId, $newGroupId, $effectiveDate) {
            $previousDay = Carbon::parse($effectiveDate)->subDay()->toDateString();

            // Serialize concurrent transfers for the same employee.
            User::query()->whereKey($employeeId)->lockForUpdate()->first();

            $this->closeCurrentAssignment($employeeId, $previousDay);

            return $this->createAssignment($employeeId, $newRotationId, $newGroupId, $effectiveDate);
        });
    }

    /**
     * Unassign an employee from their current rotation.
     */
    public function unassignEmployee(int $employeeId, string $endDate): ?RotationAssignment
    {
        $active = $this->assignmentRepository->getActiveAssignment($employeeId);

        if (! $active) {
            return null;
        }

        return $this->assignmentRepository->closeAssignment($active, $endDate);
    }

    /**
     * Get all assignments with filters and pagination.
     */
    public function getAllAssignments(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->assignmentRepository->getAll($filters, $perPage);
    }

    /**
     * Get the schedule preview for a rotation.
     *
     * @return array<int, array{date: string, groups: array<int, bool>}>
     */
    public function getSchedulePreview(int $rotationId, string $fromDate, string $toDate): array
    {
        $rotation = $this->rotationRepository->findById($rotationId);

        if (! $rotation) {
            return [];
        }

        $groups = $rotation->groups()->orderBy('group_index')->get();
        $preview = [];

        $current = Carbon::parse($fromDate)->startOfDay();
        $end = Carbon::parse($toDate)->startOfDay();

        while ($current->lte($end)) {
            $dayData = [
                'date' => $current->format('Y-m-d'),
                'groups' => [],
            ];

            foreach ($groups as $group) {
                $dayData['groups'][$group->id] = [
                    'name' => $group->name,
                    'is_work_day' => $this->rotationEngine->isWorkDay($rotation, $group, $current),
                ];
            }

            $preview[] = $dayData;
            $current->addDay();
        }

        return $preview;
    }

    /**
     * Get the active rotation assignment for an employee.
     */
    public function getActiveAssignment(int $employeeId): ?RotationAssignment
    {
        return $this->assignmentRepository->getActiveAssignment($employeeId);
    }

    private function createGroups(Rotation $rotation, int $numberOfGroups, array $customGroups): void
    {
        // `group_index` is a sequential 0-based group number. RotationEngine
        // turns it into a real day offset via `group_index * work_days_count`,
        // which tiles the cycle without overlap when groups × work ≤ cycle.
        for ($i = 0; $i < $numberOfGroups; $i++) {
            $customData = $customGroups[$i] ?? [];

            $this->groupRepository->create([
                'rotation_id' => $rotation->id,
                'name' => $customData['name'] ?? chr(65 + $i),
                'group_index' => $i,
            ]);
        }
    }

    /**
     * Create a new rotation assignment (without close/validate — caller handles that).
     */
    private function createAssignment(int $employeeId, int $rotationId, int $groupId, string $startDate, ?string $endDate = null): RotationAssignment
    {
        $rotation = Rotation::find($rotationId);
        $group = RotationGroup::find($groupId);

        if (! $rotation) {
            throw ValidationException::withMessages([
                'rotation_id' => [__('shifts.rotation_not_found')],
            ]);
        }

        if (! $group) {
            throw ValidationException::withMessages([
                'rotation_group_id' => [__('shifts.rotation_group_not_found')],
            ]);
        }

        if ((int) $group->rotation_id !== (int) $rotation->id) {
            throw ValidationException::withMessages([
                'rotation_group_id' => [__('shifts.rotation_group_mismatch')],
            ]);
        }

        $timeSchedule = $rotation->timeSchedule;
        $snapshotData = [
            'rotation' => [
                'id' => $rotation->id,
                'name' => $rotation->name,
                'description' => $rotation->description,
                'anchor_start_date' => $rotation->anchor_start_date?->toDateString(),
                'pattern' => $rotation->pattern,
                'cycle_length' => $rotation->cycle_length,
                'work_days_count' => $rotation->work_days_count,
                'rest_days_count' => $rotation->rest_days_count,
                'number_of_groups' => $rotation->number_of_groups,
                'time_schedule_id' => $rotation->time_schedule_id,
                'overtime_enabled' => $rotation->overtime_enabled,
                'work_on_holidays' => $rotation->work_on_holidays,
                'grace_minutes' => $rotation->grace_minutes,
                'in_ahead_margin' => $rotation->in_ahead_margin,
                'in_above_margin' => $rotation->in_above_margin,
                'out_ahead_margin' => $rotation->out_ahead_margin,
                'out_above_margin' => $rotation->out_above_margin,
                'color' => $rotation->color,
            ],
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'group_index' => $group->group_index,
            ],
            'time_schedule' => $timeSchedule ? [
                'id' => $timeSchedule->id,
                'name' => $timeSchedule->name,
                'in_time' => $timeSchedule->in_time,
                'out_time' => $timeSchedule->out_time,
                'is_multi_day' => $timeSchedule->is_multi_day,
                'late_margin' => $timeSchedule->late_margin,
                'early_margin' => $timeSchedule->early_margin,
                'breaks' => $timeSchedule->breaks->map(fn ($b) => [
                    'break_start' => $b->break_start,
                    'break_end' => $b->break_end,
                    'duration' => $b->duration,
                ])->values()->all(),
            ] : null,
        ];

        return $this->assignmentRepository->create([
            'employee_id' => $employeeId,
            'rotation_id' => $rotationId,
            'rotation_group_id' => $groupId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'snapshot_data' => $snapshotData,
        ]);
    }

    private function closeCurrentAssignment(int $employeeId, string $endDate): void
    {
        $active = $this->assignmentRepository->getActiveAssignment($employeeId);

        if ($active) {
            $this->assignmentRepository->closeAssignment($active, $endDate);
        }
    }

    private function validateAssignment(int $employeeId, int $rotationId, string $startDate, ?string $endDate): void
    {
        $existingAssignment = $this->assignmentRepository->findOverlappingAssignment(
            $employeeId,
            $startDate,
            $endDate,
        );

        if ($existingAssignment) {
            throw ValidationException::withMessages([
                'employee_id' => [__('shifts.employee_rotation_assignment_conflict', [
                    'rotation' => $existingAssignment->rotation?->name ?? '—',
                    'group' => $existingAssignment->rotationGroup?->name ?? '—',
                ])],
            ]);
        }
    }
}
