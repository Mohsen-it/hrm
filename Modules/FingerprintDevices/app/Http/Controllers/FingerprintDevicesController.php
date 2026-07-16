<?php

namespace Modules\FingerprintDevices\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Branches\Services\BranchService;
use Modules\FingerprintDevices\Http\Requests\StoreFingerprintDeviceRequest;
use Modules\FingerprintDevices\Http\Requests\UpdateFingerprintDeviceRequest;
use Modules\FingerprintDevices\Http\Resources\FingerprintDeviceResource;
use Modules\FingerprintDevices\Services\FingerprintDeviceService;
use Modules\FingerprintDevices\Services\FingerprintDeviceTypeService;

/**
 * FingerprintDevicesController — CRUD for fingerprint devices.
 */
class FingerprintDevicesController extends Controller
{
    public function __construct(
        private FingerprintDeviceService $deviceService,
        private FingerprintDeviceTypeService $typeService,
        private BranchService $branchService,
    ) {}

    public function index(): Response
    {
        $this->authorize('view-fingerprint-devices');

        $filters = $this->cleanFilters(request()->only([
            'search', 'status', 'device_type_id', 'branch_id', 'connection_type',
        ]));

        return Inertia::render('FingerprintDevices/Index', [
            'filters' => fn () => $filters,
            'devices' => fn () => $this->deviceService->getAllDevices($filters)
                ->through(fn ($d) => (new FingerprintDeviceResource($d))->toArray(request())),
            'deviceTypes' => fn () => $this->typeService->getActiveDeviceTypes()
                ->map(fn ($dt) => ['id' => $dt->id, 'name' => $dt->name])
                ->values(),
            'branches' => fn () => $this->branchService->getActiveBranches()
                ->map(fn ($b) => ['id' => $b->id, 'branch_name' => $b->branch_name])
                ->values(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create-fingerprint-devices');

        return Inertia::render('FingerprintDevices/Create', [
            'deviceTypes' => fn () => $this->typeService->getActiveDeviceTypes()
                ->map(fn ($dt) => [
                    'id' => $dt->id,
                    'name' => $dt->name,
                    'manufacturer' => $dt->manufacturer,
                    'default_port' => $dt->default_port,
                ])
                ->values(),
            'branches' => fn () => $this->branchService->getActiveBranches()
                ->map(fn ($b) => ['id' => $b->id, 'branch_name' => $b->branch_name])
                ->values(),
        ]);
    }

    public function store(StoreFingerprintDeviceRequest $request): RedirectResponse
    {
        $this->deviceService->createDevice($request);

        return redirect()->route('fingerprint-devices.index')
            ->with('success', __('fingerprint_devices.device_created'));
    }

    public function show(int $id): Response
    {
        $this->authorize('view-fingerprint-devices');

        $device = $this->deviceService->getDeviceById($id);

        if (! $device) {
            abort(404);
        }

        return Inertia::render('FingerprintDevices/Show', [
            'device' => fn () => (new FingerprintDeviceResource($device))->toArray(request()),
        ]);
    }

    public function edit(int $id): Response
    {
        $this->authorize('edit-fingerprint-devices');

        $device = $this->deviceService->getDeviceById($id);

        if (! $device) {
            abort(404);
        }

        return Inertia::render('FingerprintDevices/Edit', [
            'device' => fn () => (new FingerprintDeviceResource($device))->toArray(request()),
            'deviceTypes' => fn () => $this->typeService->getActiveDeviceTypes()
                ->map(fn ($dt) => [
                    'id' => $dt->id,
                    'name' => $dt->name,
                    'manufacturer' => $dt->manufacturer,
                    'default_port' => $dt->default_port,
                ])
                ->values(),
            'branches' => fn () => $this->branchService->getActiveBranches()
                ->map(fn ($b) => ['id' => $b->id, 'branch_name' => $b->branch_name])
                ->values(),
        ]);
    }

    public function update(UpdateFingerprintDeviceRequest $request, int $id): RedirectResponse
    {
        $device = $this->deviceService->getDeviceById($id);

        if (! $device) {
            abort(404);
        }

        $this->deviceService->updateDevice($request, $device);

        return redirect()->route('fingerprint-devices.index')
            ->with('success', __('fingerprint_devices.device_updated'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->authorize('delete-fingerprint-devices');

        $device = $this->deviceService->getDeviceById($id);

        if (! $device) {
            abort(404);
        }

        $this->deviceService->deleteDevice($device);

        return redirect()->route('fingerprint-devices.index')
            ->with('success', __('fingerprint_devices.device_deleted'));
    }

    public function testConnection(int $id): RedirectResponse
    {
        $this->authorize('edit-fingerprint-devices');

        $device = $this->deviceService->getDeviceById($id);

        if (! $device) {
            abort(404);
        }

        $connected = $this->deviceService->testConnection($device);

        return redirect()->back()->with(
            $connected ? 'success' : 'error',
            $connected
                ? __('fingerprint_devices.connection_success')
                : __('fingerprint_devices.connection_failed')
        );
    }

    public function syncAttendance(int $id): RedirectResponse
    {
        $this->authorize('edit-fingerprint-devices');

        $device = $this->deviceService->getDeviceById($id);

        if (! $device) {
            abort(404);
        }

        $records = $this->deviceService->syncAttendance($device);

        return redirect()->back()->with(
            'success',
            __('fingerprint_devices.sync_complete', ['count' => count($records)])
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function cleanFilters(array $filters): array
    {
        return array_filter($filters, fn ($v) => $v !== null && $v !== '');
    }
}
