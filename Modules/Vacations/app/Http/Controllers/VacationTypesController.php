<?php

namespace Modules\Vacations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Vacations\Http\Requests\StoreVacationTypeRequest;
use Modules\Vacations\Http\Requests\UpdateVacationTypeRequest;
use Modules\Vacations\Http\Resources\VacationTypeResource;
use Modules\Vacations\Services\VacationTypeService;

/**
 * VacationTypesController — CRUD on the vacation catalog.
 *
 * The page is mostly read-only (`index` / `create` / `edit` / `show`)
 * with a thin write surface (`store` / `update` / `destroy`).
 */
class VacationTypesController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private VacationTypeService $typeService,
    ) {}

    /**
     * Display a listing of vacation types.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-vacation-types');

        $filters = $this->cleanFilters($request->only([
            'search', 'is_active', 'is_paid', 'requires_approval',
        ]));

        return Inertia::render('Vacations/Types/Index', [
            'filters' => fn () => $filters,
            'types' => fn () => VacationTypeResource::collection(
                $this->typeService->getAllTypes($filters, 20)
            )->response($request)->getData(true),
        ]);
    }

    /**
     * Show the form for creating a new vacation type.
     */
    public function create(): Response
    {
        $this->authorize('create-vacation-types');

        return Inertia::render('Vacations/Types/Create');
    }

    /**
     * Persist a new vacation type.
     */
    public function store(StoreVacationTypeRequest $request): RedirectResponse
    {
        $this->authorize('create-vacation-types');

        $type = $this->typeService->createType($request->validated());

        return redirect()->route('vacations.types.index')
            ->with('success', __('vacations.type_created_successfully'));
    }

    /**
     * Display the specified vacation type.
     */
    public function show(int $vacationType): Response
    {
        $this->authorize('view-vacation-types');

        $type = $this->typeService->findType($vacationType);
        if (! $type) {
            abort(404);
        }

        return Inertia::render('Vacations/Types/Show', [
            'type' => fn () => (new VacationTypeResource($type))->resolve(),
        ]);
    }

    /**
     * Show the form for editing the specified vacation type.
     */
    public function edit(int $vacationType): Response
    {
        $this->authorize('edit-vacation-types');

        $type = $this->typeService->findType($vacationType);
        if (! $type) {
            abort(404);
        }

        return Inertia::render('Vacations/Types/Edit', [
            'type' => fn () => (new VacationTypeResource($type))->resolve(),
        ]);
    }

    /**
     * Update the specified vacation type.
     */
    public function update(UpdateVacationTypeRequest $request, int $vacationType): RedirectResponse
    {
        $this->authorize('edit-vacation-types');

        $type = $this->typeService->findType($vacationType);
        if (! $type) {
            abort(404);
        }

        $this->typeService->updateType($type, $request->validated());

        return redirect()->route('vacations.types.index')
            ->with('success', __('vacations.type_updated_successfully'));
    }

    /**
     * Soft delete the specified vacation type.
     */
    public function destroy(int $vacationType): RedirectResponse
    {
        $this->authorize('delete-vacation-types');

        $type = $this->typeService->findType($vacationType);
        if (! $type) {
            abort(404);
        }

        $this->typeService->deleteType($type);

        return redirect()->route('vacations.types.index')
            ->with('success', __('vacations.type_deleted_successfully'));
    }

    /**
     * Drop empty / null entries from a filter bag so the URL stays clean.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function cleanFilters(array $filters): array
    {
        return array_filter(
            $filters,
            fn ($v) => $v !== null && $v !== '' && $v !== [],
        );
    }
}
