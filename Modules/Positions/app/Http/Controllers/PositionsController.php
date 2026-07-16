<?php

namespace Modules\Positions\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Branches\Services\BranchService;
use Modules\Companies\Services\CompanyService;
use Modules\Departments\Services\DepartmentService;
use Modules\Positions\Http\Resources\PositionResource;
use Modules\Positions\Services\PositionService;

class PositionsController extends Controller
{
    public function __construct(
        private PositionService $positionService,
        private CompanyService $companyService,
        private BranchService $branchService,
        private DepartmentService $departmentService
    ) {}

    /**
     * Display a listing of positions.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-positions');

        return Inertia::render('Positions/Index', [
            'filters' => fn () => $request->only(['search', 'status', 'company_id', 'branch_id', 'department_id']),
            'positions' => fn () => PositionResource::collection(
                $this->positionService->getAllPositions(
                    $request->only(['search', 'status', 'company_id', 'branch_id', 'department_id'])
                )
            ),
            'companies' => fn () => $this->companyService->getActiveCompanies()
                ->map(fn ($c) => ['id' => $c->id, 'company_name' => $c->company_name]),
            'branches' => fn () => $this->branchService->getActiveBranches()
                ->map(fn ($b) => ['id' => $b->id, 'branch_name' => $b->branch_name, 'company_id' => $b->company_id]),
            'departments' => fn () => $this->departmentService->getRootDepartments()
                ->map(fn ($d) => ['id' => $d->id, 'department_name' => $d->department_name, 'branch_id' => $d->branch_id]),
        ]);
    }

    /**
     * Show the form for creating a new position.
     */
    public function create(): Response
    {
        $this->authorize('create-positions');

        return Inertia::render('Positions/Create', [
            'companies' => fn () => $this->companyService->getActiveCompanies()
                ->map(fn ($c) => ['id' => $c->id, 'company_name' => $c->company_name]),
            'branches' => fn () => $this->branchService->getActiveBranches()
                ->map(fn ($b) => ['id' => $b->id, 'branch_name' => $b->branch_name, 'company_id' => $b->company_id]),
            'departments' => fn () => $this->departmentService->getRootDepartments()
                ->map(fn ($d) => ['id' => $d->id, 'department_name' => $d->department_name, 'branch_id' => $d->branch_id]),
        ]);
    }

    /**
     * Store a newly created position.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create-positions');

        $this->positionService->createPosition($request->all());

        return redirect()->route('positions.index')
            ->with('success', __('positions.created_successfully'));
    }

    /**
     * Display the specified position.
     */
    public function show(int $id): Response
    {
        $this->authorize('view-positions');

        $position = $this->positionService->getPositionById($id);

        if (! $position) {
            abort(404);
        }

        return Inertia::render('Positions/Show', [
            'position' => fn () => new PositionResource($position),
        ]);
    }

    /**
     * Show the form for editing the specified position.
     */
    public function edit(int $id): Response
    {
        $this->authorize('edit-positions');

        $position = $this->positionService->getPositionById($id);

        if (! $position) {
            abort(404);
        }

        return Inertia::render('Positions/Edit', [
            'position' => fn () => new PositionResource($position),
            'companies' => fn () => $this->companyService->getActiveCompanies()
                ->map(fn ($c) => ['id' => $c->id, 'company_name' => $c->company_name]),
            'branches' => fn () => $this->branchService->getActiveBranches()
                ->map(fn ($b) => ['id' => $b->id, 'branch_name' => $b->branch_name, 'company_id' => $b->company_id]),
            'departments' => fn () => $this->departmentService->getRootDepartments()
                ->map(fn ($d) => ['id' => $d->id, 'department_name' => $d->department_name, 'branch_id' => $d->branch_id]),
        ]);
    }

    /**
     * Update the specified position.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $this->authorize('edit-positions');

        $position = $this->positionService->getPositionById($id);

        if (! $position) {
            abort(404);
        }

        $this->positionService->updatePosition($position, $request->all());

        return redirect()->route('positions.index')
            ->with('success', __('positions.updated_successfully'));
    }

    /**
     * Remove the specified position from storage.
     */
    public function destroy(int $id): RedirectResponse
    {
        $this->authorize('delete-positions');

        $position = $this->positionService->getPositionById($id);

        if (! $position) {
            abort(404);
        }

        $this->positionService->deletePosition($position);

        return redirect()->route('positions.index')
            ->with('success', __('positions.deleted_successfully'));
    }
}
