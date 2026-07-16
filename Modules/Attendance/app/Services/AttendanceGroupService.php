<?php

namespace Modules\Attendance\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Modules\Attendance\Models\AttendanceEmployee;
use Modules\Attendance\Models\AttendanceGroup;
use Modules\Attendance\Repositories\AttendanceEmployeeRepository;
use Modules\Attendance\Repositories\AttendanceGroupRepository;

/**
 * AttendanceGroupService — manages attendance groups and employee assignments.
 */
class AttendanceGroupService
{
    public function __construct(
        private AttendanceGroupRepository $groupRepository,
        private AttendanceEmployeeRepository $employeeRepository,
    ) {}

    public function createGroup(array $data): AttendanceGroup
    {
        $existing = AttendanceGroup::where('company_id', $data['company_id'])
            ->where('code', $data['code'])
            ->first();

        if ($existing) {
            throw new InvalidArgumentException('يوجد فئة بنفس الكود في هذه الشركة.');
        }

        return $this->groupRepository->create($data);
    }

    public function updateGroup(AttendanceGroup $group, array $data): AttendanceGroup
    {
        if (isset($data['code']) && $data['code'] !== $group->code) {
            $existing = AttendanceGroup::where('company_id', $group->company_id)
                ->where('code', $data['code'])
                ->where('id', '!=', $group->id)
                ->first();

            if ($existing) {
                throw new InvalidArgumentException('يوجد فئة بنفس الكود في هذه الشركة.');
            }
        }

        return $this->groupRepository->update($group, $data);
    }

    public function deleteGroup(AttendanceGroup $group): bool
    {
        $employeeCount = $this->groupRepository->countEmployeesInGroup($group->id);

        if ($employeeCount > 0) {
            throw new InvalidArgumentException('لا يمكن حذف الفئة لوجود موظفين مرتبطين بها.');
        }

        return $this->groupRepository->delete($group);
    }

    public function getGroupsByCompany(int $companyId): Collection
    {
        return $this->groupRepository->getByCompany($companyId);
    }

    public function getGroupWithEmployees(int $groupId): AttendanceGroup
    {
        $group = $this->groupRepository->findById($groupId);

        if (! $group) {
            throw new InvalidArgumentException('الفئة غير موجودة.');
        }

        return $group;
    }

    public function getAllGroups(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->groupRepository->getAll($filters, $perPage);
    }

    public function assignEmployeeToGroup(int $employeeId, int $groupId, array $flags): AttendanceEmployee
    {
        $group = $this->groupRepository->findById($groupId);

        if (! $group) {
            throw new InvalidArgumentException('الفئة غير موجودة.');
        }

        $existing = $this->employeeRepository->findByEmployee($employeeId);

        if ($existing) {
            throw new InvalidArgumentException('الموظف معيّن بالفعل في فئة.');
        }

        return $this->employeeRepository->create(array_merge([
            'emp_id' => $employeeId,
            'group_id' => $groupId,
        ], $flags));
    }

    public function removeEmployeeFromGroup(int $employeeId): bool
    {
        $record = $this->employeeRepository->findByEmployee($employeeId);

        if (! $record) {
            throw new InvalidArgumentException('الموظف غير معيّن في أي فئة.');
        }

        return $this->employeeRepository->delete($record);
    }

    public function getEmployeesInGroup(int $groupId): Collection
    {
        return $this->employeeRepository->getByGroup($groupId);
    }
}
