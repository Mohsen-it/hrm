<?php

namespace Modules\Departments\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Branches\Services\BranchService;
use Modules\Companies\Services\CompanyService;
use Modules\Departments\Http\Resources\DepartmentResource;
use Modules\Departments\Services\DepartmentService;

class DepartmentsController extends Controller
{
    public function __construct(
        private DepartmentService $departmentService,
        private BranchService $branchService,
        private CompanyService $companyService
    ) {}

    /**
     * Display a listing of departments.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-departments');

        return Inertia::render('Departments/Index', [
            'filters' => fn () => $request->only(['search', 'status', 'company_id', 'branch_id', 'parent_id', 'roots_only']),
            'departments' => fn () => DepartmentResource::collection(
                $this->departmentService->getAllDepartments(
                    $request->only(['search', 'status', 'company_id', 'branch_id', 'parent_id', 'roots_only'])
                )
            ),
            'companies' => fn () => $this->companyService->getActiveCompanies()
                ->map(fn ($c) => ['id' => $c->id, 'company_name' => $c->company_name]),
            'branches' => fn () => $this->branchService->getActiveBranches()
                ->map(fn ($b) => ['id' => $b->id, 'branch_name' => $b->branch_name, 'company_id' => $b->company_id]),
        ]);
    }

    /**
     * Show the form for creating a new department.
     */
    public function create(): Response
    {
        $this->authorize('create-departments');

        return Inertia::render('Departments/Create', [
            'companies' => fn () => $this->companyService->getActiveCompanies()
                ->map(fn ($c) => ['id' => $c->id, 'company_name' => $c->company_name]),
            'branches' => fn () => $this->branchService->getActiveBranches()
                ->map(fn ($b) => ['id' => $b->id, 'branch_name' => $b->branch_name, 'company_id' => $b->company_id]),
            'parents' => fn () => $this->departmentService->getRootDepartments()
                ->map(fn ($d) => ['id' => $d->id, 'department_name' => $d->department_name, 'branch_id' => $d->branch_id]),
        ]);
    }

    /**
     * Store a newly created department.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create-departments');

        $this->departmentService->createDepartment($request->all());

        return redirect()->route('departments.index')
            ->with('success', __('departments.created_successfully'));
    }

    /**
     * Display the specified department.
     */
    public function show(int $id): Response
    {
        $this->authorize('view-departments');

        $department = $this->departmentService->getDepartmentById($id);

        if (! $department) {
            abort(404);
        }

        return Inertia::render('Departments/Show', [
            'department' => fn () => new DepartmentResource($department),
        ]);
    }

    /**
     * Show the form for editing the specified department.
     */
    public function edit(int $id): Response
    {
        $this->authorize('edit-departments');

        $department = $this->departmentService->getDepartmentById($id);

        if (! $department) {
            abort(404);
        }

        return Inertia::render('Departments/Edit', [
            'department' => fn () => new DepartmentResource($department),
            'companies' => fn () => $this->companyService->getActiveCompanies()
                ->map(fn ($c) => ['id' => $c->id, 'company_name' => $c->company_name]),
            'branches' => fn () => $this->branchService->getActiveBranches()
                ->map(fn ($b) => ['id' => $b->id, 'branch_name' => $b->branch_name, 'company_id' => $b->company_id]),
            'parents' => fn () => $this->departmentService->getRootDepartments()
                ->map(fn ($d) => ['id' => $d->id, 'department_name' => $d->department_name, 'branch_id' => $d->branch_id]),
        ]);
    }

    /**
     * Update the specified department.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $this->authorize('edit-departments');

        $department = $this->departmentService->getDepartmentById($id);

        if (! $department) {
            abort(404);
        }

        $this->departmentService->updateDepartment($department, $request->all());

        return redirect()->route('departments.index')
            ->with('success', __('departments.updated_successfully'));
    }

    /**
     * Remove the specified department from storage.
     */
    public function destroy(int $id): RedirectResponse
    {
        $this->authorize('delete-departments');

        $department = $this->departmentService->getDepartmentById($id);

        if (! $department) {
            abort(404);
        }

        $this->departmentService->deleteDepartment($department);

        return redirect()->route('departments.index')
            ->with('success', __('departments.deleted_successfully'));
    }
}
