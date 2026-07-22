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
     * Get all rotations with filters and pagination.
     */
    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->rotationRepository->getAll($filters, $perPage);
    }

    /**
     * Get a simple list of all rotations.
     */
    public function getAllList()
    {
        return $this->rotationRepository->getAllList();
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

            $rotation = $this->rotationRepository->create($data);

            $numberOfGroups = $data['number_of_groups'] ?? 1;
            $this->createGroups($rotation, $numberOfGroups, $data['groups'] ?? []);

            return $rotation->fresh(['groups']);
        });
    }

    /**
     * Update an existing rotation.
     */
    public function update(int $id, array $data): Rotation
    {
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
        }

        $rotation = $this->rotationRepository->update($rotation, $data);

        return $rotation;
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
        $this->closePreviousAssignment($employeeId, $startDate);

        $this->validateAssignment($employeeId, $rotationId, $startDate, $endDate);

        $rotation = $this->rotationRepository->findById($rotationId);
        $group = $this->groupRepository->findById($groupId);

        $snapshotData = [
            'rotation' => $rotation->toArray(),
            'group' => $group->toArray(),
            'time_schedule' => $rotation->timeSchedule?->toArray(),
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

    /**
     * Transfer an employee to a different rotation group.
     */
    public function transferEmployee(int $employeeId, int $newRotationId, int $newGroupId, string $effectiveDate): RotationAssignment
    {
        $previousDay = Carbon::parse($effectiveDate)->subDay()->toDateString();

        $this->closeCurrentAssignment($employeeId, $previousDay);

        return $this->assignEmployee($employeeId, $newRotationId, $newGroupId, $effectiveDate);
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
        $cycleLength = $rotation->cycle_length;
        $offsetStep = intdiv($cycleLength, max($numberOfGroups, 1));

        for ($i = 0; $i < $numberOfGroups; $i++) {
            $customData = $customGroups[$i] ?? [];

            $this->groupRepository->create([
                'rotation_id' => $rotation->id,
                'name' => $customData['name'] ?? chr(65 + $i),
                'group_index' => $i * $offsetStep,
            ]);
        }
    }

    private function closePreviousAssignment(int $employeeId, string $startDate): void
    {
        $active = $this->assignmentRepository->getActiveAssignment($employeeId);

        if ($active) {
            $previousDay = Carbon::parse($startDate)->subDay()->toDateString();
            $this->assignmentRepository->closeAssignment($active, $previousDay);
        }
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
        $existingActive = $this->assignmentRepository->getActiveAssignment($employeeId);

        if ($existingActive) {
            throw ValidationException::withMessages([
                'employee_id' => [__('shifts.employee_already_assigned_to_rotation')],
            ]);
        }
    }
}
