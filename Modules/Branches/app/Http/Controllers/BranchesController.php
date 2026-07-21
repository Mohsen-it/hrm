<?php

namespace Modules\Branches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\ExcelExportable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Branches\Exports\BranchesExport;
use Modules\Branches\Http\Requests\StoreBranchRequest;
use Modules\Branches\Http\Requests\UpdateBranchRequest;
use Modules\Branches\Http\Resources\BranchResource;
use Modules\Branches\Services\BranchService;
use Modules\Companies\Services\CompanyService;

class BranchesController extends Controller
{
    use ExcelExportable;

    public function __construct(
        private BranchService $branchService,
        private CompanyService $companyService
    ) {}

    /**
     * Display a listing of branches.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-branches');

        return Inertia::render('Branches/Index', [
            'filters' => fn () => $request->only(['search', 'status', 'company_id', 'is_main']),
            'branches' => fn () => BranchResource::collection(
                $this->branchService->getAllBranches(
                    $request->only(['search', 'status', 'company_id', 'is_main'])
                )
            ),
            'companies' => fn () => $this->companyService->getActiveCompanies()
                ->map(fn ($c) => ['id' => $c->id, 'company_name' => $c->company_name]),
        ]);
    }

    /**
     * Show the form for creating a new branch.
     */
    public function create(): Response
    {
        $this->authorize('create-branches');

        return Inertia::render('Branches/Create', [
            'companies' => fn () => $this->companyService->getActiveCompanies()
                ->map(fn ($c) => ['id' => $c->id, 'company_name' => $c->company_name]),
        ]);
    }

    /**
     * Store a newly created branch.
     */
    public function store(StoreBranchRequest $request): RedirectResponse
    {
        $this->branchService->createBranch($request->validated());

        return redirect()->route('branches.index')
            ->with('success', __('branches.created_successfully'));
    }

    /**
     * Display the specified branch.
     */
    public function show(int $id): Response
    {
        $this->authorize('view-branches');

        $branch = $this->branchService->getBranchById($id);

        if (! $branch) {
            abort(404);
        }

        return Inertia::render('Branches/Show', [
            'branch' => fn () => new BranchResource($branch),
        ]);
    }

    /**
     * Show the form for editing the specified branch.
     */
    public function edit(int $id): Response
    {
        $this->authorize('edit-branches');

        $branch = $this->branchService->getBranchById($id);

        if (! $branch) {
            abort(404);
        }

        return Inertia::render('Branches/Edit', [
            'branch' => fn () => new BranchResource($branch),
            'companies' => fn () => $this->companyService->getActiveCompanies()
                ->map(fn ($c) => ['id' => $c->id, 'company_name' => $c->company_name]),
        ]);
    }

    /**
     * Update the specified branch.
     */
    public function update(UpdateBranchRequest $request, int $id): RedirectResponse
    {
        $branch = $this->branchService->getBranchById($id);

        if (! $branch) {
            abort(404);
        }

        $this->branchService->updateBranch($branch, $request->validated());

        return redirect()->route('branches.index')
            ->with('success', __('branches.updated_successfully'));
    }

    /**
     * Remove the specified branch from storage.
     */
    public function destroy(int $id): RedirectResponse
    {
        $this->authorize('delete-branches');

        $branch = $this->branchService->getBranchById($id);

        if (! $branch) {
            abort(404);
        }

        $this->branchService->deleteBranch($branch);

        return redirect()->route('branches.index')
            ->with('success', __('branches.deleted_successfully'));
    }

    /**
     * Export branches to Excel.
     */
    public function export(Request $request)
    {
        $this->authorize('view-branches');

        $branches = $this->branchService->getAllBranches(
            $request->only(['search', 'status', 'company_id', 'is_main']),
            10000
        );

        $export = new BranchesExport($branches->getCollection());

        return $this->downloadExcel($export->build(), 'branches');
    }
}
