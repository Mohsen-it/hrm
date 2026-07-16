<?php

namespace Modules\Shifts\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Departments\Models\Department;
use Modules\Shifts\Http\Requests\AssignEmployeeRequest;
use Modules\Shifts\Http\Requests\BulkAssignRequest;
use Modules\Shifts\Http\Requests\TransferEmployeeRequest;
use Modules\Shifts\Http\Resources\EmployeeShiftCategoryResource;
use Modules\Shifts\Http\Resources\ShiftCategoryResource;
use Modules\Shifts\Services\ShiftCategoryAssignmentService;
use Modules\Shifts\Services\ShiftCategoryService;
use Modules\Users\Models\User;

class ShiftCategoryAssignmentController extends Controller
{
    public function __construct(
        private ShiftCategoryAssignmentService $assignmentService,
        private ShiftCategoryService $categoryService
    ) {}

    /**
     * Display a listing of employee shift category assignments.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-shift-categories');

        return Inertia::render('Shifts/Assignments/Index', [
            'filters' => fn () => $request->only(['search', 'category_id', 'department_id', 'status']),
            'assignments' => fn () => EmployeeShiftCategoryResource::collection(
                $this->assignmentService->getAllAssignments(
                    $request->only(['search', 'category_id', 'department_id', 'status'])
                )
            ),
            'categories' => fn () => ShiftCategoryResource::collection(
                $this->categoryService->getAll()
            ),
            'departments' => fn () => Department::orderBy('department_name')->get(['id', 'department_name']),
        ]);
    }

    /**
     * Show the form for assigning an employee to a category.
     */
    public function create(Request $request): Response
    {
        $this->authorize('assign-employees-to-category');

        $preselectedCategoryId = $request->input('category');

        return Inertia::render('Shifts/Assignments/Assign', [
            'categories' => fn () => ShiftCategoryResource::collection(
                $this->categoryService->getAll()
            ),
            'preselected_category_id' => $preselectedCategoryId ? (int) $preselectedCategoryId : null,
        ]);
    }

    /**
     * Show the form for bulk assigning employees to a category.
     */
    public function bulkCreate(Request $request): Response
    {
        $this->authorize('assign-employees-to-category');

        $preselectedCategoryId = $request->input('category');

        return Inertia::render('Shifts/Assignments/BulkAssign', [
            'categories' => fn () => ShiftCategoryResource::collection(
                $this->categoryService->getAll()
            ),
            'departments' => fn () => Department::orderBy('department_name')->get(['id', 'department_name']),
            'preselected_category_id' => $preselectedCategoryId ? (int) $preselectedCategoryId : null,
        ]);
    }

    /**
     * Assign a single employee to a shift category.
     */
    public function assign(AssignEmployeeRequest $request): RedirectResponse
    {
        $this->authorize('assign-employees-to-category');

        $this->assignmentService->assignEmployee(
            $request->employee_id,
            $request->shift_category_id,
            $request->start_date,
            $request->end_date
        );

        return redirect()->route('shift-assignments.index')
            ->with('success', __('shifts.employee_assigned'));
    }

    /**
     * Bulk assign multiple employees to a shift category.
     */
    public function bulkAssign(BulkAssignRequest $request): RedirectResponse
    {
        $this->authorize('assign-employees-to-category');

        $assignments = $this->assignmentService->bulkAssign(
            $request->employee_ids,
            $request->shift_category_id,
            $request->start_date
        );

        $count = count($assignments);

        return redirect()->route('shift-assignments.index')
            ->with('success', __('shifts.employees_assigned_count', ['count' => $count]));
    }

    /**
     * Transfer an employee from their current category to a new one.
     */
    public function transfer(TransferEmployeeRequest $request): RedirectResponse
    {
        $this->authorize('assign-employees-to-category');

        $this->assignmentService->transferEmployee(
            $request->employee_id,
            $request->new_category_id,
            $request->effective_date
        );

        return redirect()->route('shift-assignments.index')
            ->with('success', __('shifts.employee_transferred'));
    }

    /**
     * Search employees for assignment (AJAX endpoint).
     */
    public function searchEmployees(Request $request): JsonResponse
    {
        $this->authorize('assign-employees-to-category');

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
     * Unassign an employee from their current shift category.
     */
    public function unassign(Request $request): RedirectResponse
    {
        $this->authorize('assign-employees-to-category');

        $active = $this->assignmentService->getActiveAssignment($request->employee_id);

        if (! $active) {
            abort(404, __('shifts.no_active_assignment'));
        }

        $this->assignmentService->unassignEmployee($request->employee_id, now()->toDateString());

        return redirect()->route('shift-assignments.index')
            ->with('success', __('shifts.employee_unassigned'));
    }
}
