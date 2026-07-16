<?php

namespace Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\Http\Requests\AssignEmployeeToGroupRequest;
use Modules\Attendance\Http\Requests\StoreAttendanceGroupRequest;
use Modules\Attendance\Http\Requests\UpdateAttendanceGroupRequest;
use Modules\Attendance\Services\AttendanceGroupService;
use Modules\Users\Services\UserService;

/**
 * AttendanceGroupsController — manage attendance groups and employee assignments.
 */
class AttendanceGroupsController extends Controller
{
    public function __construct(
        private AttendanceGroupService $groupService,
        private UserService $userService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('view-attendance-groups');

        $filters = $request->only(['search', 'company_id', 'status']);
        $filters = array_filter($filters, fn ($v) => $v !== null && $v !== '');

        return Inertia::render('Shifts/AttendanceGroups/Index', [
            'groups' => fn () => $this->groupService->getAllGroups($filters, 20),
            'filters' => fn () => $filters,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create-attendance-groups');

        return Inertia::render('Shifts/AttendanceGroups/Create', []);
    }

    public function store(StoreAttendanceGroupRequest $request): RedirectResponse
    {
        $this->authorize('create-attendance-groups');

        $this->groupService->createGroup($request->validated());

        return redirect()->route('attendance.groups.index')
            ->with('success', __('attendance.messages.group_created_successfully'));
    }

    public function show(int $id): Response
    {
        $this->authorize('view-attendance-groups');

        $group = $this->groupService->getGroupWithEmployees($id);

        return Inertia::render('Shifts/AttendanceGroups/Show', [
            'group' => fn () => $group,
            'employees' => fn () => $this->groupService->getEmployeesInGroup($id),
        ]);
    }

    public function edit(int $id): Response
    {
        $this->authorize('edit-attendance-groups');

        $group = $this->groupService->getGroupWithEmployees($id);

        return Inertia::render('Shifts/AttendanceGroups/Edit', [
            'group' => fn () => $group,
        ]);
    }

    public function update(UpdateAttendanceGroupRequest $request, int $id): RedirectResponse
    {
        $this->authorize('edit-attendance-groups');

        $group = $this->groupService->getGroupWithEmployees($id);
        $this->groupService->updateGroup($group, $request->validated());

        return redirect()->route('attendance.groups.show', $id)
            ->with('success', __('attendance.messages.group_updated_successfully'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->authorize('delete-attendance-groups');

        $group = $this->groupService->getGroupWithEmployees($id);
        $this->groupService->deleteGroup($group);

        return redirect()->route('attendance.groups.index')
            ->with('success', __('attendance.messages.group_deleted_successfully'));
    }

    public function assignEmployee(AssignEmployeeToGroupRequest $request, int $groupId): RedirectResponse
    {
        $this->authorize('assign-attendance-groups');

        $data = $request->validated();
        $this->groupService->assignEmployeeToGroup(
            (int) $data['emp_id'],
            $groupId,
            array_filter([
                'enable_attendance' => $data['enable_attendance'] ?? true,
                'enable_schedule' => $data['enable_schedule'] ?? true,
                'enable_overtime' => $data['enable_overtime'] ?? false,
                'enable_holiday' => $data['enable_holiday'] ?? true,
                'enable_compensatory' => $data['enable_compensatory'] ?? false,
            ], fn ($v) => $v !== null)
        );

        return redirect()->route('attendance.groups.show', $groupId)
            ->with('success', __('attendance.messages.employee_assigned_to_group'));
    }

    public function removeEmployee(int $groupId, int $employeeId): RedirectResponse
    {
        $this->authorize('assign-attendance-groups');

        $this->groupService->removeEmployeeFromGroup($employeeId);

        return redirect()->route('attendance.groups.show', $groupId)
            ->with('success', __('attendance.messages.employee_removed_from_group'));
    }

    public function employees(int $groupId): Response
    {
        $this->authorize('view-attendance-groups');

        $group = $this->groupService->getGroupWithEmployees($groupId);
        $employees = $this->groupService->getEmployeesInGroup($groupId);

        return Inertia::render('Shifts/AttendanceGroups/Show', [
            'group' => fn () => $group,
            'employees' => fn () => $employees,
        ]);
    }
}
