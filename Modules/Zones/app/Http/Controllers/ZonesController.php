<?php

namespace Modules\Zones\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\ExcelExportable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Companies\Services\CompanyService;
use Modules\Zones\Http\Requests\AssignBranchesRequest;
use Modules\Zones\Http\Requests\StoreZoneRequest;
use Modules\Zones\Http\Requests\UpdateZoneRequest;
use Modules\Zones\Http\Resources\ZoneResource;
use Modules\Zones\Jobs\RecalculateZoneBranchStats;
use Modules\Zones\Jobs\SyncZoneDevices;
use Modules\Zones\Models\Zone;
use Modules\Zones\Services\ZoneBranchService;
use Modules\Zones\Services\ZoneService;

/**
 * ZonesController — HTTP entry-point for the zone catalogue.
 *
 * Every write routes through {@see ZoneService}; the controller only
 * shapes the Inertia responses and dispatches background jobs.
 */
class ZonesController extends Controller
{
    use ExcelExportable;

    public function __construct(
        private ZoneService $zoneService,
        private ZoneBranchService $zoneBranchService,
        private CompanyService $companyService,
    ) {}

    /**
     * Display a paginated list of zones.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-zones');

        $filters = $this->cleanFilters($request->only([
            'search', 'company_id', 'zone_type', 'city', 'region', 'is_active',
        ]));

        return Inertia::render('Zones/Index', [
            'filters' => fn () => $filters,
            'zones' => fn () => ZoneResource::collection(
                $this->zoneService->getAllZones($filters)
            ),
            'companies' => fn () => $this->companyService->getActiveCompanies()
                ->map(fn ($c) => ['id' => $c->id, 'company_name' => $c->company_name]),
        ]);
    }

    /**
     * Show the form for creating a new zone.
     */
    public function create(): Response
    {
        $this->authorize('create-zones');

        return Inertia::render('Zones/Create', [
            'companies' => fn () => $this->companyService->getActiveCompanies()
                ->map(fn ($c) => ['id' => $c->id, 'company_name' => $c->company_name]),
        ]);
    }

    /**
     * Persist a new zone row.
     */
    public function store(StoreZoneRequest $request): RedirectResponse
    {
        $this->authorize('create-zones');

        $zone = $this->zoneService->createZone($request->validated());

        return redirect()->route('zones.show', $zone->id)
            ->with('success', __('zones.created_successfully'));
    }

    /**
     * Display the specified zone.
     */
    public function show(int $zone): Response
    {
        $this->authorize('view-zones');

        $z = $this->zoneService->getZoneById($zone);
        if (! $z) {
            abort(404);
        }

        return Inertia::render('Zones/Show', [
            'zone' => fn () => new ZoneResource($z),
            'branches' => fn () => $this->zoneBranchService
                ->getBranchesForZone($zone)
                ->map(fn ($b) => [
                    'id' => $b->id,
                    'branch_code' => $b->branch_code,
                    'branch_name' => $b->branch_name,
                    'city' => $b->city,
                    'is_main' => (bool) $b->is_main,
                    'pivot_is_primary' => (bool) ($b->pivot_is_primary ?? false),
                    'pivot_priority' => (int) ($b->pivot_priority ?? 0),
                    'pivot_notes' => $b->pivot_notes,
                ]),
        ]);
    }

    /**
     * Show the form for editing the specified zone.
     */
    public function edit(int $zone): Response
    {
        $this->authorize('edit-zones');

        $z = $this->zoneService->getZoneById($zone);
        if (! $z) {
            abort(404);
        }

        return Inertia::render('Zones/Edit', [
            'zone' => fn () => new ZoneResource($z),
            'companies' => fn () => $this->companyService->getActiveCompanies()
                ->map(fn ($c) => ['id' => $c->id, 'company_name' => $c->company_name]),
        ]);
    }

    /**
     * Update the specified zone.
     */
    public function update(UpdateZoneRequest $request, int $zone): RedirectResponse
    {
        $this->authorize('edit-zones');

        $z = $this->zoneService->getZoneById($zone);
        if (! $z) {
            abort(404);
        }

        $this->zoneService->updateZone($z, $request->validated());

        return redirect()->route('zones.show', $zone)
            ->with('success', __('zones.updated_successfully'));
    }

    /**
     * Soft delete the specified zone.
     */
    public function destroy(int $zone): RedirectResponse
    {
        $this->authorize('delete-zones');

        $z = $this->zoneService->getZoneById($zone);
        if (! $z) {
            abort(404);
        }

        $this->zoneService->deleteZone($z);

        return redirect()->route('zones.index')
            ->with('success', __('zones.deleted_successfully'));
    }

    /**
     * Render the dashboard view with aggregate counts.
     */
    public function dashboard(Request $request): Response
    {
        $this->authorize('view-zones');

        $filters = $this->cleanFilters($request->only(['company_id']));

        $base = $this->zoneService->getAllZones($filters, 100);
        $zones = $base->getCollection();

        $stats = [
            'total' => $base->total(),
            'active' => $zones->where('is_active', true)->count(),
            'inactive' => $zones->where('is_active', false)->count(),
            'branches_total' => (int) $zones->sum('branches_count'),
            'employees_total' => (int) $zones->sum('employees_count'),
            'devices_total' => (int) $zones->sum('devices_count'),
        ];

        return Inertia::render('Zones/Dashboard', [
            'stats' => fn () => $stats,
            'zones' => fn () => ZoneResource::collection($zones->load('company')),
            'filters' => fn () => $filters,
            'companies' => fn () => $this->companyService->getActiveCompanies()
                ->map(fn ($c) => ['id' => $c->id, 'company_name' => $c->company_name]),
        ]);
    }

    /**
     * Render the branches management view.
     */
    public function branches(int $zone): Response
    {
        $this->authorize('edit-zones');

        $z = $this->zoneService->getZoneById($zone);
        if (! $z) {
            abort(404);
        }

        $branches = $this->zoneBranchService->getBranchesForZone($zone);

        return Inertia::render('Zones/Branches', [
            'zone' => fn () => new ZoneResource($z),
            'branches' => fn () => $branches->map(fn ($b) => [
                'id' => $b->id,
                'branch_code' => $b->branch_code,
                'branch_name' => $b->branch_name,
                'city' => $b->city,
                'is_main' => (bool) $b->is_main,
                'status' => $b->status,
                'pivot_is_primary' => (bool) ($b->pivot_is_primary ?? false),
                'pivot_priority' => (int) ($b->pivot_priority ?? 0),
                'pivot_notes' => $b->pivot_notes,
            ]),
        ]);
    }

    /**
     * Replace the zone's branch assignment wholesale.
     */
    public function assignBranches(AssignBranchesRequest $request, int $zone): RedirectResponse
    {
        $this->authorize('edit-zones');

        $z = Zone::whereKey($zone)->first();
        if (! $z) {
            abort(404);
        }

        $this->zoneBranchService->syncBranches($zone, $request->validated('branches'));
        RecalculateZoneBranchStats::dispatch($zone);

        return redirect()->route('zones.branches', $zone)
            ->with('success', __('zones.branches_assigned_successfully'));
    }

    /**
     * Drop empty / null entries from a filter bag.
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

    /**
     * Dispatch the cached-counts refresh job.
     */
    public function recalculate(int $zone): RedirectResponse
    {
        $this->authorize('edit-zones');

        $z = Zone::whereKey($zone)->first();
        if (! $z) {
            abort(404);
        }

        RecalculateZoneBranchStats::dispatch($zone);

        return redirect()->route('zones.show', $zone)
            ->with('success', __('zones.recalculate_queued'));
    }

    /**
     * Dispatch the device-sync job.
     */
    public function syncDevices(int $zone): RedirectResponse
    {
        $this->authorize('edit-zones');

        $z = Zone::whereKey($zone)->first();
        if (! $z) {
            abort(404);
        }

        SyncZoneDevices::dispatch($zone);

        return redirect()->route('zones.show', $zone)
            ->with('success', __('zones.recalculate_queued'));
    }

    /**
     * Export zones to Excel.
     */
    public function export(Request $request)
    {
        $this->authorize('view-zones');

        $filters = $this->cleanFilters($request->only([
            'search', 'company_id', 'zone_type', 'city', 'region', 'is_active',
        ]));

        $zones = $this->zoneService->getAllZones($filters, 10000);

        $headers = ['#', 'اسم المنطقة', 'الرمز', 'النوع', 'المدينة', 'المنطقة', 'الشركة', 'الفروع', 'الموظفين', 'الأجهزة', 'نشط'];
        $columns = [
            'index' => ['key' => 'id', 'type' => 'integer', 'width' => 8],
            'name' => ['key' => 'name', 'type' => 'string', 'width' => 25],
            'code' => ['key' => 'code', 'type' => 'string', 'width' => 15],
            'type' => [
                'key' => 'zone_type',
                'type' => 'status',
                'width' => 15,
                'map' => [
                    'headquarters' => 'المقر الرئيسي',
                    'branch' => 'فرع',
                    'region' => 'منطقة',
                    'city' => 'مدينة',
                ],
            ],
            'city' => ['key' => 'city', 'type' => 'string', 'width' => 15],
            'region' => ['key' => 'region', 'type' => 'string', 'width' => 15],
            'company' => ['key' => 'company.company_name', 'type' => 'string', 'width' => 20],
            'branches' => ['key' => 'branches_count', 'type' => 'integer', 'width' => 10],
            'employees' => ['key' => 'employees_count', 'type' => 'integer', 'width' => 10],
            'devices' => ['key' => 'devices_count', 'type' => 'integer', 'width' => 10],
            'is_active' => [
                'key' => 'is_active',
                'type' => 'status',
                'width' => 10,
                'map' => [true => 'نشط', false => 'غير نشط'],
                'status_color' => [
                    true => ['text' => '16A34A', 'bg' => 'DCFCE7'],
                    false => ['text' => 'DC2626', 'bg' => 'FEE2E2'],
                ],
            ],
        ];

        return $this->quickExcelExport('قائمة المناطق', $headers, $zones->getCollection(), $columns, 'zones');
    }
}
