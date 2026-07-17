<?php

namespace Modules\Shifts\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Departments\Models\Department;
use Modules\Shifts\Http\Requests\AssignRotationRequest;
use Modules\Shifts\Http\Requests\BulkAssignRotationRequest;
use Modules\Shifts\Http\Requests\StoreRotationRequest;
use Modules\Shifts\Http\Requests\UpdateRotationRequest;
use Modules\Shifts\Http\Resources\RotationGroupResource;
use Modules\Shifts\Http\Resources\RotationResource;
use Modules\Shifts\Models\RotationAssignment;
use Modules\Shifts\Models\RotationGroup;
use Modules\Shifts\Services\RotationService;
use Modules\Shifts\Services\TimeScheduleService;
use Modules\Users\Models\User;

class RotationsController extends Controller
{
    public function __construct(
        private RotationService $rotationService,
        private TimeScheduleService $timeScheduleService,
    ) {}

    /**
     * Display a listing of rotations.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-rotations');

        return Inertia::render('Shifts/Rotations/Index', [
            'filters' => fn () => $request->only(['search', 'company_id']),
            'rotations' => fn () => RotationResource::collection(
                $this->rotationService->getAll(
                    $request->only(['search', 'company_id'])
                )
            ),
        ]);
    }

    /**
     * Show the form for creating a new rotation.
     */
    public function create(): Response
    {
        $this->authorize('create-rotations');

        return Inertia::render('Shifts/Rotations/Create', [
            'timeSchedules' => fn () => $this->timeScheduleService->getList(),
        ]);
    }

    /**
     * Store a newly created rotation.
     */
    public function store(StoreRotationRequest $request): RedirectResponse
    {
        $this->authorize('create-rotations');

        $this->rotationService->create($request->validated());

        return redirect()->route('rotations.index')
            ->with('success', __('shifts.rotation_created'));
    }

    /**
     * Display the specified rotation with groups and preview.
     */
    public function show(int|string $id): Response
    {
        $this->authorize('view-rotations');
        $id = (int) $id;

        $rotation = $this->rotationService->getById($id);

        if (! $rotation) {
            abort(404);
        }

        $from = now()->startOfMonth()->toDateString();
        $to = now()->addMonths(3)->endOfMonth()->toDateString();
        $preview = $this->rotationService->getSchedulePreview($id, $from, $to);

        return Inertia::render('Shifts/Rotations/Show', [
            'rotation' => fn () => new RotationResource($rotation),
            'preview' => fn () => $preview,
            'preview_from' => $from,
            'preview_to' => $to,
        ]);
    }

    /**
     * Show the form for editing the specified rotation.
     */
    public function edit(int|string $id): Response
    {
        $this->authorize('edit-rotations');
        $id = (int) $id;

        $rotation = $this->rotationService->getById($id);

        if (! $rotation) {
            abort(404);
        }

        return Inertia::render('Shifts/Rotations/Edit', [
            'rotation' => fn () => new RotationResource($rotation),
        ]);
    }

    /**
     * Update the specified rotation.
     */
    public function update(UpdateRotationRequest $request, int|string $id): RedirectResponse
    {
        $this->authorize('edit-rotations');

        $this->rotationService->update((int) $id, $request->validated());

        return redirect()->route('rotations.index')
            ->with('success', __('shifts.rotation_updated'));
    }

    /**
     * Remove the specified rotation.
     */
    public function destroy(int|string $id): RedirectResponse
    {
        $this->authorize('delete-rotations');

        $this->rotationService->delete((int) $id);

        return redirect()->route('rotations.index')
            ->with('success', __('shifts.rotation_deleted'));
    }

    /**
     * Add a group to a rotation.
     */
    public function addGroup(Request $request, int|string $rotationId): RedirectResponse
    {
        $this->authorize('edit-rotations');

        $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'time_schedule_id' => ['nullable', 'integer', 'exists:att_time_schedules,id'],
        ]);

        $this->rotationService->addGroup($rotationId, $request->only(['name', 'time_schedule_id']));

        return redirect()->route('rotations.show', $rotationId)
            ->with('success', __('shifts.rotation_group_added'));
    }

    /**
     * Update a rotation group.
     */
    public function updateGroup(Request $request, int|string $groupId): RedirectResponse
    {
        $this->authorize('edit-rotations');

        $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:50'],
            'time_schedule_id' => ['nullable', 'integer', 'exists:att_time_schedules,id'],
        ]);

        $this->rotationService->updateGroup($groupId, $request->only(['name', 'time_schedule_id']));

        $group = RotationGroup::find($groupId);

        return redirect()->route('rotations.show', $group->rotation_id)
            ->with('success', __('shifts.rotation_group_updated'));
    }

    /**
     * Delete a rotation group.
     */
    public function deleteGroup(int|string $groupId): RedirectResponse
    {
        $this->authorize('edit-rotations');

        $group = RotationGroup::find($groupId);
        $rotationId = $group?->rotation_id;

        $this->rotationService->deleteGroup($groupId);

        return redirect()->route('rotations.show', $rotationId)
            ->with('success', __('shifts.rotation_group_deleted'));
    }

    /**
     * Show the assignment page.
     */
    public function assignPage(Request $request): Response
    {
        $this->authorize('assign-employees-to-rotation');

        return Inertia::render('Shifts/Rotations/Assign', [
            'rotations' => fn () => RotationResource::collection($this->rotationService->getAllList()),
            'preselected_rotation_id' => $request->input('rotation') ? (int) $request->input('rotation') : null,
            'preselected_group_id' => $request->input('group') ? (int) $request->input('group') : null,
        ]);
    }

    /**
     * Show the bulk assignment page.
     */
    public function bulkAssignPage(Request $request): Response
    {
        $this->authorize('assign-employees-to-rotation');

        return Inertia::render('Shifts/Rotations/BulkAssign', [
            'rotations' => fn () => RotationResource::collection($this->rotationService->getAllList()),
            'departments' => fn () => Department::orderBy('department_name')->get(['id', 'department_name']),
            'preselected_rotation_id' => $request->input('rotation') ? (int) $request->input('rotation') : null,
            'preselected_group_id' => $request->input('group') ? (int) $request->input('group') : null,
        ]);
    }

    /**
     * Assign an employee to a rotation group.
     */
    public function assign(AssignRotationRequest $request): RedirectResponse
    {
        $this->authorize('assign-employees-to-rotation');

        $this->rotationService->assignEmployee(
            $request->employee_id,
            $request->rotation_id,
            $request->rotation_group_id,
            $request->start_date,
            $request->end_date
        );

        return redirect()->route('rotations.assign')
            ->with('success', __('shifts.rotation_employee_assigned'));
    }

    /**
     * Bulk assign employees to a rotation group.
     */
    public function bulkAssign(BulkAssignRotationRequest $request): RedirectResponse
    {
        $this->authorize('assign-employees-to-rotation');

        foreach ($request->employee_ids as $employeeId) {
            $this->rotationService->assignEmployee(
                $employeeId,
                $request->rotation_id,
                $request->rotation_group_id,
                $request->start_date
            );
        }

        $count = count($request->employee_ids);

        return redirect()->route('rotations.assign')
            ->with('success', __('shifts.rotation_employees_assigned_count', ['count' => $count]));
    }

    /**
     * Transfer an employee to a different rotation/group.
     */
    public function transfer(Request $request): RedirectResponse
    {
        $this->authorize('assign-employees-to-rotation');

        $request->validate([
            'employee_id' => ['required', 'integer', 'exists:users,id'],
            'new_rotation_id' => ['required', 'integer', 'exists:att_rotations,id'],
            'new_group_id' => ['required', 'integer', 'exists:att_rotation_groups,id'],
            'effective_date' => ['required', 'date'],
        ]);

        $this->rotationService->transferEmployee(
            $request->employee_id,
            $request->new_rotation_id,
            $request->new_group_id,
            $request->effective_date
        );

        return redirect()->route('rotations.assign')
            ->with('success', __('shifts.rotation_employee_transferred'));
    }

    /**
     * Unassign an employee from their rotation.
     */
    public function unassign(Request $request): RedirectResponse
    {
        $this->authorize('assign-employees-to-rotation');

        $this->rotationService->unassignEmployee($request->employee_id, now()->toDateString());

        return redirect()->route('rotations.assign')
            ->with('success', __('shifts.rotation_employee_unassigned'));
    }

    /**
     * Get schedule preview data (AJAX endpoint).
     */
    public function preview(int|string $id, Request $request)
    {
        $this->authorize('view-rotations');

        $from = $request->get('from', now()->toDateString());
        $to = $request->get('to', now()->addMonths(3)->toDateString());

        $preview = $this->rotationService->getSchedulePreview($id, $from, $to);

        return response()->json([
            'preview' => $preview,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * Search employees for rotation assignment (AJAX endpoint).
     */
    public function searchEmployees(Request $request)
    {
        $this->authorize('assign-employees-to-rotation');

        $search = $request->input('search', '');
        $departmentId = $request->input('department_id');

        $query = User::query()
            ->active()
            ->withoutSuperAdmin()
            ->select('id', 'employee_code', 'name', 'first_name', 'last_name', 'department_id');

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%");
            });
        }

        $employees = $query->orderBy('name')->limit(20)->get();

        return response()->json([
            'employees' => $employees->map(function ($emp): array {
                return [
                    'id' => $emp->id,
                    'employee_code' => $emp->employee_code,
                    'name' => $emp->name,
                    'first_name' => $emp->first_name,
                    'last_name' => $emp->last_name,
                    'full_name' => trim(($emp->first_name ?? '').' '.($emp->last_name ?? '')),
                ];
            }),
        ]);
    }

    /**
     * Get rotation groups (AJAX endpoint).
     */
    public function getGroups(int|string $id)
    {
        $rotation = $this->rotationService->getById($id);

        if (! $rotation) {
            return response()->json(['groups' => []], 404);
        }

        return response()->json([
            'groups' => RotationGroupResource::collection($rotation->groups),
        ]);
    }

    /**
     * Show the manage assignments page.
     */
    public function manageAssignments(Request $request): Response
    {
        $this->authorize('assign-employees-to-rotation');

        return Inertia::render('Shifts/Rotations/ManageAssignments', [
            'rotations' => fn () => RotationResource::collection($this->rotationService->getAllList()),
            'departments' => fn () => Department::orderBy('department_name')->get(['id', 'department_name']),
            'preselected_rotation_id' => $request->input('rotation') ? (int) $request->input('rotation') : null,
        ]);
    }

    /**
     * Get all employees assigned to a rotation with their group info (AJAX endpoint).
     */
    public function getRotationEmployees(int|string $id, Request $request)
    {
        $this->authorize('view-rotations');

        $rotation = $this->rotationService->getById($id);

        if (! $rotation) {
            return response()->json(['employees' => []], 404);
        }

        $departmentId = $request->input('department_id');
        $search = $request->input('search', '');

        $query = User::query()
            ->active()
            ->withoutSuperAdmin()
            ->select('id', 'employee_code', 'name', 'first_name', 'last_name', 'department_id');

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%");
            });
        }

        $employees = $query->orderBy('name')->get();

        $employeeIds = $employees->pluck('id')->toArray();

        $assignments = RotationAssignment::query()
            ->with(['rotationGroup'])
            ->where('rotation_id', $id)
            ->whereIn('employee_id', $employeeIds)
            ->whereNull('end_date')
            ->get()
            ->keyBy('employee_id');

        $result = $employees->map(function ($emp) use ($assignments) {
            $assignment = $assignments->get($emp->id);

            return [
                'id' => $emp->id,
                'employee_code' => $emp->employee_code,
                'name' => $emp->name,
                'first_name' => $emp->first_name,
                'last_name' => $emp->last_name,
                'full_name' => trim(($emp->first_name ?? '').' '.($emp->last_name ?? '')),
                'department_id' => $emp->department_id,
                'rotation_group_id' => $assignment?->rotation_group_id,
                'rotation_group_name' => $assignment?->rotationGroup?->name,
                'start_date' => $assignment?->start_date,
            ];
        });

        return response()->json([
            'employees' => $result,
        ]);
    }

    /**
     * Bulk transfer employees between groups (AJAX endpoint).
     */
    public function bulkTransfer(Request $request)
    {
        $this->authorize('assign-employees-to-rotation');

        $request->validate([
            'assignments' => ['required', 'array'],
            'assignments.*.employee_id' => ['required', 'integer', 'exists:users,id'],
            'assignments.*.rotation_group_id' => ['required', 'integer', 'exists:att_rotation_groups,id'],
            'effective_date' => ['required', 'date'],
        ]);

        $count = 0;

        foreach ($request->assignments as $assignment) {
            $group = RotationGroup::find($assignment['rotation_group_id']);
            if ($group) {
                $this->rotationService->transferEmployee(
                    $assignment['employee_id'],
                    $group->rotation_id,
                    $assignment['rotation_group_id'],
                    $request->effective_date
                );
                $count++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => __('shifts.rotation_employees_transferred_count', ['count' => $count]),
            'count' => $count,
        ]);
    }
}
