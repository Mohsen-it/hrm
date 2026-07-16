<?php

namespace Modules\Shifts\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Branches\Services\BranchService;
use Modules\Companies\Services\CompanyService;
use Modules\Shifts\Http\Resources\ShiftResource;
use Modules\Shifts\Services\ShiftService;

class ShiftsController extends Controller
{
    public function __construct(
        private ShiftService $shiftService,
        private CompanyService $companyService,
        private BranchService $branchService
    ) {}

    /**
     * Display a listing of shifts.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-shifts');

        return Inertia::render('Shifts/Index', [
            'filters' => fn () => $request->only(['search', 'status', 'company_id', 'branch_id']),
            'shifts' => fn () => ShiftResource::collection(
                $this->shiftService->getAllShifts(
                    $request->only(['search', 'status', 'company_id', 'branch_id'])
                )
            ),
            'companies' => fn () => $this->companyService->getActiveCompanies()
                ->map(fn ($c) => ['id' => $c->id, 'company_name' => $c->company_name]),
            'branches' => fn () => $this->branchService->getActiveBranches()
                ->map(fn ($b) => ['id' => $b->id, 'branch_name' => $b->branch_name, 'company_id' => $b->company_id]),
        ]);
    }

    /**
     * Show the form for creating a new shift.
     */
    public function create(): Response
    {
        $this->authorize('create-shifts');

        return Inertia::render('Shifts/Create', [
            'companies' => fn () => $this->companyService->getActiveCompanies()
                ->map(fn ($c) => ['id' => $c->id, 'company_name' => $c->company_name]),
            'branches' => fn () => $this->branchService->getActiveBranches()
                ->map(fn ($b) => ['id' => $b->id, 'branch_name' => $b->branch_name, 'company_id' => $b->company_id]),
        ]);
    }

    /**
     * Store a newly created shift.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create-shifts');

        $this->shiftService->createShift($request->all());

        return redirect()->route('shifts.index')
            ->with('success', __('shifts.created_successfully'));
    }

    /**
     * Display the specified shift.
     */
    public function show(int $id): Response
    {
        $this->authorize('view-shifts');

        $shift = $this->shiftService->getShiftById($id);

        if (! $shift) {
            abort(404);
        }

        return Inertia::render('Shifts/Show', [
            'shift' => fn () => new ShiftResource($shift),
        ]);
    }

    /**
     * Show the form for editing the specified shift.
     */
    public function edit(int $id): Response
    {
        $this->authorize('edit-shifts');

        $shift = $this->shiftService->getShiftById($id);

        if (! $shift) {
            abort(404);
        }

        return Inertia::render('Shifts/Edit', [
            'shift' => fn () => new ShiftResource($shift),
            'companies' => fn () => $this->companyService->getActiveCompanies()
                ->map(fn ($c) => ['id' => $c->id, 'company_name' => $c->company_name]),
            'branches' => fn () => $this->branchService->getActiveBranches()
                ->map(fn ($b) => ['id' => $b->id, 'branch_name' => $b->branch_name, 'company_id' => $b->company_id]),
        ]);
    }

    /**
     * Update the specified shift.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $this->authorize('edit-shifts');

        $shift = $this->shiftService->getShiftById($id);

        if (! $shift) {
            abort(404);
        }

        $this->shiftService->updateShift($shift, $request->all());

        return redirect()->route('shifts.index')
            ->with('success', __('shifts.updated_successfully'));
    }

    /**
     * Remove the specified shift from storage.
     */
    public function destroy(int $id): RedirectResponse
    {
        $this->authorize('delete-shifts');

        $shift = $this->shiftService->getShiftById($id);

        if (! $shift) {
            abort(404);
        }

        $this->shiftService->deleteShift($shift);

        return redirect()->route('shifts.index')
            ->with('success', __('shifts.deleted_successfully'));
    }
}
