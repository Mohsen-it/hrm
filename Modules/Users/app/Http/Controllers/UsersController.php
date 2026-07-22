<?php

namespace Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\ExcelExportable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\Services\AttendanceGroupService;
use Modules\Branches\Services\BranchService;
use Modules\Companies\Services\CompanyService;
use Modules\Departments\Services\DepartmentService;
use Modules\Grades\Services\GradeService;
use Modules\Positions\Services\PositionService;
use Modules\Shifts\Services\RotationService;
use Modules\Shifts\Services\ShiftService;
use Modules\Subordinations\Services\SubordinationService;
use Modules\Users\Exports\UsersExport;
use Modules\Users\Http\Requests\StoreUserRequest;
use Modules\Users\Http\Requests\UpdateUserRequest;
use Modules\Users\Http\Resources\UserIndexResource;
use Modules\Users\Http\Resources\UserResource;
use Modules\Users\Models\User;
use Modules\Users\Services\UserService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UsersController extends Controller
{
    use ExcelExportable;

    public function __construct(
        private UserService $userService,
        private CompanyService $companyService,
        private BranchService $branchService,
        private DepartmentService $departmentService,
        private PositionService $positionService,
        private GradeService $gradeService,
        private SubordinationService $subordinationService,
        private ShiftService $shiftService,
        private AttendanceGroupService $attendanceGroupService,
        private RotationService $rotationService,
    ) {}

    // ------------------------------------------------------------------
    // CRUD
    // ------------------------------------------------------------------

    /**
     * Display a listing of users.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-users');

        return Inertia::render('Users/Index', [
            'filters' => fn () => $request->only([
                'search', 'company_id', 'branch_id', 'department_id',
                'position_id', 'grade_id', 'subordination_id', 'shift_id', 'status',
                'employment_type', 'role', 'is_active_employee', 'per_page',
            ]),
            'users' => fn () => UserIndexResource::collection(
                $this->userService->getAllUsers(
                    $request->only([
                        'search', 'company_id', 'branch_id', 'department_id',
                        'position_id', 'grade_id', 'subordination_id', 'shift_id', 'status',
                        'employment_type', 'role', 'is_active_employee',
                    ]),
                    (int) $request->input('per_page', 20)
                )
            ),
            'companies' => fn () => $this->companyService->getActiveCompanies()
                ->map(fn ($c) => ['id' => $c->id, 'company_name' => $c->company_name]),
            'branches' => fn () => $this->branchService->getActiveBranches()
                ->map(fn ($b) => ['id' => $b->id, 'branch_name' => $b->branch_name, 'company_id' => $b->company_id]),
            'departments' => fn () => $this->departmentService->getAllDepartments([])
                ->getCollection()
                ->map(fn ($d) => ['id' => $d->id, 'department_name' => $d->department_name, 'branch_id' => $d->branch_id]),
            'positions' => fn () => $this->positionService->getAllPositions([])
                ->getCollection()
                ->map(fn ($p) => ['id' => $p->id, 'position_name' => $p->position_name]),
            'grades' => fn () => $this->gradeService->getActiveGrades()
                ->map(fn ($g) => ['id' => $g->id, 'grade_name' => $g->grade_name]),
            'subordinations' => fn () => $this->subordinationService->getActiveSubordinations()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'code' => $s->code,
                    'display_name' => $s->display_name,
                ]),
            'shifts' => fn () => $this->shiftService->getActiveShifts()
                ->map(fn ($s) => ['id' => $s->id, 'shift_name' => $s->shift_name]),
            'roles' => fn () => Role::orderBy('name')->get()
                ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name]),
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): Response
    {
        $this->authorize('create-users');

        return Inertia::render('Users/Create', $this->formOptions());
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->userService->createUser($request->validated());

        return redirect()->route('users.index')
            ->with('success', __('users.created_successfully'));
    }

    /**
     * Display the specified user.
     */
    public function show(int $id): Response
    {
        $this->authorize('view-users');

        $user = $this->userService->getUserById($id);

        if (! $user) {
            abort(404);
        }

        return Inertia::render('Users/Show', [
            'user' => fn () => new UserResource($user),
        ]);
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(int $id): Response
    {
        $this->authorize('edit-users');

        $user = $this->userService->getUserById($id);

        if (! $user) {
            abort(404);
        }

        $currentRotationAssignment = $this->rotationService->getActiveAssignment($user->id);

        return Inertia::render('Users/Edit', array_merge(
            [
                'user' => fn () => new UserResource($user),
                'currentRotationAssignment' => fn () => $currentRotationAssignment ? [
                    'id' => $currentRotationAssignment->id,
                    'rotation_id' => $currentRotationAssignment->rotation_id,
                    'rotation_group_id' => $currentRotationAssignment->rotation_group_id,
                    'rotation_name' => $currentRotationAssignment->rotation->name ?? null,
                    'group_name' => $currentRotationAssignment->rotationGroup->name ?? null,
                    'start_date' => $currentRotationAssignment->start_date?->format('Y-m-d'),
                    'end_date' => $currentRotationAssignment->end_date?->format('Y-m-d'),
                ] : null,
                'rotations' => fn () => $this->rotationService->getAllList()
                    ->map(fn ($r) => [
                        'id' => $r->id,
                        'name' => $r->name,
                        'groups' => $r->groups->map(fn ($g) => [
                            'id' => $g->id,
                            'name' => $g->name,
                        ])->values(),
                    ]),
            ],
            $this->formOptions()
        ));
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, int $id): RedirectResponse
    {
        $user = $this->userService->getUserById($id);

        if (! $user) {
            abort(404);
        }

        $this->userService->updateUser($user, $request->validated());

        return redirect()->route('users.edit', $id)
            ->with('success', __('users.updated_successfully'));
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(int $id): RedirectResponse
    {
        $this->authorize('delete-users');

        $user = $this->userService->getUserById($id);

        if (! $user) {
            abort(404);
        }

        $this->userService->deleteUser($user);

        return redirect()->route('users.index')
            ->with('success', __('users.deleted_successfully'));
    }

    /**
     * Bulk delete users.
     */
    public function bulkDelete(Request $request): RedirectResponse
    {
        $this->authorize('delete-users');

        $ids = (array) $request->input('ids', []);

        $count = $this->userService->bulkDeleteUsers($ids);

        return redirect()->route('users.index')
            ->with('success', __('users.bulk_deleted', ['count' => $count]));
    }

    // ------------------------------------------------------------------
    // Shifts sub-resource
    // ------------------------------------------------------------------

    /**
     * Show the page to manage a user's shifts.
     */
    public function shifts(int $id): Response
    {
        $this->authorize('edit-users');

        $user = $this->userService->getUserById($id);

        if (! $user) {
            abort(404);
        }

        return Inertia::render('Users/Shifts', [
            'user' => fn () => new UserResource($user->load('shifts')),
            'shifts' => fn () => $this->shiftService->getActiveShifts()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'shift_name' => $s->shift_name,
                    'shift_code' => $s->shift_code,
                ]),
        ]);
    }

    /**
     * Sync the shifts for the given user.
     */
    public function updateShifts(Request $request, int $id): RedirectResponse
    {
        $this->authorize('edit-users');

        $user = $this->userService->getUserById($id);

        if (! $user) {
            abort(404);
        }

        $shifts = (array) $request->input('shifts', []);
        $this->userService->syncShifts($user, $shifts);

        return redirect()->route('users.shifts', $id)
            ->with('success', __('users.shifts_updated_successfully'));
    }

    // ------------------------------------------------------------------
    // Fingerprints sub-resource
    // ------------------------------------------------------------------

    /**
     * Show the page to manage a user's fingerprints.
     *
     * Fingerprint data is owned by the FingerprintDevices module, but the
     * page reuses the FingerprintTemplate controller through a forward.
     * Here we only render the wrapper page.
     */
    public function fingerprints(int $id): Response
    {
        $this->authorize('view-users');

        $user = $this->userService->getUserById($id);

        if (! $user) {
            abort(404);
        }

        return Inertia::render('Users/Fingerprints', [
            'user' => fn () => new UserResource($user),
        ]);
    }

    // ------------------------------------------------------------------
    // Roles / permissions sub-resource
    // ------------------------------------------------------------------

    /**
     * Sync the roles for the given user.
     */
    public function updateRoles(Request $request, int $id): RedirectResponse
    {
        $this->authorize('edit-users');

        $user = $this->userService->getUserById($id);

        if (! $user) {
            abort(404);
        }

        $roles = (array) $request->input('roles', []);
        $this->userService->syncRoles($user, $roles);

        return redirect()->route('users.show', $id)
            ->with('success', __('users.roles_updated_successfully'));
    }

    /**
     * Sync the direct permissions for the given user.
     */
    public function updatePermissions(Request $request, int $id): RedirectResponse
    {
        $this->authorize('edit-users');

        $user = $this->userService->getUserById($id);

        if (! $user) {
            abort(404);
        }

        $permissions = (array) $request->input('permissions', []);
        $user->syncPermissions($permissions);

        return redirect()->route('users.show', $id)
            ->with('success', __('users.permissions_updated_successfully'));
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Build the option arrays used by Create and Edit forms.
     *
     * @return array<string, callable>
     */
    protected function formOptions(): array
    {
        return [
            'companies' => fn () => $this->companyService->getActiveCompanies()
                ->map(fn ($c) => ['id' => $c->id, 'company_name' => $c->company_name]),
            'branches' => fn () => $this->branchService->getActiveBranches()
                ->map(fn ($b) => ['id' => $b->id, 'branch_name' => $b->branch_name, 'company_id' => $b->company_id]),
            'departments' => fn () => $this->departmentService->getAllDepartments([])
                ->getCollection()
                ->map(fn ($d) => ['id' => $d->id, 'department_name' => $d->department_name, 'branch_id' => $d->branch_id]),
            'positions' => fn () => $this->positionService->getAllPositions([])
                ->getCollection()
                ->map(fn ($p) => ['id' => $p->id, 'position_name' => $p->position_name]),
            'grades' => fn () => $this->gradeService->getActiveGrades()
                ->map(fn ($g) => ['id' => $g->id, 'grade_name' => $g->grade_name]),
            'subordinations' => fn () => $this->subordinationService->getActiveSubordinations()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'code' => $s->code,
                    'display_name' => $s->display_name,
                ]),
            'managers' => fn () => User::query()
                ->where('status', 1)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]),
            'roles' => fn () => Role::orderBy('name')->get()
                ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name]),
            'permissions' => fn () => Permission::orderBy('name')->get()
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name]),
            'attendanceGroups' => fn () => $this->attendanceGroupService->getAllGroups([], 100)
                ->getCollection()
                ->map(fn ($g) => ['id' => $g->id, 'name' => $g->name, 'code' => $g->code]),
        ];
    }

    /**
     * Export users to Excel.
     */
    public function export(Request $request)
    {
        $this->authorize('view-users');

        $users = $this->userService->getAllUsers(
            $request->only([
                'search', 'company_id', 'branch_id', 'department_id',
                'position_id', 'grade_id', 'subordination_id', 'shift_id', 'status',
                'employment_type', 'role', 'is_active_employee',
            ]),
            10000
        );

        $export = new UsersExport($users->getCollection());

        return $this->downloadExcel($export->build(), 'users');
    }
}
