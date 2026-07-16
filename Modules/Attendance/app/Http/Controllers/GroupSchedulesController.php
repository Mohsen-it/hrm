<?php

namespace Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\Http\Requests\StoreGroupScheduleRequest;
use Modules\Attendance\Http\Requests\UpdateGroupScheduleRequest;
use Modules\Attendance\Services\AttendanceGroupService;
use Modules\Attendance\Services\AttendanceShiftService;
use Modules\Attendance\Services\GroupScheduleService;

/**
 * GroupSchedulesController — manage group schedules (shift assignments for groups).
 */
class GroupSchedulesController extends Controller
{
    public function __construct(
        private GroupScheduleService $scheduleService,
        private AttendanceGroupService $groupService,
        private AttendanceShiftService $shiftService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('view-group-schedules');

        $filters = $request->only(['group_id', 'shift_id', 'date']);
        $filters = array_filter($filters, fn ($v) => $v !== null && $v !== '');

        return Inertia::render('Shifts/GroupSchedules/Index', [
            'schedules' => fn () => $this->scheduleService->getAllSchedules($filters, 20),
            'filters' => fn () => $filters,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create-group-schedules');

        return Inertia::render('Shifts/GroupSchedules/Create', [
            'groups' => fn () => $this->groupService->getAllGroups([], 100),
            'shifts' => fn () => $this->shiftService->getAllShifts([], 100),
        ]);
    }

    public function store(StoreGroupScheduleRequest $request): RedirectResponse
    {
        $this->authorize('create-group-schedules');

        $this->scheduleService->createGroupSchedule($request->validated());

        return redirect()->route('attendance.group-schedules.index')
            ->with('success', __('attendance.messages.group_schedule_created_successfully'));
    }

    public function show(int $id): Response
    {
        $this->authorize('view-group-schedules');

        $schedule = $this->scheduleService->getScheduleWithDetails($id);

        return Inertia::render('Shifts/GroupSchedules/Show', [
            'schedule' => fn () => $schedule,
        ]);
    }

    public function edit(int $id): Response
    {
        $this->authorize('edit-group-schedules');

        $schedule = $this->scheduleService->getScheduleWithDetails($id);

        return Inertia::render('Shifts/GroupSchedules/Edit', [
            'schedule' => fn () => $schedule,
            'groups' => fn () => $this->groupService->getAllGroups([], 100),
            'shifts' => fn () => $this->shiftService->getAllShifts([], 100),
        ]);
    }

    public function update(UpdateGroupScheduleRequest $request, int $id): RedirectResponse
    {
        $this->authorize('edit-group-schedules');

        $schedule = $this->scheduleService->getScheduleWithDetails($id);
        $this->scheduleService->updateGroupSchedule($schedule, $request->validated());

        return redirect()->route('attendance.group-schedules.show', $id)
            ->with('success', __('attendance.messages.group_schedule_updated_successfully'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->authorize('delete-group-schedules');

        $schedule = $this->scheduleService->getScheduleWithDetails($id);
        $this->scheduleService->deleteGroupSchedule($schedule);

        return redirect()->route('attendance.group-schedules.index')
            ->with('success', __('attendance.messages.group_schedule_deleted_successfully'));
    }
}
